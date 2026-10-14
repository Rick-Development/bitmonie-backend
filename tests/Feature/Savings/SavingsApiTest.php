<?php

namespace Tests\Feature\Savings;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\Money;

class SavingsApiTest extends SavingsTestCase
{
    public function test_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/savings/flexi')->assertStatus(401);
        $this->postJson('/api/v1/savings/flexi/deposit', ['amount' => '100.00'])->assertStatus(401);
        $this->getJson('/api/v1/savings/auto')->assertStatus(401);
        $this->getJson('/api/v1/savings/targets')->assertStatus(401);
    }

    public function test_flexi_deposit_and_withdrawal_api_flow(): void
    {
        $user = $this->makeUser('5000.00');

        // Deposit
        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/flexi/deposit', [
            'amount' => '500.00',
        ], ['Idempotency-Key' => 'flex-dep-api-01']);

        $res->assertStatus(201)
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.transaction.amount', '500.00')
            ->assertJsonPath('data.replayed', false);

        $this->assertSame('4500.00', $this->walletBalance($user));

        // Get Flexi details
        $get = $this->actingAs($user, 'api')->getJson('/api/v1/savings/flexi');
        $get->assertStatus(200)
            ->assertJsonPath('data.balance', '500.00')
            ->assertJsonPath('data.available_balance', '500.00');

        // Withdraw
        $wdr = $this->actingAs($user, 'api')->postJson('/api/v1/savings/flexi/withdraw', [
            'amount' => '200.00',
        ], ['Idempotency-Key' => 'flex-wdr-api-01']);

        $wdr->assertStatus(200)
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.transaction.amount', '200.00');

        $this->assertSame('4700.00', $this->walletBalance($user));

        // Transactions list
        $tx = $this->actingAs($user, 'api')->getJson('/api/v1/savings/flexi/transactions');
        $tx->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_reject_access_to_another_users_savings(): void
    {
        $alice = $this->makeUser('5000.00');
        $bob = $this->makeUser('5000.00');

        $target = app(\App\Services\Savings\SavingsManager::class)->createTarget($alice, [
            'title' => 'Alice Private Target',
            'target_amount' => '2000.00',
        ])['account'];

        // Bob tries to access Alice's target
        $res = $this->actingAs($bob, 'api')->getJson("/api/v1/savings/targets/{$target->id}");
        $res->assertStatus(404)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('data.code', 'NOT_FOUND');

        // Bob tries to contribute to Alice's target
        $res2 = $this->actingAs($bob, 'api')->postJson("/api/v1/savings/targets/{$target->id}/contribute", [
            'amount' => '100.00',
        ], ['Idempotency-Key' => 'bob-steal-attempt-01']);

        $res2->assertStatus(404)
            ->assertJsonPath('type', 'error');
    }

    public function test_idempotency_key_requirement_on_money_endpoints(): void
    {
        $user = $this->makeUser('5000.00');

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/flexi/deposit', [
            'amount' => '500.00',
        ]); // missing Idempotency-Key header

        $res->assertStatus(422)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('data.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }
}
