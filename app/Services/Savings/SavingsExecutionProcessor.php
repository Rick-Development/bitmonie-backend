<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsExecution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes persisted scheduled contributions.
 *
 * Safety model:
 *  - A worker CLAIMS an execution with a conditional UPDATE
 *    (status PENDING -> PROCESSING); only one worker can win.
 *  - Debit + credit + execution completion happen in ONE local DB
 *    transaction, and savings_entries.execution_id is UNIQUE, so even a
 *    reclaimed stale execution can credit at most once.
 *  - The plan row is locked inside that transaction, so pause/cancel either
 *    wins before collection starts (execution SKIPPED) or after it finished.
 *  - Funding is the user's internal wallet, so no external, uncertain
 *    provider call is involved (no simulated or unverified debits).
 */
class SavingsExecutionProcessor
{
    public function __construct(
        private readonly SavingsLedger $ledger,
        private readonly SavingsManager $manager,
        private readonly SavingsAuditor $audit,
    ) {
    }

    /** @return array{processed:int, completed:int, retried:int, failed:int, skipped:int} */
    public function processDue(?Carbon $now = null, ?int $limit = null): array
    {
        $now ??= Carbon::now('UTC');
        $limit ??= (int) config('savings.scheduler.batch_size');
        $stats = ['processed' => 0, 'completed' => 0, 'retried' => 0, 'failed' => 0, 'skipped' => 0];

        $this->releaseStale($now);

        $ids = SavingsExecution::where('status', SavingsExecution::PENDING)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('scheduled_for')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            if (! $this->claim($id, $now)) {
                continue; // another worker owns it
            }
            $stats['processed']++;
            $outcome = $this->execute($id, $now);
            $stats[$outcome]++;
        }

