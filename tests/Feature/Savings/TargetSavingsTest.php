<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsMaintenance;
use App\Services\Savings\SavingsManager;
use Carbon\Carbon;

class TargetSavingsTest extends SavingsTestCase
{
    protected SavingsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SavingsManager::class);
    }

    public function test_create_multiple_targets_and_validate_amounts_and_deadlines(): void
    {
        $user = $this->makeUser('5000.00');

        $t1 = $this->manager->createTarget($user, [
            'title' => 'Trip to Paris',
            'target_amount' => '1000.00',
            'deadline' => '2026-12-31',
        ]);
        $this->assertSame('Trip to Paris', $t1['account']->title);
        $this->assertSame('1000.00', Money::norm($t1['account']->target_amount));

        $t2 = $this->manager->createTarget($user, [
            'title' => 'New Laptop',
            'target_amount' => '2500.00',
        ]);
        $this->assertSame('New Laptop', $t2['account']->title);

        $this->assertCount(2, SavingsAccount::where('user_id', $user->id)->where('product', SavingsAccount::TARGET)->get());

        // Invalid cases
        try {
            $this->manager->createTarget($user, [
                'title' => 'Invalid',
                'target_amount' => '-100',
            ]);
            $this->fail('Expected exception for negative target amount');
        } catch (SavingsException $e) {
            $this->assertSame('INVALID_AMOUNT', $e->errorCode);
        }

        try {
            $this->manager->createTarget($user, [
                'title' => 'Invalid Deadline',
                'target_amount' => '500.00',
                'start_date' => '2026-11-01',
                'deadline' => '2026-10-01', // Before start
            ]);
            $this->fail('Expected exception for deadline before start date');
        } catch (SavingsException $e) {
            $this->assertSame('INVALID_DEADLINE', $e->errorCode);
        }
    }

    public function test_manual_contributions_and_accurate_progress_calculation(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createTarget($user, [
            'title' => 'Wedding Fund',
            'target_amount' => '1000.00',
        ]);
        $target = $created['account'];

        // Initial progress
        $p0 = $this->manager->progress($target);
        $this->assertSame('0.00', $p0['balance']);
        $this->assertSame('1000.00', $p0['remaining_amount']);
        $this->assertSame('0.00', $p0['percentage_completed']);

        // Contribute 250
        $this->manager->contribute($user, $target->id, '250.00', 'wallet', 'contrib-key-01');
        $p1 = $this->manager->progress($target->refresh());
        $this->assertSame('250.00', $p1['balance']);
        $this->assertSame('750.00', $p1['remaining_amount']);
        $this->assertSame('25.00', $p1['percentage_completed']);

        // Contribute another 500
        $this->manager->contribute($user, $target->id, '500.00', 'wallet', 'contrib-key-02');
        $p2 = $this->manager->progress($target->refresh());
        $this->assertSame('750.00', $p2['balance']);
        $this->assertSame('250.00', $p2['remaining_amount']);
        $this->assertSame('75.00', $p2['percentage_completed']);
    }

    public function test_target_completes_automatically_when_goal_reached_and_prevents_excess_contributions(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createTarget($user, [
            'title' => 'School Fees',
            'target_amount' => '1000.00',
        ]);
        $target = $created['account'];

        // Try to contribute 1200 (exceeds 1000)
        try {
            $this->manager->contribute($user, $target->id, '1200.00', 'wallet', 'excess-key-01');
            $this->fail('Expected exception for exceeding target');
        } catch (SavingsException $e) {
            $this->assertSame('EXCEEDS_TARGET', $e->errorCode);
        }

        // Contribute exact 1000
        $this->manager->contribute($user, $target->id, '1000.00', 'wallet', 'exact-key-01');
        $target->refresh();
        $this->assertSame(SavingsAccount::COMPLETED, $target->status);
        $this->assertNotNull($target->completed_at);

        // Try to contribute after completion
        try {
            $this->manager->contribute($user, $target->id, '100.00', 'wallet', 'after-complete-01');
            $this->fail('Expected exception for contributing to completed target');
        } catch (SavingsException $e) {
            $this->assertSame('INVALID_STATE', $e->errorCode);
        }
    }

    public function test_target_expires_past_deadline_without_deductions_and_funds_remain_withdrawable(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createTarget($user, [
            'title' => 'Concert Ticket',
            'target_amount' => '1000.00',
            'start_date' => Carbon::now()->toDateString(),
            'deadline' => Carbon::now()->addDays(5)->toDateString(),
        ]);
        $target = $created['account'];
        $this->manager->contribute($user, $target->id, '400.00', 'wallet', 'concert-key-01');

        $maintenance = app(SavingsMaintenance::class);
        $expiredCount = $maintenance->expireTargets(Carbon::now()->addDays(10));
        $this->assertSame(1, $expiredCount);

        $target->refresh();
        $this->assertSame(SavingsAccount::EXPIRED, $target->status);
        $this->assertSame('400.00', Money::norm($target->balance));

        // Withdraw from expired target to wallet
        $res = $this->manager->withdraw($user, $target->id, '400.00', 'wdr-expired-01');
        $this->assertSame('0.00', Money::norm($target->refresh()->balance));
        $this->assertSame('5000.00', $this->walletBalance($user)); // restored to original
    }

    public function test_early_closure_fee_applied_with_explicit_acknowledgement(): void
    {
        $user = $this->makeUser('5000.00');
        $created = $this->manager->createTarget($user, [
            'title' => 'Long Term Goal',
            'target_amount' => '2000.00',
            'deadline' => Carbon::now()->addMonths(6)->toDateString(),
        ]);
        $target = $created['account'];
        $this->manager->contribute($user, $target->id, '1000.00', 'wallet', 'early-contrib-01');

        // Quote (refresh target so balance 1000 is reflected)
        $quote = $this->manager->closureQuote($target->refresh());
        $this->assertTrue($quote['requires_acknowledgement']);
        $this->assertSame('20.00', $quote['fee']); // 2% of 1000 is 20
        $this->assertSame('980.00', $quote['net_amount']);

        // Close without acknowledgement -> fails
        try {
            $this->manager->closeTarget($user, $target->id, false, 'close-key-01');
            $this->fail('Expected failure without fee acknowledgement');
        } catch (SavingsException $e) {
            $this->assertSame('FEE_ACKNOWLEDGEMENT_REQUIRED', $e->errorCode);
        }

        // Close with acknowledgement
        $res = $this->manager->closeTarget($user, $target->id, true, 'close-key-02');
        $this->assertSame('980.00', Money::norm($res['entry']->amount));
        $target->refresh();
        $this->assertSame(SavingsAccount::CANCELLED, $target->status);
        $this->assertSame('0.00', Money::norm($target->balance));

        // Wallet got net amount (5000 - 1000 + 980 = 4980)
        $this->assertSame('4980.00', $this->walletBalance($user));
    }
}
