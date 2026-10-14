<?php

namespace Tests\Feature\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsExecution;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsExecutionProcessor;
use App\Services\Savings\SavingsLedger;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsScheduleService;
use Carbon\Carbon;

class AutoSaveTest extends SavingsTestCase
{
    protected SavingsManager $manager;
    protected SavingsScheduleService $schedule;
    protected SavingsExecutionProcessor $processor;
    protected SavingsLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SavingsManager::class);
        $this->schedule = app(SavingsScheduleService::class);
        $this->processor = app(SavingsExecutionProcessor::class);
        $this->ledger = app(SavingsLedger::class);
    }

    public function test_create_daily_weekly_and_monthly_schedule(): void
    {
        $user = $this->makeUser('5000.00');

        $daily = $this->manager->createAuto($user, [
            'title' => 'Daily Save',
            'amount' => '100.00',
            'frequency' => 'daily',
            'run_time' => '09:00',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->assertSame('DAILY', $daily['plan']->frequency);
        $this->assertSame('100.00', Money::norm($daily['plan']->amount));
        $this->assertNotNull($daily['plan']->next_run_at);

        $weekly = $this->manager->createAuto($user, [
            'title' => 'Weekly Save',
            'amount' => '500.00',
            'frequency' => 'weekly',
            'day_of_week' => 1, // Monday
            'run_time' => '10:00',
        ]);
        $this->assertSame('WEEKLY', $weekly['plan']->frequency);
        $this->assertSame(1, $weekly['plan']->day_of_week);

        $monthly = $this->manager->createAuto($user, [
            'title' => 'Monthly Save',
            'amount' => '2000.00',
            'frequency' => 'monthly',
            'day_of_month' => 31,
            'run_time' => '12:00',
        ]);
        $this->assertSame('MONTHLY', $monthly['plan']->frequency);
        $this->assertSame(31, $monthly['plan']->day_of_month);
    }

    public function test_validate_invalid_amounts_and_invalid_dates(): void
    {
        $user = $this->makeUser('1000.00');

        $invalidCases = [
            ['amount' => '-10', 'frequency' => 'daily'],
            ['amount' => '0', 'frequency' => 'daily'],
            ['amount' => '50.00', 'frequency' => 'daily'], // Below minimum
            ['amount' => '100.00', 'frequency' => 'hourly'], // Invalid frequency
            ['amount' => '100.00', 'frequency' => 'weekly', 'day_of_week' => 7], // 0-6
            ['amount' => '100.00', 'frequency' => 'monthly', 'day_of_month' => 32],
            ['amount' => '100.00', 'frequency' => 'daily', 'start_date' => '2020-01-01'], // Past date
            ['amount' => '100.00', 'frequency' => 'daily', 'start_date' => '2026-11-10', 'end_date' => '2026-11-05'],
        ];

        foreach ($invalidCases as $case) {
            try {
                $this->manager->createAuto($user, $case);
                $this->fail("Expected failure for case: " . json_encode($case));
            } catch (SavingsException $e) {
                $this->assertNotEmpty($e->errorCode);
            }
        }
    }

    public function test_pause_resume_modify_and_cancel_plan(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createAuto($user, [
            'title' => 'Savings Plan 1',
            'amount' => '200.00',
            'frequency' => 'daily',
        ]);
        $plan = $created['plan'];
        $account = $created['account'];

        // Pause
        $paused = $this->manager->pausePlan($user, $account->id);
        $this->assertSame(RecurringSavingsPlan::PAUSED, $paused->status);

        // Resume
        $resumed = $this->manager->resumePlan($user, $account->id);
        $this->assertSame(RecurringSavingsPlan::ACTIVE, $resumed->status);
        $this->assertNotNull($resumed->next_run_at);

        // Update
        $updated = $this->manager->updateAuto($user, $account->id, [
            'title' => 'Updated Plan 1',
            'amount' => '300.00',
        ]);
        $this->assertSame('300.00', Money::norm($updated['plan']->amount));
        $this->assertSame('Updated Plan 1', $updated['account']->title);

        // Cancel
        $cancelledAccount = $this->manager->cancelAuto($user, $account->id);
        $this->assertSame(SavingsAccount::CANCELLED, $cancelledAccount->status);
        $this->assertSame(RecurringSavingsPlan::CANCELLED, $cancelledAccount->plan->status);
        $this->assertNull($cancelledAccount->plan->next_run_at);
    }

    public function test_execute_due_contribution(): void
    {
        $user = $this->makeUser('1000.00');
        $created = $this->manager->createAuto($user, [
            'title' => 'Car Fund',
            'amount' => '200.00',
            'frequency' => 'daily',
        ]);
        $plan = $created['plan'];

        // Set next run in the past so it's due
        $dueTime = Carbon::now('UTC')->subMinutes(5);
        $plan->update(['next_run_at' => $dueTime]);

        $enqueued = $this->schedule->enqueueDue(Carbon::now('UTC'));
        $this->assertSame(1, $enqueued['queued']);

        $execution = SavingsExecution::where('plan_id', $plan->id)->first();
        $this->assertNotNull($execution);
        $this->assertSame(SavingsExecution::PENDING, $execution->status);

        $processed = $this->processor->processDue(Carbon::now('UTC'));
        $this->assertSame(1, $processed['completed']);

        $execution->refresh();
        $this->assertSame(SavingsExecution::COMPLETED, $execution->status);
        $this->assertSame('800.00', $this->walletBalance($user));
        $this->assertSame('200.00', Money::norm($created['account']->refresh()->balance));
    }

    public function test_handle_insufficient_funds_and_bounded_retry(): void
    {
        $user = $this->makeUser('50.00'); // Plan needs 200
        $created = $this->manager->createAuto($user, [
            'title' => 'Rent',
            'amount' => '200.00',
            'frequency' => 'daily',
        ]);
        $plan = $created['plan'];

        $plan->update(['next_run_at' => Carbon::now('UTC')->subMinutes(5)]);
        $this->schedule->enqueueDue(Carbon::now('UTC'));

        $now = Carbon::now('UTC');
        $processed = $this->processor->processDue($now);
        $this->assertSame(1, $processed['retried']);

        $execution = SavingsExecution::where('plan_id', $plan->id)->first();
        $this->assertSame(SavingsExecution::PENDING, $execution->status);
        $this->assertSame(1, $execution->attempts);
        $this->assertSame('INSUFFICIENT_FUNDS', $execution->failure_code);
        $this->assertNotNull($execution->next_attempt_at);

        // Exhaust retries: max attempts is 3
        $execution->update(['attempts' => 2, 'next_attempt_at' => $now->subMinute()]);
        $processed2 = $this->processor->processDue($now);
        $this->assertSame(1, $processed2['failed']);

        $execution->refresh();
        $this->assertSame(SavingsExecution::FAILED, $execution->status);
        $this->assertSame(3, $execution->attempts);
        $this->assertSame('50.00', $this->walletBalance($user));
    }

    public function test_concurrent_workers_claim_execution_only_once(): void
    {
        $user = $this->makeUser('1000.00');
        $created = $this->manager->createAuto($user, [
            'title' => 'Emergency',
            'amount' => '100.00',
            'frequency' => 'daily',
        ]);
        $plan = $created['plan'];
        $plan->update(['next_run_at' => Carbon::now('UTC')->subMinute()]);
        $this->schedule->enqueueDue(Carbon::now('UTC'));

        $execution = SavingsExecution::where('plan_id', $plan->id)->first();
        $now = Carbon::now('UTC');

        // First worker claims
        $claimed1 = $this->processor->claim($execution->id, $now);
        $this->assertTrue($claimed1);

        // Second worker tries to claim same execution
        $claimed2 = $this->processor->claim($execution->id, $now);
        $this->assertFalse($claimed2);
    }

    public function test_month_end_schedule_clamps_properly(): void
    {
        $settings = [
            'frequency' => RecurringSavingsPlan::MONTHLY,
            'day_of_month' => 31,
            'run_time' => '08:00',
            'timezone' => 'Africa/Lagos',
            'start_date' => '2026-02-01',
        ];

        // Evaluating from 2026-02-15
        $next = $this->schedule->nextRunAfter($settings, Carbon::parse('2026-02-15 00:00:00', 'UTC'));
        $localNext = $next->copy()->setTimezone('Africa/Lagos');

        // February has 28 days in 2026, so it must clamp to Feb 28
        $this->assertSame(28, $localNext->day);
        $this->assertSame(2, $localNext->month);
    }

    public function test_missed_executions_outside_grace_window_are_skipped_without_catchup(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createAuto($user, [
            'title' => 'Skipped Test',
            'amount' => '100.00',
            'frequency' => 'daily',
        ]);
        $plan = $created['plan'];

        // Plan was due 2 days ago (way past default grace of 360 minutes = 6 hours)
        $staleDate = Carbon::now('UTC')->subDays(2);
        $plan->update(['next_run_at' => $staleDate]);

        $enqueued = $this->schedule->enqueueDue(Carbon::now('UTC'));
        $this->assertSame(1, $enqueued['skipped']);
        $this->assertSame(0, $enqueued['queued']);

        $execution = SavingsExecution::where('plan_id', $plan->id)->first();
        $this->assertSame(SavingsExecution::SKIPPED, $execution->status);
        $this->assertSame('MISSED', $execution->failure_code);

        // Verify next run advanced to future, no bulk debits
        $this->assertTrue($plan->refresh()->next_run_at->gt(Carbon::now('UTC')));
        $this->assertSame('5000.00', $this->walletBalance($user));
    }
}
