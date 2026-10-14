<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsLedger;
use App\Services\Savings\SavingsMaintenance;
use App\Services\Savings\SavingsManager;
use App\Services\Savings\SavingsStateMachine;

class LedgerTest extends SavingsTestCase
{
    private SavingsLedger $ledger;
    private SavingsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(SavingsLedger::class);
        $this->manager = app(SavingsManager::class);
    }

    public function test_flexi_deposit_moves_exact_amount_from_wallet_and_writes_entry(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);

        $r = $this->ledger->deposit($user->id, $flexi->id, '250.50', ['idempotency_key' => 'key-0001-aaaa']);

        $this->assertFalse($r['replayed']);
        $this->assertSame('749.50', $this->walletBalance($user));
        $flexi->refresh();
        $this->assertSame('250.50', Money::norm($flexi->balance));
        $this->assertSame('250.50', Money::norm($flexi->total_deposited));
        $this->assertSame('COMPLETED', $r['entry']->status);
        $this->assertSame('250.50', Money::norm($r['entry']->balance_after));
        $this->assertSame(1, \App\Models\OrderTransaction::where('reference', $r['entry']->reference)->count());
    }

    public function test_insufficient_wallet_funds_changes_nothing(): void
    {
        $user = $this->makeUser('50.00');
        $flexi = $this->manager->flexi($user);

        try {
            $this->ledger->deposit($user->id, $flexi->id, '100.00');
            $this->fail('expected exception');
        } catch (SavingsException $e) {
            $this->assertSame('INSUFFICIENT_FUNDS', $e->errorCode);
        }

        $this->assertSame('50.00', $this->walletBalance($user));
        $this->assertSame(0, SavingsEntry::count());
        $this->assertSame('0.00', Money::norm($flexi->refresh()->balance));
    }

    public function test_duplicate_idempotency_key_is_not_double_credited(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);

        $a = $this->ledger->deposit($user->id, $flexi->id, '100.00', ['idempotency_key' => 'dup-key-12345']);
        $b = $this->ledger->deposit($user->id, $flexi->id, '100.00', ['idempotency_key' => 'dup-key-12345']);

        $this->assertTrue($b['replayed']);
        $this->assertSame($a['entry']->reference, $b['entry']->reference);
        $this->assertSame('900.00', $this->walletBalance($user));
        $this->assertSame('100.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame(1, SavingsEntry::count());
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '100.00', ['idempotency_key' => 'same-key-12345']);

        $this->expectException(SavingsException::class);
        $this->ledger->deposit($user->id, $flexi->id, '200.00', ['idempotency_key' => 'same-key-12345']);
    }

    public function test_second_withdrawal_exceeding_remaining_balance_is_rejected(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '100.00');

        $this->ledger->withdraw($user->id, $flexi->id, '60.00', ['idempotency_key' => 'wd-key-00001']);
        try {
            $this->ledger->withdraw($user->id, $flexi->id, '60.00', ['idempotency_key' => 'wd-key-00002']);
            $this->fail('expected insufficient funds');
        } catch (SavingsException $e) {
            $this->assertSame('INSUFFICIENT_FUNDS', $e->errorCode);
        }

        $this->assertSame('40.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame('960.00', $this->walletBalance($user));
    }

    public function test_duplicate_withdrawal_request_pays_once(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '100.00');

        $this->ledger->withdraw($user->id, $flexi->id, '30.00', ['idempotency_key' => 'wd-dup-00001']);
        $again = $this->ledger->withdraw($user->id, $flexi->id, '30.00', ['idempotency_key' => 'wd-dup-00001']);

        $this->assertTrue($again['replayed']);
        $this->assertSame('930.00', $this->walletBalance($user));
        $this->assertSame('70.00', Money::norm($flexi->refresh()->balance));
    }

    public function test_transfer_is_atomic_when_destination_rejects(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '500.00');
        $target = $this->manager->createTarget($user, ['title' => 'Phone', 'target_amount' => '1000.00'])['account'];

        try {
            // exceeds remaining target amount -> destination guard throws after the source was debited in-memory
            $this->manager->transfer($user, $flexi->id, $target->id, '1500.00', 'tr-key-000001');
            $this->fail('expected exception');
        } catch (SavingsException $e) {
            $this->assertContains($e->errorCode, ['EXCEEDS_TARGET', 'INSUFFICIENT_FUNDS']);
        }

        $this->assertSame('500.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame('0.00', Money::norm($target->refresh()->balance));

        // A valid transfer posts two balanced legs.
        $r = $this->manager->transfer($user, $flexi->id, $target->id, '200.00', 'tr-key-000002');
        $this->assertSame('300.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame('200.00', Money::norm($target->refresh()->balance));
        $legs = SavingsEntry::where('group_ref', $r['entry']->group_ref)->get();
        $this->assertCount(2, $legs);
        $this->assertSame(0, bccomp(
            (string) $legs->where('direction', 'CREDIT')->sum('amount'),
            (string) $legs->where('direction', 'DEBIT')->sum('amount'), 2
        ));
    }

    public function test_cannot_transfer_to_or_from_another_users_account(): void
    {
        $alice = $this->makeUser('1000.00');
        $bob = $this->makeUser('1000.00');
        $aliceFlexi = $this->manager->flexi($alice);
        $bobFlexi = $this->manager->flexi($bob);
        $this->ledger->deposit($alice->id, $aliceFlexi->id, '500.00');

        $this->expectException(SavingsException::class);
        $this->manager->transfer($alice, $aliceFlexi->id, $bobFlexi->id, '100.00', 'steal-key-001');
    }

    public function test_monetary_precision_is_decimal_safe(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '100.10');
        $this->ledger->deposit($user->id, $flexi->id, '100.20');

        $this->assertSame('200.30', Money::norm($flexi->refresh()->balance));
        $this->assertSame('799.70', $this->walletBalance($user));

        foreach (['100.123', '-5', '0', 'abc', '1e3', ''] as $bad) {
            try {
                Money::parse($bad);
                $this->fail("accepted {$bad}");
            } catch (SavingsException $e) {
                $this->assertSame('INVALID_AMOUNT', $e->errorCode);
            }
        }
        $this->assertSame('100.50', Money::parse(100.5));
    }

    public function test_deposit_reversal_restores_wallet_and_cannot_be_repeated(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $dep = $this->ledger->deposit($user->id, $flexi->id, '100.00')['entry'];

        $reversals = $this->ledger->reverse($dep->id, 'duplicate funding');

        $this->assertCount(1, $reversals);
        $this->assertSame($dep->id, (int) $reversals[0]->reverses_entry_id);
        $this->assertSame('1000.00', $this->walletBalance($user));
        $this->assertSame('0.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame('REVERSED', $dep->refresh()->status);

        $this->expectException(SavingsException::class);
        $this->ledger->reverse($dep->id, 'again');
    }

    public function test_reversal_fails_when_funds_were_already_spent(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $dep = $this->ledger->deposit($user->id, $flexi->id, '100.00')['entry'];
        $this->ledger->withdraw($user->id, $flexi->id, '90.00');

        try {
            $this->ledger->reverse($dep->id, 'x');
            $this->fail('expected failure');
        } catch (SavingsException $e) {
            $this->assertSame('INSUFFICIENT_FUNDS', $e->errorCode);
        }
        $this->assertSame('COMPLETED', $dep->refresh()->status);
    }

    public function test_reconciliation_is_clean_then_detects_tampering(): void
    {
        $user = $this->makeUser('1000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '100.00');
        $this->ledger->withdraw($user->id, $flexi->id, '25.00');

        $maintenance = app(SavingsMaintenance::class);
        $this->assertSame([], $maintenance->reconcile());

        SavingsAccount::whereKey($flexi->id)->update(['balance' => '999.00']);
        $findings = $maintenance->reconcile();
        $this->assertSame('BALANCE_MISMATCH', $findings[0]['type']);
    }

    public function test_state_machine_rejects_invalid_transitions(): void
    {
        $this->expectException(SavingsException::class);
        SavingsStateMachine::account(SavingsAccount::CANCELLED, SavingsAccount::ACTIVE);
    }

    public function test_entry_state_cannot_move_backwards(): void
    {
        $this->expectException(SavingsException::class);
        SavingsStateMachine::entry('COMPLETED', 'PENDING');
    }

    public function test_flexi_interest_is_idempotent_per_day_and_uses_truncation(): void
    {
        config(['savings.flexi.interest_apr_percent' => '36.5']); // 0.1% per day
        $user = $this->makeUser('100000.00');
        $flexi = $this->manager->flexi($user);
        $this->ledger->deposit($user->id, $flexi->id, '1000.00');
        // Make the deposit "old" so it earns interest for the accrual day.
        SavingsEntry::query()->update(['created_at' => now()->subDays(3)]);

        $maintenance = app(SavingsMaintenance::class);
        $day = now(config('savings.scheduler.default_timezone'))->subDay();

        $first = $maintenance->accrueFlexiInterest($day);
        $second = $maintenance->accrueFlexiInterest($day);

        $this->assertSame('1.00', $first['interest']);
        $this->assertSame(0, $second['accounts']);
        $this->assertSame('1001.00', Money::norm($flexi->refresh()->balance));
        $this->assertSame('1.00', Money::norm($flexi->interest_earned));
        $this->assertSame([], $maintenance->reconcile());
    }
}
