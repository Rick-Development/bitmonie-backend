<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\MerchantApplication;
use App\Models\User;
use App\Services\QuidaxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MerchantApplicationFlowTest extends TestCase
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

    public function test_apply_locks_security_deposit_and_marks_user_pending(): void
    {
        $user = $this->makeUser([
            'quidax_id' => 'quidax-merchant-1',
            'mobile' => '08012345678',
            'full_mobile' => '+2348012345678',
            'merchant_status' => 'none',
            'kyc_tier' => 2,
        ]);

        $this->seedMerchantSettings(100, 200);

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fetchUserWallet')
                ->once()
                ->with($user->quidax_id, 'usdt')
                ->andReturn([
                    'status' => 'success',
                    'data' => ['balance' => '250'],
                ]);

            $mock->shouldReceive('transferToEscrow')
                ->once()
                ->withArgs(function ($quidaxId, $amount, $currency, $note) use ($user) {
                    return $quidaxId === $user->quidax_id
                        && (float) $amount === 200.0
                        && $currency === 'usdt'
                        && $note === 'P2P Merchant Security Deposit';
                })
                ->andReturn([
                    'status' => 'success',
                    'data' => ['id' => 'lock-ref-001'],
                ]);
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/p2p/merchant/apply', [
            'whatsapp' => '08012345678',
            'business_name' => 'BitMonie Merchant',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.security_deposit.amount', 200)
            ->assertJsonPath('data.security_deposit.status', 'locked')
            ->assertJsonPath('data.security_deposit.reference', 'lock-ref-001');

        $application = MerchantApplication::query()->firstOrFail();

        $this->assertSame($user->id, $application->user_id);
        $this->assertSame('pending', $application->status);
        $this->assertSame('locked', $application->security_deposit_status);
        $this->assertSame('lock-ref-001', $application->security_deposit_lock_reference);
        $this->assertSame(200.0, (float) $application->security_deposit_amount);
        $this->assertSame('pending', $user->fresh()->merchant_status);
    }

    public function test_admin_reject_releases_locked_security_deposit_back_to_user(): void
    {
        $user = $this->makeUser([
            'quidax_id' => 'quidax-merchant-2',
            'merchant_status' => 'pending',
            'kyc_tier' => 2,
        ]);
        $admin = $this->makeAdmin();

        $application = MerchantApplication::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '08010000000',
            'whatsapp' => '08010000000',
            'business_name' => 'Pending Merchant',
            'quidax_usdt_balance' => 260,
            'min_usdt_required' => 100,
            'security_deposit_amount' => 200,
            'security_deposit_currency' => 'usdt',
            'security_deposit_status' => 'locked',
            'security_deposit_lock_reference' => 'lock-ref-002',
            'security_deposit_locked_at' => now(),
            'balance_verified_at' => now(),
            'status' => 'pending',
        ]);

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fundSubAccount')
                ->once()
                ->withArgs(function ($quidaxId, $amount, $currency) use ($user) {
                    return $quidaxId === $user->quidax_id
                        && (float) $amount === 200.0
                        && $currency === 'usdt';
                })
                ->andReturn([
                    'status' => 'success',
                    'data' => ['id' => 'release-ref-002'],
                ]);
        });

        $response = $this->actingAs($admin, 'admin')
            ->from('/admin/p2p/merchant-applications/' . $application->id)
            ->post('/admin/p2p/merchant-applications/' . $application->id . '/reject', [
                'reason' => 'Incomplete merchant verification documents.',
            ]);

        $response->assertStatus(302);

        $application = $application->fresh();

        $this->assertSame('rejected', $application->status);
        $this->assertSame('released', $application->security_deposit_status);
        $this->assertSame('release-ref-002', $application->security_deposit_release_reference);
        $this->assertSame('application_rejected', $application->security_deposit_release_reason);
        $this->assertNotNull($application->security_deposit_released_at);
        $this->assertSame('rejected', $user->fresh()->merchant_status);
    }

    public function test_user_deactivate_releases_security_deposit_and_offlines_live_ads(): void
    {
        $user = $this->makeUser([
            'quidax_id' => 'quidax-merchant-3',
            'merchant_status' => 'approved',
            'merchant_approved_at' => now(),
            'kyc_tier' => 3,
        ]);

        $application = MerchantApplication::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '08020000000',
            'whatsapp' => '08020000000',
            'business_name' => 'Approved Merchant',
            'quidax_usdt_balance' => 300,
            'min_usdt_required' => 100,
            'security_deposit_amount' => 200,
            'security_deposit_currency' => 'usdt',
            'security_deposit_status' => 'locked',
            'security_deposit_lock_reference' => 'lock-ref-003',
            'security_deposit_locked_at' => now(),
            'balance_verified_at' => now(),
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        DB::table('p2p_ads')->insert([
            [
                'user_id' => $user->id,
                'status' => 'online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'status' => 'offline',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->mockQuidaxService(function ($mock) use ($user) {
            $mock->shouldReceive('fundSubAccount')
                ->once()
                ->withArgs(function ($quidaxId, $amount, $currency) use ($user) {
                    return $quidaxId === $user->quidax_id
                        && (float) $amount === 200.0
                        && $currency === 'usdt';
                })
                ->andReturn([
                    'status' => 'success',
                    'data' => ['id' => 'release-ref-003'],
                ]);
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/p2p/merchant/deactivate', [
            'reason' => 'I no longer want to trade as a merchant.',
        ]);

        $response->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.status', 'deactivated')
            ->assertJsonPath('data.offline_ads_count', 1)
            ->assertJsonPath('data.security_deposit.status', 'released')
            ->assertJsonPath('data.security_deposit.release_reference', 'release-ref-003');

        $application = $application->fresh();
        $user = $user->fresh();

        $this->assertSame('none', $user->merchant_status);
        $this->assertNull($user->merchant_approved_at);
        $this->assertSame(2, (int) $user->kyc_tier);
        $this->assertNotNull($application->merchant_deactivated_at);
        $this->assertSame('released', $application->security_deposit_status);
        $this->assertSame('merchant_deactivated', $application->security_deposit_release_reason);
        $this->assertSame(0, DB::table('p2p_ads')->where('user_id', $user->id)->where('status', 'online')->count());
    }

    public function test_user_deactivate_is_blocked_when_active_orders_exist(): void
    {
        $user = $this->makeUser([
            'quidax_id' => 'quidax-merchant-4',
            'merchant_status' => 'approved',
            'merchant_approved_at' => now(),
            'kyc_tier' => 3,
        ]);

        $application = MerchantApplication::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '08030000000',
            'whatsapp' => '08030000000',
            'business_name' => 'Busy Merchant',
            'quidax_usdt_balance' => 300,
            'min_usdt_required' => 100,
            'security_deposit_amount' => 200,
            'security_deposit_currency' => 'usdt',
            'security_deposit_status' => 'locked',
            'security_deposit_lock_reference' => 'lock-ref-004',
            'security_deposit_locked_at' => now(),
            'balance_verified_at' => now(),
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        DB::table('p2p_orders')->insert([
            'maker_id' => $user->id,
            'taker_id' => $user->id + 1,
            'status' => 'accepted',
            'appeal_status' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mockQuidaxService(function ($mock) {
            $mock->shouldReceive('fundSubAccount')->never();
        });

        $response = $this->actingAs($user, 'api')->postJson('/api/user/p2p/merchant/deactivate');

        $response->assertStatus(409)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Merchant status cannot be deactivated while there are active P2P orders or disputes.');

        $this->assertSame('approved', $user->fresh()->merchant_status);
        $this->assertSame('locked', $application->fresh()->security_deposit_status);
    }

    public function test_admin_cannot_approve_application_without_locked_security_deposit(): void
    {
        $user = $this->makeUser([
            'merchant_status' => 'pending',
            'kyc_tier' => 2,
        ]);
        $admin = $this->makeAdmin();

        $application = MerchantApplication::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => '08040000000',
            'whatsapp' => '08040000000',
            'business_name' => 'Legacy Pending Merchant',
            'quidax_usdt_balance' => 300,
            'min_usdt_required' => 100,
            'security_deposit_amount' => 200,
            'security_deposit_currency' => 'usdt',
            'security_deposit_status' => 'not_locked',
            'balance_verified_at' => now(),
            'status' => 'pending',
        ]);

        $this->mockQuidaxService();

        $response = $this->actingAs($admin, 'admin')
            ->from('/admin/p2p/merchant-applications/' . $application->id)
            ->post('/admin/p2p/merchant-applications/' . $application->id . '/approve', [
                'admin_notes' => 'Attempting approval without deposit.',
            ]);

        $response->assertStatus(302);

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertSame('pending', $user->fresh()->merchant_status);
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
            'firstname' => 'Merchant',
            'lastname' => 'Tester',
            'email' => 'merchant.' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'email_verified' => 1,
            'kyc_verified' => 1,
            'merchant_status' => 'none',
            'kyc_tier' => 2,
        ], $attributes));
    }

    protected function makeAdmin(array $attributes = []): Admin
    {
        return Admin::query()->create(array_merge([
            'firstname' => 'Admin',
            'lastname' => 'User',
            'username' => 'admin_' . Str::random(6),
            'email' => 'admin.' . Str::random(8) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
        ], $attributes));
    }

    protected function seedMerchantSettings(float $minimumBalance, float $securityDeposit): void
    {
        DB::table('admin_settings')->insert([
            [
                'setting_key' => 'merchant_min_usdt',
                'setting_value' => (string) $minimumBalance,
                'description' => 'Minimum balance required for merchant application',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'merchant_security_deposit_usdt',
                'setting_value' => (string) $securityDeposit,
                'description' => 'Refundable security deposit for merchant activation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    protected function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('mobile')->nullable();
            $table->string('full_mobile')->nullable();
            $table->string('quidax_id')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('kyc_verified')->default(true);
            $table->integer('kyc_tier')->default(0);
            $table->string('merchant_status')->default('none');
            $table->timestamp('merchant_approved_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('business_name')->nullable();
            $table->decimal('quidax_usdt_balance', 20, 8)->default(0);
            $table->decimal('min_usdt_required', 20, 8)->default(0);
            $table->decimal('security_deposit_amount', 20, 8)->default(0);
            $table->string('security_deposit_currency', 16)->default('usdt');
            $table->string('security_deposit_status', 32)->default('not_locked');
            $table->string('security_deposit_lock_reference')->nullable();
            $table->timestamp('security_deposit_locked_at')->nullable();
            $table->string('security_deposit_release_reference')->nullable();
            $table->timestamp('security_deposit_released_at')->nullable();
            $table->string('security_deposit_release_reason')->nullable();
            $table->timestamp('balance_verified_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('merchant_deactivated_at')->nullable();
            $table->text('merchant_deactivation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('offline');
            $table->timestamps();
        });

        Schema::create('p2p_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maker_id')->nullable();
            $table->unsignedBigInteger('taker_id')->nullable();
            $table->string('status')->default('accepted');
            $table->string('appeal_status')->default('none');
            $table->timestamps();
        });
    }
}
