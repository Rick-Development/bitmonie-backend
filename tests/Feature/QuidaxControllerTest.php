<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\QuidaxWebhookController;
use App\Jobs\ProcessQuidaxWithdrawal;
use App\Models\RampTransaction;
use App\Models\User;
use App\Services\QuidaxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class QuidaxControllerTest extends TestCase
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
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_withdrawal_returns_pending_response_after_preflight_validation(): void
    {
        Queue::fake();

        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('getWithdrawalFee')
                ->once()
                ->with('usdt', 'bep20')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'fee' => 1,
                        'type' => 'flat',
                    ],
                ]);

            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'balance' => '50.5236',
                        'locked' => '0.0',
                    ],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'id' => 'quidax-main-account',
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/quidax/create-withdrawal', [
            'currency' => 'USDT',
            'network' => 'BEP20',
            'amount' => 10,
            'fund_uid' => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('type', 'pending')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.currency', 'usdt')
            ->assertJsonPath('data.network', 'bep20')
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('data.fee', 2)
            ->assertJsonPath('data.total', 12);

        Queue::assertPushed(ProcessQuidaxWithdrawal::class, 1);
    }

    public function test_create_withdrawal_rejects_when_available_balance_is_insufficient(): void
    {
        Queue::fake();

        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('getWithdrawalFee')
                ->once()
                ->with('usdt', 'bep20')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'fee' => 1,
                        'type' => 'flat',
                    ],
                ]);

            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'balance' => '5',
                        'locked' => '0.0',
                    ],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'id' => 'quidax-main-account',
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/quidax/create-withdrawal', [
            'currency' => 'USDT',
            'network' => 'BEP20',
            'amount' => 10,
            'fund_uid' => '0x1234567890abcdef1234567890abcdef12345678',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('data.available_balance', 5)
            ->assertSeeText('Insufficient available balance');

        Queue::assertNothingPushed();
    }

    public function test_create_withdrawal_normalizes_evm_network_label_and_missing_address_prefix(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $addressWithoutPrefix = '3AB94980D8f87512dC6252D3C8DA8B1234567890';

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('getWithdrawalFee')
                ->once()
                ->with('eth', 'base')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'fee' => '0.001',
                        'type' => 'flat',
                    ],
                ]);

            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'eth')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'currency' => 'eth',
                        'balance' => '1',
                        'locked' => '0',
                        'is_crypto' => true,
                        'blockchain_enabled' => true,
                        'networks' => [
                            [
                                'id' => 'base',
                                'name' => 'Base Network',
                                'withdraws_enabled' => true,
                            ],
                        ],
                    ],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'id' => 'quidax-main-account',
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/quidax/create-withdrawal', [
            'currency' => 'ETH',
            'network' => 'Base Network',
            'amount' => '0.1',
            'fund_uid' => $addressWithoutPrefix,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.network', 'base');

        Queue::assertPushed(ProcessQuidaxWithdrawal::class, function (ProcessQuidaxWithdrawal $job) use ($addressWithoutPrefix) {
            $destinationData = $this->getProtectedProperty($job, 'destinationData');

            return ($destinationData['network'] ?? null) === 'base'
                && ($destinationData['fund_uid'] ?? null) === '0x' . $addressWithoutPrefix;
        });
    }

    public function test_create_withdrawal_rejects_invalid_evm_address_before_provider_calls(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) {
            $mock->shouldReceive('getWithdrawalFee')->never();
            $mock->shouldReceive('fetchUserWallet')->never();
            $mock->shouldReceive('getUser')->never();
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/quidax/create-withdrawal', [
            'currency' => 'ETH',
            'network' => 'Base Network',
            'amount' => '0.1',
            'fund_uid' => '3AB94980D8f87512dC6252D3C8DA8B',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('type', 'error')
            ->assertSeeText('Invalid wallet address');

        Queue::assertNothingPushed();
    }

    public function test_fetch_user_wallet_returns_provider_error_when_wallet_lookup_fails(): void
    {
        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'error',
                    'message' => 'Wallet unavailable',
                ]);
        });

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/user/quidax/fetch-user-wallet?currency=usdt');

        $response->assertStatus(400)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Wallet unavailable');
    }

    public function test_fetch_user_wallet_subtracts_pending_outgoing_reservations_from_balance(): void
    {
        $user = $this->makeUser();

        \App\Models\Withdrawals::create([
            'user_id' => $user->id,
            'reference' => 'send-ref-1',
            'type' => 'coin_address',
            'currency' => 'usdt',
            'amount' => '5',
            'fee' => '2',
            'total' => '7',
            'trans_id' => 'pending:send-ref-1',
            'wallet' => [
                'status' => 'processing',
                'reservation_type' => 'crypto_withdrawal',
            ],
        ]);

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        'currency' => 'usdt',
                        'balance' => '59',
                        'locked' => '0',
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/user/quidax/fetch-user-wallet?currency=usdt');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider_balance', '59')
            ->assertJsonPath('data.0.reserved_outgoing_balance', '7')
            ->assertJsonPath('data.0.balance', '52')
            ->assertJsonPath('data.0.available_balance', '52')
            ->assertJsonPath('data.0.locked_balance', '7');
    }

    public function test_fetch_user_wallets_normalizes_numbered_assets_to_plain_list_items(): void
    {
        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallets')
                ->once()
                ->with($user->quidax_id)
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        [
                            0 => [
                                'currency' => 'btc',
                                'balance' => '1.5',
                                'locked' => '0',
                            ],
                        ],
                        [
                            1 => [
                                'currency' => 'eth',
                                'balance' => '2.75',
                                'locked' => '0',
                            ],
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/user/quidax/fetch-user-wallets');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.currency', 'btc')
            ->assertJsonPath('data.1.currency', 'eth')
            ->assertJsonMissingPath('data.0.0')
            ->assertJsonMissingPath('data.1.1');
    }

    public function test_fetch_user_wallet_normalizes_numeric_wrapper_to_plain_wallet_item(): void
    {
        $user = $this->makeUser();

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'message' => 'Successful',
                    'data' => [
                        0 => [
                            'currency' => 'usdt',
                            'balance' => '15',
                            'locked' => '0',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/user/quidax/fetch-user-wallet?currency=usdt');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.currency', 'usdt')
            ->assertJsonPath('data.0.balance', '15')
            ->assertJsonMissingPath('data.0.0');
    }

    public function test_process_quidax_withdrawal_waits_for_main_withdrawal_done_before_sending_destination(): void
    {
        $user = $this->makeUser(['quidax_id' => 'quidax-user-1']);

        $job = new ProcessQuidaxWithdrawal(
            $user,
            [
                'currency' => 'usdt',
                'network' => 'bep20',
                'amount' => 12,
                'fund_uid' => 'me',
                'transaction_note' => 'Withdrawal to main account',
                'narration' => 'Withdrawal to main account',
                'reference' => 'test-ref-main',
            ],
            [
                'currency' => 'usdt',
                'network' => 'bep20',
                'amount' => 10,
                'fund_uid' => '0xrecipient',
                'reference' => 'test-ref',
            ],
            2,
            12
        );

        $quidaxService = Mockery::mock(QuidaxService::class);
        $quidaxService->shouldReceive('create_withdrawal')
            ->once()
            ->with($user->quidax_id, Mockery::on(function (array $payload) {
                return ($payload['reference'] ?? null) === 'test-ref-main'
                    && (float) ($payload['amount'] ?? 0) === 12.0;
            }))
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [
                    'id' => 'main-withdrawal-1',
                    'status' => 'Processing',
                ],
            ]);

        $quidaxService->shouldReceive('fetch_a_withdrawal')
            ->once()
            ->with($user->quidax_id, 'main-withdrawal-1')
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [
                    'id' => 'main-withdrawal-1',
                    'status' => 'DONE',
                ],
            ]);

        $quidaxService->shouldReceive('create_withdrawal')
            ->once()
            ->with('me', Mockery::on(function (array $payload) {
                return ($payload['reference'] ?? null) === 'test-ref'
                    && (float) ($payload['amount'] ?? 0) === 10.0;
            }))
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [
                    'id' => 'destination-withdrawal-1',
                    'reference' => 'test-ref',
                    'type' => 'coin_address',
                    'currency' => 'usdt',
                    'amount' => '10',
                    'txid' => 'tx-123',
                    'transaction_note' => 'Transfer to recipient',
                    'recipient' => [
                        'type' => 'coin_address',
                        'details' => [
                            'address' => '0xrecipient',
                        ],
                    ],
                    'wallet' => [
                        'id' => 'wallet-1',
                        'currency' => 'usdt',
                    ],
                    'user' => [
                        'id' => 'quidax-user-1',
                    ],
                ],
            ]);

        $job->handle($quidaxService);

        $this->assertDatabaseHas('withdrawals', [
            'user_id' => $user->id,
            'reference' => 'test-ref',
            'currency' => 'usdt',
            'amount' => '10',
            'fee' => '2',
            'total' => '12',
            'trans_id' => 'tx-123',
        ]);
    }

    public function test_process_quidax_withdrawal_uses_cancel_endpoint_when_main_transfer_times_out(): void
    {
        $user = $this->makeUser(['quidax_id' => 'quidax-user-2']);

        $job = new ProcessQuidaxWithdrawal(
            $user,
            [
                'currency' => 'usdt',
                'network' => 'bep20',
                'amount' => 12,
                'fund_uid' => 'me',
                'transaction_note' => 'Withdrawal to main account',
                'narration' => 'Withdrawal to main account',
                'reference' => 'test-ref-main-timeout',
            ],
            [
                'currency' => 'usdt',
                'network' => 'bep20',
                'amount' => 10,
                'fund_uid' => '0xrecipient',
                'reference' => 'test-ref-timeout',
            ],
            2,
            12
        );

        $this->setProtectedProperty($job, 'mainWithdrawalWaitSeconds', 0);

        $quidaxService = Mockery::mock(QuidaxService::class);
        $quidaxService->shouldReceive('create_withdrawal')
            ->once()
            ->with($user->quidax_id, Mockery::type('array'))
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [
                    'id' => 'main-withdrawal-timeout',
                    'status' => 'Processing',
                ],
            ]);

        $quidaxService->shouldReceive('fetch_a_withdrawal')
            ->once()
            ->with($user->quidax_id, 'main-withdrawal-timeout')
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [
                    'id' => 'main-withdrawal-timeout',
                    'status' => 'PROCESSING',
                ],
            ]);

        $quidaxService->shouldReceive('cancel_withdrawal')
            ->once()
            ->with('main-withdrawal-timeout')
            ->andReturn([
                'status' => 'success',
                'message' => 'Successful',
                'data' => [],
            ]);

        $quidaxService->shouldReceive('create_withdrawal')
            ->with('me', Mockery::type('array'))
            ->never();

        $job->handle($quidaxService);

        $this->assertDatabaseMissing('withdrawals', [
            'reference' => 'test-ref-timeout',
        ]);
    }

    public function test_quidax_withdrawal_webhook_updates_matching_ramp_sell_hash_and_status(): void
    {
        $user = $this->makeUser(['quidax_id' => 'quidax-user-ramp']);

        $transaction = RampTransaction::create([
            'user_id' => $user->id,
            'merchant_reference' => 'OFFRAMP_WEBHOOK_HASH_001',
            'type' => 'off_ramp',
            'from_currency' => 'usdc',
            'from_amount' => '175.72',
            'network' => 'bep20',
            'wallet_address' => '0xold',
            'status' => 'processing',
            'metadata' => [
                'job' => [
                    'ramp_withdrawal' => [
                        'data' => [
                            'id' => 'ramp-withdrawal-123',
                            'status' => 'Processing',
                        ],
                    ],
                ],
            ],
        ]);

        $payloadData = [
            'id' => 'ramp-withdrawal-123',
            'status' => 'Successful',
            'currency' => 'usdc',
            'amount' => '175.72',
            'txid' => '0xabc123hash',
            'recipient' => [
                'details' => [
                    'address' => '0x102b9F8FC74B28d0Db44aA8528922ca4c808DBCf',
                    'network' => 'bep20',
                ],
            ],
        ];

        $controller = new QuidaxWebhookController();
        $method = new \ReflectionMethod($controller, 'reconcileRampTransactionFromWithdrawalWebhook');
        $method->setAccessible(true);
        $updated = $method->invoke($controller, $payloadData, 'completed', [
            'transaction_hash' => '0xabc123hash',
            'recipient_address' => '0x102b9F8FC74B28d0Db44aA8528922ca4c808DBCf',
            'network' => 'bep20',
        ]);

        $this->assertSame($transaction->id, $updated->id);
        $this->assertSame('awaiting_payout', $updated->status);
        $this->assertSame('0xabc123hash', $updated->transaction_hash);
        $this->assertSame('ramp-withdrawal-123', $updated->provider_transaction_id);
        $this->assertSame('0x102b9F8FC74B28d0Db44aA8528922ca4c808DBCf', $updated->wallet_address);
        $this->assertSame('0xabc123hash', data_get($updated->metadata, 'job.ramp_withdrawal.data.txid'));
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

    protected function setProtectedProperty(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    protected function getProtectedProperty(object $object, string $property)
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
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

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('type')->nullable();
            $table->string('currency')->nullable();
            $table->string('amount')->nullable();
            $table->string('fee')->nullable();
            $table->string('total')->nullable();
            $table->string('trans_id')->nullable();
            $table->string('transaction_note')->nullable();
            $table->json('recipient_data')->nullable();
            $table->json('wallet')->nullable();
            $table->json('user')->nullable();
            $table->timestamps();
        });

        Schema::create('ramp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('merchant_reference')->nullable();
            $table->string('public_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('transaction_hash')->nullable();
            $table->string('from_currency')->nullable();
            $table->string('to_currency')->nullable();
            $table->decimal('from_amount', 16, 8)->nullable();
            $table->decimal('to_amount', 16, 8)->nullable();
            $table->string('network')->nullable();
            $table->string('wallet_address')->nullable();
            $table->decimal('blockchain_fee', 16, 8)->nullable();
            $table->decimal('processor_fee', 16, 8)->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_status')->nullable();
            $table->timestamp('provider_status_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('crypto_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('quidax')->index();
            $table->string('event_name')->nullable()->index();
            $table->string('event_id')->nullable()->index();
            $table->string('dedupe_key')->unique();
            $table->string('transaction_reference')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('notification_type')->nullable()->index();
            $table->string('status')->default('processing')->index();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('duplicate')->default(false);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }
}
