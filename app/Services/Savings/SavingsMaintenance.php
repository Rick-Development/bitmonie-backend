<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\SavingsExecution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Periodic housekeeping: target expiry, Flexi interest, ledger reconciliation. */
class SavingsMaintenance
{
    public function __construct(
        private readonly SavingsLedger $ledger,
        private readonly SavingsManager $manager,
        private readonly SavingsScheduleService $schedule,
        private readonly SavingsAuditor $audit,
    ) {
    }

    /**
     * Targets past their deadline that did not reach the goal become EXPIRED:
     * no new contributions, schedule cancelled, money stays withdrawable
     * without any early-closure fee. Nothing is deducted automatically.
     */
    public function expireTargets(?Carbon $now = null): int
    {
        $tz = (string) config('savings.scheduler.default_timezone');
        $today = ($now ?? Carbon::now($tz))->copy()->setTimezone($tz)->toDateString();
        $count = 0;

        SavingsAccount::where('product', SavingsAccount::TARGET)
            ->whereIn('status', [SavingsAccount::ACTIVE, SavingsAccount::PAUSED])
            ->whereNotNull('deadline')->whereDate('deadline', '<', $today)
            ->orderBy('id')->each(function (SavingsAccount $candidate) use (&$count) {
                DB::transaction(function () use ($candidate, &$count) {
                    $a = SavingsAccount::whereKey($candidate->id)->lockForUpdate()->first();
                    if (! in_array($a->status, [SavingsAccount::ACTIVE, SavingsAccount::PAUSED], true)) {
                        return;
                    }
                    if (Money::cmp(Money::norm($a->balance), Money::norm($a->target_amount)) >= 0) {
                        $this->manager->afterCreditHook($a->forceFill(['status' => SavingsAccount::ACTIVE]));
                        $count++;

                        return;
                    }
                    SavingsStateMachine::account($a->status, SavingsAccount::EXPIRED);
                    $a->update(['status' => SavingsAccount::EXPIRED]);

                    $plan = $a->plan;
                    if ($plan && in_array($plan->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
                        $this->schedule->cancel($plan);
                    }
                    $this->audit->record('target.expired', $a->user_id, $a->id, ['balance' => Money::norm($a->balance)], 'scheduler');
                    $count++;
                });
            });

        return $count;
    }

    /**
     * Credit Flexi interest for calendar day $day (app timezone).
     * Basis: money present for the WHOLE day (opening balance minus that day's
     * debits). Simple daily accrual, rate = APR/365, rounded DOWN to 2dp.
     * Idempotent per (account, day) through the unique entry reference.
     *
     * @return array{accounts:int, interest:string, skipped:int}
     */
    public function accrueFlexiInterest(Carbon $day): array
    {
        $out = ['accounts' => 0, 'interest' => '0.00', 'skipped' => 0];
        if (! config('savings.flexi.interest_enabled')) {
            return $out;
        }

        $tz = (string) config('savings.scheduler.default_timezone');
        $apr = (string) config('savings.flexi.interest_apr_percent');
        $start = Carbon::parse($day->toDateString(), $tz)->startOfDay()->utc();

        SavingsAccount::where('product', SavingsAccount::FLEXI)->where('status', SavingsAccount::ACTIVE)
            ->where('balance', '>', 0)->orderBy('id')->each(function (SavingsAccount $a) use (&$out, $start, $apr, $day) {
                $since = SavingsEntry::where('account_id', $a->id)->where('created_at', '>=', $start)->get();
                $opening = Money::norm($a->balance);
                $debitsToday = '0.00';
                foreach ($since as $e) {
                    if ($e->direction === SavingsEntry::CREDIT) {
                        $opening = Money::sub($opening, $e->amount);
                    } else {
                        $opening = Money::add($opening, $e->amount);
                        $debitsToday = Money::add($debitsToday, $e->amount);
                    }
                }
                $eligible = Money::sub($opening, $debitsToday);
                if (Money::cmp($eligible, '0.00') <= 0) {
                    $out['skipped']++;

                    return;
                }
                $interest = bcdiv(bcmul($eligible, bcdiv($apr, '100', 12), 12), '365', 2); // truncates
                if (Money::cmp($interest, '0.00') <= 0) {
                    $out['skipped']++;

                    return;
                }

                $ref = 'INT-' . $a->id . '-' . $day->format('Ymd');
                try {
                    $r = $this->ledger->interest($a->user_id, $a->id, $interest, $ref, 'Flexi Save daily interest ' . $day->toDateString());
                } catch (\Throwable $e) {
                    Log::error('savings.interest_failed', ['account_id' => $a->id, 'error' => $e->getMessage()]);

                    return;
                }
                if ($r['replayed']) {
                    $out['skipped']++;

                    return;
                }
                $out['accounts']++;
                $out['interest'] = Money::add($out['interest'], $interest);
            });

        return $out;
    }

    /**
     * Verifies the ledger invariants and reports anything an operator must
     * look at. Read-only: it never "fixes" money.
     *
     * @return array<int, array<string,mixed>> findings
     */
    public function reconcile(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $findings = [];

        SavingsAccount::orderBy('id')->each(function (SavingsAccount $a) use (&$findings) {
            $credits = '0.00';
            $debits = '0.00';
            SavingsEntry::where('account_id', $a->id)->whereIn('status', ['COMPLETED', 'REVERSED'])->orderBy('id')
                ->each(function (SavingsEntry $e) use (&$credits, &$debits) {
                    $e->direction === SavingsEntry::CREDIT
                        ? $credits = Money::add($credits, $e->amount)
                        : $debits = Money::add($debits, $e->amount);
                });
            $expected = Money::sub($credits, $debits);
            $actual = Money::norm($a->balance);

            if (Money::cmp($expected, $actual) !== 0) {
                $findings[] = ['type' => 'BALANCE_MISMATCH', 'account_id' => $a->id, 'ledger' => $expected, 'balance' => $actual];
                Log::critical('savings.reconciliation_mismatch', ['account_id' => $a->id, 'ledger' => $expected, 'balance' => $actual]);
            }
            if (Money::cmp($actual, '0.00') < 0) {
                $findings[] = ['type' => 'NEGATIVE_BALANCE', 'account_id' => $a->id, 'balance' => $actual];
                Log::critical('savings.negative_balance', ['account_id' => $a->id]);
            }
        });

        $stuck = SavingsExecution::where('status', SavingsExecution::PROCESSING)
            ->where('locked_at', '<', $now->copy()->subMinutes(60))->get();
        foreach ($stuck as $e) {
            $findings[] = ['type' => 'STUCK_EXECUTION', 'execution_id' => $e->id];
            Log::error('savings.stuck_execution', ['execution_id' => $e->id]);
        }

        return $findings;
    }
}