        return $stats;
    }

    /** Conditional claim: exactly one concurrent worker gets affected-rows = 1. */
    public function claim(int $id, Carbon $now): bool
    {
        return SavingsExecution::where('id', $id)
            ->where('status', SavingsExecution::PENDING)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->update([
                'status' => SavingsExecution::PROCESSING,
                'locked_at' => $now,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => $now,
            ]) === 1;
    }

    /** A worker that died mid-run left PROCESSING rows; safe to re-queue (credit is at-most-once). */
    private function releaseStale(Carbon $now): void
    {
        $cutoff = $now->copy()->subMinutes((int) config('savings.scheduler.stale_lock_minutes'));
        $n = SavingsExecution::where('status', SavingsExecution::PROCESSING)->where('locked_at', '<', $cutoff)
            ->update(['status' => SavingsExecution::PENDING, 'locked_at' => null]);
        if ($n > 0) {
            Log::warning('savings.stale_executions_released', ['count' => $n]);
        }
    }

    /** @return 'completed'|'retried'|'failed'|'skipped' */
    private function execute(int $id, Carbon $now): string
    {
        $execution = SavingsExecution::findOrFail($id);

        try {
            $result = DB::transaction(function () use ($execution, $now) {
                $plan = RecurringSavingsPlan::whereKey($execution->plan_id)->lockForUpdate()->firstOrFail();
                $account = SavingsAccount::whereKey($execution->account_id)->lockForUpdate()->firstOrFail();

                if ($plan->status !== RecurringSavingsPlan::ACTIVE || $account->status !== SavingsAccount::ACTIVE) {
                    SavingsStateMachine::execution(SavingsExecution::PROCESSING, SavingsExecution::SKIPPED);
                    $execution->update([
                        'status' => SavingsExecution::SKIPPED, 'failure_code' => 'PLAN_INACTIVE',
                        'failure_message' => 'Plan or account was not active at collection time.', 'locked_at' => null,
                    ]);

                    return ['status' => 'skipped'];
                }

                $r = $this->ledger->deposit($execution->user_id, $execution->account_id, Money::norm($execution->amount), [
                    'execution_id' => $execution->id, 'source' => 'scheduled', 'narration' => "Scheduled contribution: {$account->title}",
                    'meta' => ['plan_id' => $plan->id, 'scheduled_for' => $execution->scheduled_for->toIso8601String()],
                    // Scheduled instalments are clipped to the remaining target amount (never over-collect).
                    'guard' => function (SavingsAccount $a, string $amount) {
                        if ($a->product === SavingsAccount::TARGET) {
                            $remaining = Money::sub(Money::norm($a->target_amount), Money::norm($a->balance));
                            $amount = Money::min($amount, $remaining);
                            if (Money::cmp($amount, '0.00') <= 0) {
                                throw SavingsException::invalidState('The target is already fully funded.');
                            }
                        }
                        $this->manager->assertCanReceive($a, $amount);

                        return $amount;
                    },
                    'after' => fn (SavingsAccount $a) => $this->manager->afterCreditHook($a),
                ]);

                SavingsStateMachine::execution(SavingsExecution::PROCESSING, SavingsExecution::COMPLETED);
                $execution->update([
                    'status' => SavingsExecution::COMPLETED, 'entry_id' => $r['entry']->id, 'completed_at' => $now,
                    'locked_at' => null, 'failure_code' => null, 'failure_message' => null, 'amount' => $r['entry']->amount,
                ]);
                $plan->update(['last_success_at' => $now]);

                return ['status' => 'completed', 'entry' => $r['entry']];
            });
        } catch (SavingsException $e) {
            return $this->fail($execution->refresh(), $e->errorCode, $e->getMessage(), $e->retryable, $now);
        } catch (\Throwable $e) {
            Log::error('savings.execution_error', ['execution_id' => $id, 'error' => $e->getMessage()]);

            return $this->fail($execution->refresh(), 'TEMPORARY_ERROR', 'Temporary processing error.', true, $now);
        }

        if ($result['status'] === 'completed') {
            $this->audit->record('execution.completed', $execution->user_id, $execution->account_id, [
                'execution_id' => $execution->id, 'amount' => $result['entry']->amount,
            ], 'scheduler');
            // Notify only after the transaction has committed.
            $this->manager->notify($execution->user_id, 'Auto Save', 'Topup', $result['entry']->amount, $result['entry']->reference);
        } else {
            $this->audit->record('execution.skipped', $execution->user_id, $execution->account_id, ['execution_id' => $execution->id], 'scheduler');
        }

        return $result['status'];
    }

    /** Bounded retry for retryable failures; permanent failure otherwise. */
    private function fail(SavingsExecution $e, string $code, string $message, bool $retryable, Carbon $now): string
    {
        $max = (int) config('savings.scheduler.max_attempts');
        $backoff = (array) config('savings.scheduler.backoff_minutes');

        if ($retryable && $e->attempts < $max) {
            SavingsStateMachine::execution($e->status, SavingsExecution::PENDING);
            $e->update([
                'status' => SavingsExecution::PENDING, 'locked_at' => null, 'failure_code' => $code,
                'failure_message' => mb_substr($message, 0, 255),
                'next_attempt_at' => $now->copy()->addMinutes((int) ($backoff[$e->attempts - 1] ?? end($backoff) ?: 60)),
            ]);
            Log::info('savings.execution_retry_scheduled', ['execution_id' => $e->id, 'attempt' => $e->attempts, 'code' => $code]);

            return 'retried';
        }

        SavingsStateMachine::execution($e->status, SavingsExecution::FAILED);
        $e->update([
            'status' => SavingsExecution::FAILED, 'locked_at' => null, 'failure_code' => $code,
            'failure_message' => mb_substr($message, 0, 255), 'next_attempt_at' => null,
        ]);
        Log::warning('savings.execution_failed', ['execution_id' => $e->id, 'code' => $code, 'retry_exhausted' => $retryable]);
        $this->audit->record('execution.failed', $e->user_id, $e->account_id, ['execution_id' => $e->id, 'code' => $code], 'scheduler');
        // No fee is charged for a failed collection; the user is only informed.
        $this->manager->notify($e->user_id, 'Auto Save', 'Failed', $e->amount, null);

        return 'failed';
    }
}
