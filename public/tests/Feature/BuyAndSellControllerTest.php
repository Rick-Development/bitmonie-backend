<?php

namespace Tests\Feature;

use App\Jobs\ProcessRampSell;
use App\Models\Bank;
use App\Models\RampTransaction;
use App\Models\User;
use App\Models\VirtualAccounts;
use App\Services\QuidaxRampService;
use App\Services\QuidaxService;
use App\Services\SafeHavenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class BuyAndSellControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        $this->createTestSchema();

        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_confirm_on_ramp_returns_structured_success_without_reading_status_code(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $this->createSafeHavenAccount($user);
        Bank::create([
            'name' => 'Sterling Bank',
            'code' => '232',
            'slug' => 'sterling-bank',
            'is_active' => true,
        ]);

        $transaction = RampTransaction::create([
            'user_id' => $user->id,
            'merchant_reference' => 'ONRAMP_TEST_001',
            'type' => 'on_ramp',
            'from_currency' => 'ngn',
            'to_currency' => 'usdt',
            'from_amount' => '3000',
            'network' => 'bep20',
            'wallet_address' => '0xabc',
            'status' => 'pending',
        ]);

        $this->mockRampService(function ($mock) use ($transaction) {
            $mock->shouldReceive('confirmOnRamp')
                ->once()
                ->with($transaction->merchant_reference)
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Confirmed',
                    'data' => [
                        'public_id' => 'bank-public-id',
                        'account_name' => 'Quidax Settlement',
                        'account_number' => '5284883357',
                        'bank_name' => 'Sterling Bank',
                        'bank_code' => '232',
                        'reference' => 'BANK_ACCOUNT_REF',
                        'amount' => '3000.0',
                        'amount_expected' => '3080.63',
                        'processor_fee' => '75.0',
                        'vat' => '5.63',
                    ],
                    'http_status' => 200,
                ]);
        });

        $this->mockSafeHavenService(function ($mock) {
            $mock->shouldReceive('nameEnquiry')
                ->once()
                ->with('232', '5284883357')
                ->andReturn(['sessionId' => 'session-123']);
            $mock->shouldReceive('transfer')
                ->once()
                ->andReturn([
                    'paymentReference' => 'transfer-ref-123',
                    'status' => 'accepted',
                ]);
        });

        $this->mockQuidaxService();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/user/buy-sell/on-ramp/{$transaction->merchant_reference}/confirm");

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_account.account_number', '5284883357')
            ->assertJsonPath('data.transfer.session_id', 'session-123')
            ->assertJsonPath('data.transfer.details.paymentReference', 'transfer-ref-123');

        $this->assertSame('confirmed', $transaction->fresh()->status);
    }

    public function test_confirm_on_ramp_is_scoped_to_the_authenticated_user(): void
    {
        $owner = $this->makeUser(['email' => 'owner@example.com']);
        $otherUser = $this->makeUser(['email' => 'other@example.com']);

        RampTransaction::create([
            'user_id' => $otherUser->id,
            'merchant_reference' => 'ONRAMP_TEST_002',
            'type' => 'on_ramp',
            'from_currency' => 'ngn',
            'to_currency' => 'usdt',
            'from_amount' => '3000',
            'status' => 'pending',
        ]);

        $this->mockRampService();
        $this->mockSafeHavenService();
        $this->mockQuidaxService();

        $response = $this->actingAs($owner, 'api')
            ->postJson('/api/user/buy-sell/on-ramp/ONRAMP_TEST_002/confirm');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Transaction not found locally.');
    }

    public function test_initiate_off_ramp_persists_transaction_even_if_default_bank_attachment_fails(): void
    {
        $user = $this->makeUser();
        $this->createSafeHavenAccount($user, [
            'account_number' => '1234567890',
            'bank_code' => '090286',
        ]);

        $this->mockRampService(function ($mock) {
            $mock->shouldReceive('initiateOffRamp')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Initiated',
                    'data' => [
                        'public_id' => 'off-public-id',
                        'reference' => 'OFF-REF-1',
                        'from_currency' => 'usdt',
                        'to_currency' => 'ngn',
                        'from_amount' => '15',
                        'to_amount' => '23000',
                    ],
                    'http_status' => 200,
                ]);
            $mock->shouldReceive('addBankAccountOffRamp')
                ->once()
                ->andReturn([
                    'ok' => false,
                    'status' => 'bad_request',
                    'message' => 'Automatic bank attachment failed',
                    'data' => ['code' => 'bank_attach_failed'],
                    'http_status' => 400,
                ]);
        });

        $this->mockSafeHavenService();
        $this->mockQuidaxService();

        $response = $this->actingAs($user, 'api')->postJson('/api/user/buy-sell/off-ramp/initiate', [
            'from_currency' => 'USDT',
            'to_currency' => 'NGN',
            'from_amount' => 15,
            'network' => 'BEP20',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.bank_account_setup.attached', false)
            ->assertJsonPath('data.bank_account_setup.message', 'Automatic bank attachment failed');

        $this->assertSame(1, RampTransaction::count());
        $this->assertSame('pending', RampTransaction::first()->status);
    }

    public function test_initiate_off_ramp_uses_safehaven_account_name_for_customer_payload(): void
    {
        $user = $this->makeUser([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $this->createSafeHavenAccount($user, [
            'account_number' => '1234567890',
            'bank_code' => '090286',
            'account_name' => 'John Michael Doe',
        ]);

        $this->mockRampService(function ($mock) {
            $mock->shouldReceive('initiateOffRamp')
                ->once()
                ->with(Mockery::on(function (array $payload) {
                    return ($payload['customer']['email'] ?? null) !== null
                        && ($payload['customer']['first_name'] ?? null) === 'John'
                        && ($payload['customer']['last_name'] ?? null) === 'Michael Doe';
                }))
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Initiated',
                    'data' => [
                        'public_id' => 'off-public-id',
                        'reference' => 'OFF-REF-LEGAL-NAME',
                        'from_currency' => 'usdt',
                        'to_currency' => 'ngn',
                        'from_amount' => '15',
                        'to_amount' => '23000',
                    ],
                    'http_status' => 200,
                ]);
            $mock->shouldReceive('addBankAccountOffRamp')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Bank account attached',
                    'data' => [
                        'bank_code' => '090286',
                        'account_number' => '1234567890',
                    ],
                    'http_status' => 200,
                ]);
        });

        $this->mockSafeHavenService();
        $this->mockQuidaxService();

        $response = $this->actingAs($user, 'api')->postJson('/api/user/buy-sell/off-ramp/initiate', [
            'from_currency' => 'USDT',
            'to_currency' => 'NGN',
            'from_amount' => 15,
            'network' => 'BEP20',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.bank_account_setup.attached', true)
            ->assertJsonPath('data.payout_account.account_name', 'John Michael Doe');
    }

    public function test_initiate_off_ramp_returns_clear_name_mismatch_feedback_when_provider_rejects_payout_account(): void
    {
        $user = $this->makeUser([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $this->createSafeHavenAccount($user, [
            'account_number' => '1234567890',
            'bank_code' => '090286',
            'account_name' => 'John Michael Doe',
        ]);

        $this->mockRampService(function ($mock) {
            $mock->shouldReceive('initiateOffRamp')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Initiated',
                    'data' => [
                        'public_id' => 'off-public-id',
                        'reference' => 'OFF-REF-NAME-MISMATCH',
                        'from_currency' => 'usdt',
                        'to_currency' => 'ngn',
                        'from_amount' => '15',
                        'to_amount' => '23000',
                    ],
                    'http_status' => 200,
                ]);
            $mock->shouldReceive('addBankAccountOffRamp')
                ->once()
                ->andReturn([
                    'ok' => false,
                    'status' => 'bad_request',
                    'message' => 'name does not match',
                    'data' => ['provider_code' => 'name_mismatch'],
                    'http_status' => 400,
                ]);
        });

        $this->mockSafeHavenService();
        $this->mockQuidaxService();

        $response = $this->actingAs($user, 'api')->postJson('/api/user/buy-sell/off-ramp/initiate', [
            'from_currency' => 'USDT',
            'to_currency' => 'NGN',
            'from_amount' => 15,
            'network' => 'BEP20',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.bank_account_setup.attached', false)
            ->assertJsonPath('data.bank_account_setup.message', 'The payout bank account name does not match the verified Bitmonie profile for this sell order.')
            ->assertJsonPath('data.bank_account_setup.details.error_code', 'off_ramp_name_mismatch');
    }

    public function test_initiate_on_ramp_uses_same_normalized_customer_payload(): void
    {
        $user = $this->makeUser([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $this->createSafeHavenAccount($user, [
            'account_name' => 'John Michael Doe',
        ]);

        $this->mockRampService(function ($mock) {
            $mock->shouldReceive('initiateOnRamp')
                ->once()
                ->with(Mockery::on(function (array $payload) {
                    return ($payload['customer']['email'] ?? null) !== null
                        && ($payload['customer']['first_name'] ?? null) === 'John'
                        && ($payload['customer']['last_name'] ?? null) === 'Michael Doe';
                }))
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Initiated',
                    'data' => [
                        'public_id' => 'on-public-id',
                        'reference' => 'ON-REF-LEGAL-NAME',
                        'from_currency' => 'ngn',
                        'to_currency' => 'usdt',
                        'from_amount' => '50000',
                        'to_amount' => '31.75',
                    ],
                    'http_status' => 200,
                ]);
        });

        $this->mockSafeHavenService();
        $this->mockQuidaxService();

        $response = $this->actingAs($user, 'api')->postJson('/api/user/buy-sell/on-ramp/initiate', [
            'from_currency' => 'NGN',
            'to_currency' => 'USDT',
            'from_amount' => 50000,
            'network' => 'BEP20',
            'wallet_address' => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.trade.to_currency', 'USDT');
    }

    public function test_confirm_off_ramp_reverts_transaction_to_pending_when_wallet_lookup_fails(): void
    {
        $user = $this->makeUser();

        $transaction = RampTransaction::create([
            'user_id' => $user->id,
            'merchant_reference' => 'OFFRAMP_TEST_003',
            'type' => 'off_ramp',
            'from_currency' => 'usdt',
            'to_currency' => 'ngn',
            'from_amount' => '15',
            'network' => 'bep20',
            'bank_code' => '090286',
            'account_number' => '1234567890',
            'status' => 'pending',
        ]);

        $this->mockRampService(function ($mock) use ($transaction) {
            $mock->shouldReceive('confirmOffRamp')
                ->once()
                ->with($transaction->merchant_reference)
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Confirmed',
                    'data' => [
                        'id' => 'deposit-id',
                        'address' => '0xRampAddress',
                        'network' => 'bep20',
                        'currency' => 'usdt',
                        'status' => 'assigned',
                    ],
                    'http_status' => 200,
                ]);
        });

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'error',
                    'message' => 'Wallet unavailable',
                ]);
        });

        $this->mockSafeHavenService();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/user/buy-sell/off-ramp/{$transaction->merchant_reference}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Wallet unavailable');

        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_confirm_off_ramp_dispatches_job_with_main_account_id(): void
    {
        Queue::fake();
        Notification::fake();

        $user = $this->makeUser();

        $transaction = RampTransaction::create([
            'user_id' => $user->id,
            'merchant_reference' => 'OFFRAMP_TEST_004',
            'type' => 'off_ramp',
            'from_currency' => 'usdt',
            'to_currency' => 'ngn',
            'from_amount' => '15',
            'network' => 'bep20',
            'bank_code' => '090286',
            'account_number' => '1234567890',
            'status' => 'pending',
        ]);

        $this->mockRampService(function ($mock) use ($transaction) {
            $mock->shouldReceive('confirmOffRamp')
                ->once()
                ->with($transaction->merchant_reference)
                ->andReturn([
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Confirmed',
                    'data' => [
                        'id' => 'deposit-id',
                        'address' => '0xRampAddress',
                        'network' => 'bep20',
                        'currency' => 'usdt',
                        'status' => 'assigned',
                    ],
                    'http_status' => 200,
                ]);
        });

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'data' => ['balance' => '100'],
                ]);
            $mock->shouldReceive('getWithdrawalFee')
                ->once()
                ->with('usdt', 'bep20')
                ->andReturn([
                    'status' => 'success',
                    'data' => ['fee' => 1, 'type' => 'flat'],
                ]);
            $mock->shouldReceive('getUser')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'data' => ['id' => 'main-account-id'],
                ]);
        });

        $this->mockSafeHavenService();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/user/buy-sell/off-ramp/{$transaction->merchant_reference}/confirm");

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.deposit_address.address', '0xRampAddress');

        Queue::assertPushed(ProcessRampSell::class, function (ProcessRampSell $job) use ($transaction) {
            return $job->merchantReference === $transaction->merchant_reference
                && ($job->mainAccountData['fund_uid'] ?? null) === 'main-account-id';
        });

        $this->assertSame('processing', $transaction->fresh()->status);
    }

    protected function mockRampService(?callable $expectations = null): void
    {
        $mock = Mockery::mock(QuidaxRampService::class);
        $mock->shouldIgnoreMissing();

        if ($expectations) {
            $expectations($mock);
        }

        $this->app->instance(QuidaxRampService::class, $mock);
    }

    protected function mockQuidaxService(?callable $expectations = null): void
    {
        $mock = Mockery::mock(QuidaxService::class);
        $mock->shouldIgnoreMissing();

        if ($expectations) {
            $expectations($mock);
        }

        $this->app->instance(QuidaxService::class, $mock);
    }

    protected function mockSafeHavenService(?callable $expectations = null): void
    {
        $mock = Mockery::mock(SafeHavenService::class);
        $mock->shouldIgnoreMissing();

        if ($expectations) {
            $expectations($mock);
        }

        $this->app->instance(SafeHavenService::class, $mock);
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'user' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'quidax_id' => 'quidax-' . Str::random(6),
            'status' => 1,
            'email_verified' => 1,
            'kyc_verified' => 1,
        ], $attributes));
    }

    protected function createSafeHavenAccount(User $user, array $attributes = []): VirtualAccounts
    {
        return VirtualAccounts::create(array_merge([
            'user_id' => $user->id,
            'account_number' => '1000000000',
            'account_name' => 'Test User',
            'bank_name' => 'SafeHaven Microfinance Bank',
            'bank_code' => '090286',
            'provider' => 'safehaven',
        ], $attributes));
    }

    protected function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('quidax_id')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('kyc_verified')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('provider')->nullable();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code')->nullable();
            $table->decimal('balance', 28, 8)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('ramp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('merchant_reference')->unique();
            $table->string('public_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('from_currency')->nullable();
            $table->string('to_currency')->nullable();
            $table->decimal('from_amount', 16, 8)->nullable();
            $table->decimal('to_amount', 16, 8)->nullable();
            $table->string('network')->nullable();
            $table->string('wallet_address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('blockchain_fee', 16, 8)->nullable();
            $table->decimal('processor_fee', 16, 8)->nullable();
            $table->decimal('stamp_charge', 16, 8)->nullable();
            $table->decimal('vat', 16, 8)->nullable();
            $table->string('transfer_session_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
