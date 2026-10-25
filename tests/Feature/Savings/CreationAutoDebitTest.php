<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsException;
use App\Services\Savings\SavingsManager;

class CreationAutoDebitTest extends SavingsTestCase
{
    private function autoPayload(array $extra = []): array
    {
        return $extra + [
            'title' => 'Weekly', 'amount' => '500.00', 'frequency' => 'weekly',
            'day_of_week' => 1, 'run_time' => '10:00',
        ];
    }

    public function test_target_creation_with_initial_deposit_debits_wallet(): void
    {
        $user = $this->makeUser('5000.00');

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/targets', [
            'title' => 'Trip', 'target_amount' => '10000.00', 'initial_deposit' => '1000.00',
        ], ['Idempotency-Key' => 'target-create-0001']);

        $res->assertStatus(201)->assertJsonPath('data.balance', '1000.00');
        $this->assertSame('4000.00', $this->walletBalance($user));
    }

    public function test_target_creation_without_deposit_does_not_debit(): void
    {
        $user = $this->makeUser('5000.00');
        $r = app(SavingsManager::class)->createTarget($user, ['title' => 'Empty', 'target_amount' => '1000.00']);

        $this->assertSame('0.00', Money::norm($r['account']->balance));
        $this->assertSame('5000.00', $this->walletBalance($user));
    }

    public function test_target_creation_with_insufficient_funds_rolls_back(): void
    {
        $user = $this->makeUser('100.00');

        try {
            app(SavingsManager::class)->createTarget($user, [
                'title' => 'Big', 'target_amount' => '10000.00', 'initial_deposit' => '1000.00',
            ]);
            $this->fail('Expected insufficient funds');
        } catch (SavingsException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertSame('100.00', $this->walletBalance($user));
        $this->assertSame(0, SavingsAccount::where('user_id', $user->id)->where('product', 'TARGET')->count());
    }

    public function test_autosave_creation_with_initial_deposit_debits_wallet(): void
    {
        $user = $this->makeUser('5000.00');

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/auto', $this->autoPayload(['initial_deposit' => '500.00']), [
            'Idempotency-Key' => 'auto-create-0001',
        ]);

        $res->assertStatus(201)->assertJsonPath('data.account.balance', '500.00');
        $this->assertSame('4500.00', $this->walletBalance($user));
    }

    public function test_flexi_deposit_debits_wallet_and_credits_balance(): void
    {
        $user = $this->makeUser('5000.00');
        app(SavingsManager::class)->depositFlexi($user, '1500.00', 'flexi-dep-key-01');

        $this->assertSame('3500.00', $this->walletBalance($user));
        $this->assertSame('1500.00', Money::norm(app(SavingsManager::class)->flexi($user)->balance));
    }
}
