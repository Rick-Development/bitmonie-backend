<?php

namespace Tests\Feature;

use App\Models\GraphCustomer;
use App\Models\GraphTransaction;
use App\Models\GraphWallet;
use App\Models\User;
use App\Services\GraphService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GraphUsdControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('graph.webhook_secret', 'graph-test-secret');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        $this->createSchema();

        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_receive_returns_bank_transfer_instructions_in_production(): void
    {
        $this->setAppMode('live');

        $user = $this->makeUser();
        $wallet = $this->makeWallet($user, [
            'wallet_id' => 'wallet-usd-001',
            'account_number' => '9992740150015003',
            'currency' => 'USD',
        ]);

        $mock = Mockery::mock(GraphService::class);
        $mock->shouldReceive('getReceiveInstructions')
            ->once()
            ->with('wallet-usd-001')
            ->andReturn([
                'account_id' => 'wallet-usd-001',
                'account_name' => 'John Doe',
                'account_number' => '9992740150015003',
                'routing_number' => '084106768',
                'bank_name' => 'Evolve Bank and Trust',
                'currency' => 'USD',
                'status' => 'active',
                'type' => 'personal_checking',
                'bank_address' => [
                    'line1' => '6070 Poplar Ave',
                    'city' => 'Memphis',
                    'state' => 'TN',
                    'country' => 'US',
                    'postal_code' => '38119',
                ],
            ]);
        $this->app->instance(GraphService::class, $mock);

        $response = $this->actingAs($user, 'api')->postJson('/api/usd/receive', [
            'amount' => 150,
            'currency' => 'USD',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.wallet_id', $wallet->wallet_id)
            ->assertJsonPath('data.receive_method', 'bank_transfer')
            ->assertJsonPath('data.bank_account.account_number', '9992740150015003')
            ->assertJsonPath('data.bank_account.routing_number', '084106768')
            ->assertJsonPath('data.deposit_address', null);
    }

    public function test_send_accepts_destination_id_alias_and_returns_transaction_id(): void
    {
        $user = $this->makeUser(['email' => 'send@example.com', 'username' => 'send_user']);
        $wallet = $this->makeWallet($user, [
            'wallet_id' => 'wallet-usd-002',
            'currency' => 'USD',
            'balance' => 250.00,
        ]);

        $mock = Mockery::mock(GraphService::class);
        $mock->shouldReceive('updateWalletBalance')
            ->twice()
            ->with('wallet-usd-002')
            ->andReturn($wallet);
        $mock->shouldReceive('createPayout')
            ->once()
            ->with(
                Mockery::type(User::class),
                'wallet-usd-002',
                Mockery::on(function (array $payload) {
                    return ($payload['destination_id'] ?? null) === 'dest-123'
                        && ($payload['amount'] ?? null) === 75.5
                        && ($payload['description'] ?? null) === 'Rent payout';
                })
            )
            ->andReturn([
                'data' => [
                    'id' => 'evt-payout-001',
                    'payout_id' => 'payout-001',
                    'status' => 'processing',
                ],
            ]);
        $this->app->instance(GraphService::class, $mock);

        $response = $this->actingAs($user, 'api')->postJson('/api/usd/send', [
            'amount' => 75.5,
            'destination_id' => 'dest-123',
            'description' => 'Rent payout',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.destination_id', 'dest-123')
            ->assertJsonPath('data.transaction_id', 'payout-001')
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_create_payout_destination_accepts_nip_payload_and_wallet_scope(): void
    {
        $user = $this->makeUser(['email' => 'beneficiary@example.com', 'username' => 'beneficiary_user']);
        $this->makeWallet($user, [
            'wallet_id' => 'wallet-ngn-001',
            'currency' => 'USD',
            'balance' => 1000,
        ]);

        $mock = Mockery::mock(GraphService::class);
        $mock->shouldReceive('createPayoutDestination')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::on(function (array $payload) {
                    return ($payload['wallet_id'] ?? null) === 'wallet-ngn-001'
                        && ($payload['type'] ?? null) === 'nip'
                        && ($payload['details']['bank_code'] ?? null) === '058'
                        && ($payload['details']['account_number'] ?? null) === '0123456789';
                })
            )
            ->andReturn([
                'data' => [
                    'id' => 'dest-ngn-001',
                    'type' => 'nip',
                    'currency' => 'NGN',
                ],
            ]);
        $this->app->instance(GraphService::class, $mock);

        $response = $this->actingAs($user, 'api')->postJson('/api/user/graph/payout-destination', [
            'wallet_id' => 'wallet-ngn-001',
            'type' => 'nip',
            'currency' => 'NGN',
            'details' => [
                'bank_code' => '058',
                'account_number' => '0123456789',
                'beneficiary_name' => 'Jane Doe',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.destination.id', 'dest-ngn-001')
            ->assertJsonPath('data.destination.type', 'nip');
    }

    public function test_graph_webhook_account_credit_normalizes_subunits(): void
    {
        $user = $this->makeUser(['email' => 'webhook-credit@example.com', 'username' => 'webhook_credit']);
        $wallet = $this->makeWallet($user, [
            'wallet_id' => 'wallet-credit-001',
            'currency' => 'USD',
            'balance' => 0,
        ]);

        $payload = [
            'event_type' => 'account.credit',
            'entity' => 'transaction',
            'data' => [
                'id' => 'txn-credit-001',
                'account_id' => 'wallet-credit-001',
                'amount' => 14000,
                'balance_after' => 112900,
                'currency' => 'USD',
                'status' => 'successful',
                'description' => 'Salary deposit',
                'deposit_id' => 'deposit-001',
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, 'graph-test-secret');

        $response = $this->postJson('/api/webhook/graph', $payload, [
            'x-graph-signature' => $signature,
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $wallet->refresh();
        $transaction = GraphTransaction::where('transaction_id', 'txn-credit-001')->first();

        $this->assertNotNull($transaction);
        $this->assertSame('1129.00000000', number_format((float) $wallet->balance, 8, '.', ''));
        $this->assertSame('140.00000000', number_format((float) $transaction->amount, 8, '.', ''));
        $this->assertSame('completed', $transaction->status);
    }

    public function test_graph_webhook_payout_success_updates_status_and_balance(): void
    {
        $user = $this->makeUser(['email' => 'webhook-payout@example.com', 'username' => 'webhook_payout']);
        $wallet = $this->makeWallet($user, [
            'wallet_id' => 'wallet-payout-001',
            'currency' => 'USD',
            'balance' => 103.93,
        ]);

        GraphTransaction::create([
            'user_id' => $user->id,
            'graph_wallet_id' => $wallet->id,
            'transaction_id' => 'payout-xyz-001',
            'type' => 'withdrawal',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'pending',
            'reference' => 'USD_SEND_REF',
            'description' => 'USD payout',
            'metadata' => [],
        ]);

        $payload = [
            'event_type' => 'payout.success',
            'entity' => 'transaction',
            'data' => [
                'id' => 'evt-payout-success-001',
                'payout_id' => 'payout-xyz-001',
                'account_id' => 'wallet-payout-001',
                'balance_after' => 8393,
                'status' => 'successful',
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, 'graph-test-secret');

        $response = $this->postJson('/api/webhook/graph', $payload, [
            'x-graph-signature' => $signature,
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame('completed', GraphTransaction::where('transaction_id', 'payout-xyz-001')->value('status'));
        $this->assertSame('83.93000000', number_format((float) $wallet->fresh()->balance, 8, '.', ''));
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('mobile')->nullable();
            $table->string('full_mobile')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('kyc_verified')->default(true);
            $table->text('address')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('graph_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('graph_id')->unique();
            $table->string('kyc_status')->default('pending');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('graph_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('graph_customer_id');
            $table->string('wallet_id')->unique();
            $table->string('account_number')->nullable();
            $table->string('currency')->default('USD');
            $table->decimal('balance', 28, 8)->default(0);
            $table->string('status')->default('active');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('graph_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('graph_wallet_id')->nullable();
            $table->string('transaction_id')->unique();
            $table->string('type')->default('deposit');
            $table->decimal('amount', 28, 8);
            $table->string('currency', 10);
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => 'user_' . uniqid(),
            'email' => 'user_' . uniqid() . '@example.com',
            'password' => 'password',
            'mobile' => '08012345678',
            'full_mobile' => '2348012345678',
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => true,
        ], $overrides));
    }

    protected function makeWallet(User $user, array $overrides = []): GraphWallet
    {
        $customer = GraphCustomer::create([
            'user_id' => $user->id,
            'graph_id' => 'graph_' . uniqid(),
            'kyc_status' => 'verified',
            'data' => [],
        ]);

        return GraphWallet::create(array_merge([
            'user_id' => $user->id,
            'graph_customer_id' => $customer->id,
            'wallet_id' => 'wallet_' . uniqid(),
            'account_number' => '1234567890',
            'currency' => 'USD',
            'balance' => 0,
            'status' => 'active',
            'data' => [],
        ], $overrides));
    }

    protected function setAppMode(string $mode): void
    {
        putenv("APP_MODE={$mode}");
        $_ENV['APP_MODE'] = $mode;
        $_SERVER['APP_MODE'] = $mode;
    }
}
