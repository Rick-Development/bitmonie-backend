<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Admin\Currency;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedger;
use App\Models\WalletWithdrawalRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralWalletControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('referral_wallet.currency', 'NGN');
        Config::set('referral_wallet.earning_currency', 'USD');
        Config::set('referral_wallet.naira_exchange_rate', 1500);
        Config::set('referral_wallet.min_withdrawal_amount', 100);
        Config::set('referral_wallet.max_withdrawal_amount', 5000);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        $this->createSchema();

        $this->withoutMiddleware();
    }

    public function test_user_can_view_balance_convert_earnings_and_request_withdrawal(): void
    {
        $user = $this->makeUser();
        $this->makeCurrency();
        $wallet = $this->makeWallet($user, '1000.00000000', '0.00000000');

        WalletLedger::create([
            'user_id' => $user->id,
            'amount' => '1000.00000000',
            'currency_code' => 'NGN',
            'transaction_type' => 'earn',
            'status' => 'completed',
            'balance_before' => '0.00000000',
            'balance_after' => '1000.00000000',
            'pending_before' => '0.00000000',
            'pending_after' => '0.00000000',
            'reference' => 'REF-EARN-001',
            'description' => 'Referral bonus',
            'transaction_at' => now(),
        ]);

        $balanceResponse = $this->actingAs($user, 'api')->getJson('/api/user/referral/balance');

        $balanceResponse
            ->assertOk()
            ->assertJsonPath('data.total_earned', 1000)
            ->assertJsonPath('data.available_for_withdrawal', 1000)
            ->assertJsonPath('data.pending_withdrawal', 0);

        $conversionResponse = $this->actingAs($user, 'api')->postJson('/api/user/referral/convert', [
            'amount' => 2,
        ]);

        $conversionResponse
            ->assertOk()
            ->assertJsonPath('data.exchange_rate', 1500)
            ->assertJsonPath('data.converted_amount', 3000);

        $withdrawalResponse = $this->actingAs($user, 'api')->postJson('/api/user/referral/withdraw', [
            'amount' => 400,
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
        ]);

        $withdrawalResponse
            ->assertOk()
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonPath('data.wallet.available_for_withdrawal', 600)
            ->assertJsonPath('data.wallet.pending_withdrawal', 400);

        $wallet->refresh();
        $this->assertSame('600.00000000', $wallet->balance);
        $this->assertSame('400.00000000', $wallet->reserved);
        $this->assertDatabaseCount('wallet_withdrawal_requests', 1);
    }

    public function test_admin_can_approve_pending_referral_withdrawal(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->makeCurrency();
        $wallet = $this->makeWallet($user, '600.00000000', '400.00000000');

        WalletLedger::create([
            'user_id' => $user->id,
            'amount' => '1000.00000000',
            'currency_code' => 'NGN',
            'transaction_type' => 'earn',
            'status' => 'completed',
            'balance_before' => '0.00000000',
            'balance_after' => '1000.00000000',
            'pending_before' => '0.00000000',
            'pending_after' => '0.00000000',
            'reference' => 'REF-EARN-002',
            'description' => 'Referral bonus',
            'transaction_at' => now()->subMinute(),
        ]);

        $pendingLedger = WalletLedger::create([
            'user_id' => $user->id,
            'amount' => '400.00000000',
            'currency_code' => 'NGN',
            'transaction_type' => 'withdraw',
            'status' => 'pending',
            'balance_before' => '1000.00000000',
            'balance_after' => '600.00000000',
            'pending_before' => '0.00000000',
            'pending_after' => '400.00000000',
            'reference' => 'RWD-APPROVE-001',
            'description' => 'Withdrawal request submitted',
            'transaction_at' => now(),
        ]);

        $withdrawalRequest = WalletWithdrawalRequest::create([
            'user_id' => $user->id,
            'wallet_ledger_id' => $pendingLedger->id,
            'reference' => 'RWD-APPROVE-001',
            'amount' => '400.00000000',
            'currency_code' => 'NGN',
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson('/admin/referral-wallet/withdrawals/' . $withdrawalRequest->id . '/approve', [
            'admin_note' => 'Paid to beneficiary manually',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $wallet->refresh();
        $withdrawalRequest->refresh();
        $pendingLedger->refresh();

        $this->assertSame('600.00000000', $wallet->balance);
        $this->assertSame('0.00000000', $wallet->reserved);
        $this->assertSame('completed', $withdrawalRequest->status);
        $this->assertSame('completed', $pendingLedger->status);
    }

    public function test_admin_can_reject_pending_referral_withdrawal_and_refund_balance(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->makeCurrency();
        $wallet = $this->makeWallet($user, '600.00000000', '400.00000000');

        WalletLedger::create([
            'user_id' => $user->id,
            'amount' => '1000.00000000',
            'currency_code' => 'NGN',
            'transaction_type' => 'earn',
            'status' => 'completed',
            'balance_before' => '0.00000000',
            'balance_after' => '1000.00000000',
            'pending_before' => '0.00000000',
            'pending_after' => '0.00000000',
            'reference' => 'REF-EARN-003',
            'description' => 'Referral bonus',
            'transaction_at' => now()->subMinute(),
        ]);

        $pendingLedger = WalletLedger::create([
            'user_id' => $user->id,
            'amount' => '400.00000000',
            'currency_code' => 'NGN',
            'transaction_type' => 'withdraw',
            'status' => 'pending',
            'balance_before' => '1000.00000000',
            'balance_after' => '600.00000000',
            'pending_before' => '0.00000000',
            'pending_after' => '400.00000000',
            'reference' => 'RWD-REJECT-001',
            'description' => 'Withdrawal request submitted',
            'transaction_at' => now(),
        ]);

        $withdrawalRequest = WalletWithdrawalRequest::create([
            'user_id' => $user->id,
            'wallet_ledger_id' => $pendingLedger->id,
            'reference' => 'RWD-REJECT-001',
            'amount' => '400.00000000',
            'currency_code' => 'NGN',
            'bank_name' => 'Test Bank',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson('/admin/referral-wallet/withdrawals/' . $withdrawalRequest->id . '/reject', [
            'admin_note' => 'Account details mismatch',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $wallet->refresh();
        $withdrawalRequest->refresh();
        $pendingLedger->refresh();

        $this->assertSame('1000.00000000', $wallet->balance);
        $this->assertSame('0.00000000', $wallet->reserved);
        $this->assertSame('rejected', $withdrawalRequest->status);
        $this->assertSame('rejected', $pendingLedger->status);
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->string('referral_code')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('kyc_verified')->default(1);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_has_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('name')->nullable();
            $table->string('code')->unique();
            $table->string('symbol')->nullable();
            $table->decimal('rate', 28, 8)->default(1);
            $table->unsignedTinyInteger('sender')->default(1);
            $table->unsignedTinyInteger('receiver')->default(1);
            $table->unsignedTinyInteger('default')->default(1);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('currency_id');
            $table->uuid()->nullable();
            $table->string('currency_code')->nullable();
            $table->string('quote_currency_code')->nullable();
            $table->decimal('balance', 28, 8)->default(0);
            $table->decimal('reserved', 20, 8)->default(0);
            $table->boolean('status')->default(true);
            $table->unsignedTinyInteger('default')->default(1);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id')->nullable();
            $table->unsignedBigInteger('referred_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('referral_id')->nullable();
            $table->decimal('amount', 28, 8);
            $table->string('currency_code', 10)->default('NGN');
            $table->string('transaction_type');
            $table->string('status')->default('completed');
            $table->decimal('balance_before', 28, 8)->default(0);
            $table->decimal('balance_after', 28, 8)->default(0);
            $table->decimal('pending_before', 28, 8)->default(0);
            $table->decimal('pending_after', 28, 8)->default(0);
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('wallet_ledger_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reference')->unique();
            $table->decimal('amount', 28, 8);
            $table->string('currency_code', 10)->default('NGN');
            $table->string('bank_name');
            $table->string('account_number', 30);
            $table->string('account_name');
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_wallet_id');
            $table->string('type');
            $table->decimal('amount', 36, 2);
            $table->decimal('balance_after', 36, 2);
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function makeUser(): User
    {
        return User::create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => 'john' . random_int(100, 999),
            'email' => 'john' . random_int(100, 999) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'kyc_verified' => 1,
        ]);
    }

    protected function makeAdmin(): Admin
    {
        return Admin::create([
            'firstname' => 'Admin',
            'lastname' => 'User',
            'username' => 'admin' . random_int(100, 999),
            'email' => 'admin' . random_int(100, 999) . '@example.com',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
    }

    protected function makeCurrency(): Currency
    {
        return Currency::create([
            'name' => 'Nigerian Naira',
            'code' => 'NGN',
            'symbol' => 'N',
            'rate' => 1,
            'sender' => 1,
            'receiver' => 1,
            'default' => 1,
            'status' => 1,
        ]);
    }

    protected function makeWallet(User $user, string $balance, string $reserved): UserWallet
    {
        $currency = Currency::where('code', 'NGN')->firstOrFail();

        return UserWallet::create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'currency_code' => 'NGN',
            'balance' => $balance,
            'reserved' => $reserved,
            'status' => 1,
        ]);
    }
}
