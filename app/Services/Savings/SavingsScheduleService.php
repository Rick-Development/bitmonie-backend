<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsExecution;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recurring plan lifecycle + persistent schedule. Scheduling state lives in
 * the database (next_run_at / savings_executions), never in memory, so
 * workers are restart-safe and duplicate scheduler runs are harmless
 * (unique (plan_id, scheduled_for) + conditional claim).
 */
class SavingsScheduleService
{
    public function __construct(private readonly SavingsAuditor $audit)
    {
    }

    /** Validate + normalise plan settings. @return array<string,mixed> */
    public function normalize(array $in, ?RecurringSavingsPlan $existing = null): array
    {
        $cfg = config('savings.scheduler');
        $tz = $in['timezone'] ?? $existing?->timezone ?? $cfg['default_timezone'];
        if (! in_array($tz, timezone_identifiers_list(), true)) {
            throw new SavingsException('Invalid timezone.', 'INVALID_TIMEZONE');
        }

        $frequency = strtoupper((string) ($in['frequency'] ?? $existing?->frequency ?? ''));
        if (! in_array($frequency, [RecurringSavingsPlan::DAILY, RecurringSavingsPlan::WEEKLY, RecurringSavingsPlan::MONTHLY], true)) {
            throw new SavingsException('Frequency must be daily, weekly or monthly.', 'INVALID_FREQUENCY');
        }

        $runTime = $in['run_time'] ?? $existing?->run_time ?? $cfg['default_run_time'];
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $runTime)) {
            throw new SavingsException('run_time must be HH:MM (24h).', 'INVALID_RUN_TIME');
        }

        $today = Carbon::now($tz)->startOfDay();
        $start = isset($in['start_date'])
            ? Carbon::parse($in['start_date'], $tz)->startOfDay()
            : ($existing ? Carbon::parse($existing->start_date->toDateString(), $tz) : $today->copy());

        if (! $existing && $start->lt($today)) {
            throw new SavingsException('The start date cannot be in the past.', 'INVALID_START_DATE');
        }

        $end = array_key_exists('end_date', $in)
            ? ($in['end_date'] ? Carbon::parse($in['end_date'], $tz)->startOfDay() : null)
            : ($existing?->end_date ? Carbon::parse($existing->end_date->toDateString(), $tz) : null);
        if ($end && $end->lt($start)) {
            throw new SavingsException('The end date must not be before the start date.', 'INVALID_END_DATE');
        }

        $dow = $dom = null;
        if ($frequency === RecurringSavingsPlan::WEEKLY) {
            $dow = (int) ($in['day_of_week'] ?? $existing?->day_of_week ?? $start->dayOfWeek);
            if ($dow < 0 || $dow > 6) {
                throw new SavingsException('day_of_week must be 0 (Sunday) to 6 (Saturday).', 'INVALID_DAY');
            }
        } elseif ($frequency === RecurringSavingsPlan::MONTHLY) {
            $dom = (int) ($in['day_of_month'] ?? $existing?->day_of_month ?? $start->day);
            if ($dom < 1 || $dom > 31) {
                throw new SavingsException('day_of_month must be between 1 and 31.', 'INVALID_DAY');
            }
        }

        $amount = Money::parse($in['amount'] ?? $existing?->amount ?? null, 'amount');
        Money::assertWithinLimits($amount);

        return [
            'amount' => $amount,
            'frequency' => $frequency,
            'day_of_week' => $dow,
            'day_of_month' => $dom,
            'run_time' => (string) $runTime,
            'timezone' => $tz,
            'start_date' => $start->toDateString(),
            'end_date' => $end?->toDateString(),
        ];
    }

    /** Create (or replace a cancelled/paused) plan for an account. */
    public function createPlan(SavingsAccount $account, array $settings, ?Carbon $now = null): RecurringSavingsPlan
    {
        $now ??= Carbon::now('UTC');
        $plan = RecurringSavingsPlan::create($settings + [
            'account_id' => $account->id,
            'user_id' => $account->user_id,
            'funding_source' => 'WALLET',
            'status' => RecurringSavingsPlan::ACTIVE,
            'next_run_at' => $this->nextRunAfter($settings, $now),
        ]);

        $this->audit->record('plan.created', $account->user_id, $account->id, ['plan_id' => $plan->id, 'frequency' => $plan->frequency]);

        return $plan;
    }

    public function updatePlan(RecurringSavingsPlan $plan, array $input, ?Carbon $now = null): RecurringSavingsPlan
    {
        $now ??= Carbon::now('UTC');

        return DB::transaction(function () use ($plan, $input, $now) {
            $locked = RecurringSavingsPlan::whereKey($plan->id)->where('user_id', $plan->user_id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
                throw SavingsException::invalidState('Only active or paused plans can be edited.');
            }

            $settings = $this->normalize($input, $locked);
            $locked->fill($settings);
            if ($locked->status === RecurringSavingsPlan::ACTIVE) {
                $locked->next_run_at = $this->nextRunAfter($settings, $now);
            }
            $locked->save();

            $this->audit->record('plan.updated', $locked->user_id, $locked->account_id, ['plan_id' => $locked->id, 'changed' => array_keys($input)]);

            return $locked;
        });
    }

    public function pause(RecurringSavingsPlan $plan): RecurringSavingsPlan
    {
        return $this->transition($plan, RecurringSavingsPlan::PAUSED, 'plan.paused');
    }

    public function resume(RecurringSavingsPlan $plan, ?Carbon $now = null): RecurringSavingsPlan
    {
        $now ??= Carbon::now('UTC');

        return $this->transition($plan, RecurringSavingsPlan::ACTIVE, 'plan.resumed', function (RecurringSavingsPlan $p) use ($now) {
            // Resuming never back-fills: schedule restarts from the next slot.
            $p->next_run_at = $this->nextRunAfter($p->only(['frequency', 'day_of_week', 'day_of_month', 'run_time', 'timezone']) + ['start_date' => $p->start_date->toDateString()], $now);
        });
    }

    public function cancel(RecurringSavingsPlan $plan): RecurringSavingsPlan
    {
        return $this->transition($plan, RecurringSavingsPlan::CANCELLED, 'plan.cancelled', fn ($p) => $p->next_run_at = null);
    }

    /** Locks the plan row so pause/cancel cannot race with a running collection. */
    public function transition(RecurringSavingsPlan $plan, string $to, string $auditEvent, ?callable $mutate = null): RecurringSavingsPlan
    {
        return DB::transaction(function () use ($plan, $to, $auditEvent, $mutate) {
            $locked = RecurringSavingsPlan::whereKey($plan->id)->where('user_id', $plan->user_id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $to) {
                return $locked; // idempotent
            }
            SavingsStateMachine::plan($locked->status, $to);
            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->audit->record($auditEvent, $locked->user_id, $locked->account_id, ['plan_id' => $locked->id]);

            return $locked;
        });
    }

    /**
     * Next run strictly after $after (UTC instant), honouring the plan's
     * timezone. Monthly days beyond month end clamp to the last day.
     *
     * @param array $s frequency, day_of_week, day_of_month, run_time, timezone, start_date
     */
    public function nextRunAfter(array $s, Carbon $after): Carbon
    {
        $tz = $s['timezone'];
        [$h, $m] = array_map('intval', explode(':', $s['run_time']));

        $floor = Carbon::parse($s['start_date'], $tz)->startOfDay()->subSecond();
        $cursor = $after->copy()->utc();
        if ($cursor->lt($floor)) {
            $cursor = $floor->copy()->utc();
        }

        $day = $cursor->copy()->setTimezone($tz)->startOfDay();
        for ($i = 0; $i <= 800; $i++) {
            $d = $day->copy()->addDays($i);

            $matches = match ($s['frequency']) {
                RecurringSavingsPlan::DAILY => true,
                RecurringSavingsPlan::WEEKLY => $d->dayOfWeek === (int) $s['day_of_week'],
                RecurringSavingsPlan::MONTHLY => $d->day === min((int) $s['day_of_month'], $d->daysInMonth),
                default => false,
            };
            if (! $matches) {
                continue;
            }

            $candidate = Carbon::create($d->year, $d->month, $d->day, $h, $m, 0, $tz);
            if ($candidate->gt($cursor)) {
                return $candidate->utc();
            }
        }

        throw new SavingsException('Unable to compute the next run.', 'SCHEDULE_ERROR');
    }

    /**
     * Turn due plans into persisted PENDING executions (one per slot) and
     * advance each plan to its next FUTURE slot. Slots older than the grace
     * window are recorded SKIPPED: missed instalments are never collected late
     * and never in bulk.
     *
     * @return array{queued:int, skipped:int, completed_plans:int}
     */
    public function enqueueDue(?Carbon $now = null, ?int $limit = null): array
    {
        $now ??= Carbon::now('UTC');
        $limit ??= (int) config('savings.scheduler.batch_size');
        $grace = (int) config('savings.scheduler.grace_minutes');
        $stats = ['queued' => 0, 'skipped' => 0, 'completed_plans' => 0];

        $ids = RecurringSavingsPlan::where('status', RecurringSavingsPlan::ACTIVE)
            ->whereNotNull('next_run_at')->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, $now, $grace, &$stats) {
                    $plan = RecurringSavingsPlan::whereKey($id)->lockForUpdate()->first();
                    if (! $plan || $plan->status !== RecurringSavingsPlan::ACTIVE || ! $plan->next_run_at || $plan->next_run_at->gt($now)) {
                        return; // another worker handled it, or it was paused
                    }

                    $slot = $plan->next_run_at->copy()->utc();
                    $stale = $slot->diffInMinutes($now) > $grace;

                    try {
                        SavingsExecution::create([
                            'plan_id' => $plan->id, 'account_id' => $plan->account_id, 'user_id' => $plan->user_id,
                            'amount' => $plan->amount, 'scheduled_for' => $slot,
                            'status' => $stale ? SavingsExecution::SKIPPED : SavingsExecution::PENDING,
                            'failure_code' => $stale ? 'MISSED' : null,
                            'failure_message' => $stale ? 'Scheduler was unavailable; instalment not collected late.' : null,
                        ]);
                        $stale ? $stats['skipped']++ : $stats['queued']++;
                    } catch (QueryException $e) {
                        Log::info('savings.execution_already_queued', ['plan_id' => $plan->id, 'slot' => (string) $slot]);
                    }

                    $settings = $plan->only(['frequency', 'day_of_week', 'day_of_month', 'run_time', 'timezone']) + ['start_date' => $plan->start_date->toDateString()];
                    $next = $this->nextRunAfter($settings, $now->copy()->max($slot));

                    $endsBefore = $plan->end_date && Carbon::parse($plan->end_date->toDateString(), $plan->timezone)->endOfDay()->utc()->lt($next);
                    if ($endsBefore) {
                        $plan->status = RecurringSavingsPlan::COMPLETED;
                        $plan->next_run_at = null;
                        $stats['completed_plans']++;
                    } else {
                        $plan->next_run_at = $next;
                    }
                    $plan->save();
                });
            } catch (\Throwable $e) {
                Log::error('savings.enqueue_failed', ['plan_id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $stats;
    }
}
