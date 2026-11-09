<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedger;
use App\Services\QuidaxService;
use App\Services\ReferralWalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Referral bonus wallet: balance is stored in NGN. Fiat withdrawals are NGN;
 * crypto withdrawals are requested/shown in USD and paid as USDT (1 USDT = 1 USD).
 * Quidax is always mocked (rate ticker + sub-account funding).
 */
class ReferralWalletCurrencyTest extends TestCase
{
    protected const RATE = '1500';

    protected $quidax;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('referral_wallet.currency', 'NGN');
        Config::set('referral_wallet.min_withdrawal_amount', 1000);
        Config::set('referral_wallet.max_withdrawal_amount', null);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();
        Cache::flush();
        Event::fake(); // keep the shared-commission listener out of these tests

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('firstname')->nullable();
            $t->string('lastname')->nullable();
            $t->string('username')->nullable();
            $t->string('email')->unique();
            $t->string('password')->nullable();
            $t->boolean('email_verified')->default(true);
            $t->string('quidax_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('user_wallets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('currency_id')->nullable();
            $t->string('currency_code', 10)->default('NGN');
            $t->decimal('balance', 28, 8)->default(0);
            $t->decimal('reserved', 28, 8)->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('order_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_wallet_id');
            $t->string('type');
            $t->string('amount');
            $t->string('balance_after')->nullable();
            $t->string('reference')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('system_maintenances', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->nullable();
            $t->integer('status')->default(0);
            $t->timestamps();
        });
        Schema::create('admins', fn (Blueprint $t) => $t->id());
        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('referred_id')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_04_23_000000_create_wallet_ledger_and_wallet_withdrawal_requests_tables.php'))->up();
        (require base_path('database/migrations/2026_11_06_110000_add_crypto_fields_to_wallet_withdrawal_requests.php'))->up();

        $this->quidax = Mockery::mock(QuidaxService::class);
        $this->quidax->shouldReceive('getTicker')->with('usdtngn')
            ->andReturn(['data' => ['usdtngn' => ['ticker' => ['last' => self::RATE]]]])->byDefault();
        $this->app->instance(QuidaxService::class, $this->quidax);
    }

    protected function makeUserWithBonus(string $ngn): User
    {
        $id = DB::table('users')->insertGetId([
            'firstname' => 'Ref', 'lastname' => 'User', 'username' => 'ref' . uniqid(),
            'email' => uniqid('ref') . '@example.test', 'quidax_id' => 'qdx_sub_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        UserWallet::create(['user_id' => $id, 'currency_code' => 'NGN', 'balance' => $ngn, 'status' => true]);
        WalletLedger::create([
            'user_id' => $id, 'amount' => $ngn, 'currency_code' => 'NGN', 'transaction_type' => 'earn',
            'status' => 'completed', 'balance_before' => 0, 'balance_after' => $ngn,
            'pending_before' => 0, 'pending_after' => 0, 'reference' => 'EARN-' . uniqid(),
            'description' => 'bonus', 'transaction_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_balance_shows_ngn_for_fiat_and_usd_for_crypto(): void
    {
        $user = $this->makeUserWithBonus('15000');

        $summary = app(ReferralWalletService::class)->getBalanceSummary($user);

        $this->assertSame('NGN', $summary['currency']);
        $this->assertEquals(15000.0, $summary['available_for_withdrawal']);
        $this->assertEquals(10.0, $summary['available_for_withdrawal_usd']);
        $this->assertEquals(1500.0, $summary['usd_ngn_rate']);

        $methods = collect($summary['withdrawal_methods'])->keyBy('method');
        $this->assertSame('NGN', $methods['fiat']['currency']);
        $this->assertEquals(15000.0, $methods['fiat']['available_balance']);
        $this->assertEquals(1000.0, $methods['fiat']['min_amount']);
        $this->assertSame('USD', $methods['crypto']['currency']);
        $this->assertSame('USDT', $methods['crypto']['payout_currency']);
        $this->assertEquals(10.0, $methods['crypto']['available_balance']);
        $this->assertEquals(0.66, $methods['crypto']['min_amount']); // floor(1000/1500)
        $this->assertTrue($methods['crypto']['enabled']);
    }

    public function test_crypto_withdrawal_takes_usd_pays_usdt_and_debits_ngn_equivalent(): void
    {
        $user = $this->makeUserWithBonus('15000');
        $this->quidax->shouldReceive('fundSubAccount')->once()
            ->withArgs(fn ($quidaxId, $amount, $coin) => $quidaxId === 'qdx_sub_1' && $coin === 'usdt' && bccomp((string) $amount, '4', 8) === 0)
            ->andReturn(['status' => 'success', 'data' => ['id' => 'fund_1']]);

        $res = $this->actingAs($user, 'api')->postJson('/api/user/referral/withdraw', [
            'method' => 'crypto', 'amount' => 4, 'crypto_currency' => 'USDT',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.withdrawal.currency', 'USD')
            ->assertJsonPath('data.withdrawal.crypto_currency', 'USDT');
        $this->assertEquals(4, $res->json('data.withdrawal.amount'));
        $this->assertEquals(4, $res->json('data.withdrawal.amount_usd'));
        $this->assertEquals(4, $res->json('data.withdrawal.crypto_amount'));
        $this->assertEquals(6000, $res->json('data.withdrawal.amount_ngn'));
        $this->assertEquals(1500, $res->json('data.withdrawal.usd_ngn_rate'));
        $this->assertEquals(9000, (float) UserWallet::where('user_id', $user->id)->value('balance'));
        $this->assertEquals(6.0, $res->json('data.wallet.available_for_withdrawal_usd'));
    }

    public function test_crypto_withdrawal_above_usd_balance_is_rejected_without_paying(): void
    {
        $user = $this->makeUserWithBonus('15000'); // = USD 10
        $this->quidax->shouldNotReceive('fundSubAccount');

        $this->actingAs($user, 'api')->postJson('/api/user/referral/withdraw', [
            'method' => 'crypto', 'amount' => 11,
        ])->assertStatus(422);

        $this->assertEquals(15000, (float) UserWallet::where('user_id', $user->id)->value('balance'));
    }

    public function test_crypto_withdrawal_fails_closed_when_rate_unavailable(): void
    {
        $user = $this->makeUserWithBonus('15000');
        $this->quidax->shouldReceive('getTicker')->andReturn(['status' => 'error']);
        $this->quidax->shouldNotReceive('fundSubAccount');

        $res = $this->actingAs($user, 'api')->postJson('/api/user/referral/withdraw', [
            'method' => 'crypto', 'amount' => 2,
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('USD rate', (string) $res->json('message.error.0') . json_encode($res->json()));
    }

    public function test_fiat_withdrawal_amount_stays_ngn(): void
    {
        $user = $this->makeUserWithBonus('15000');
        $safeHaven = Mockery::mock(\App\Services\SafeHavenService::class);
        $safeHaven->shouldReceive('getUserSubAccount')->andReturn(new \App\Models\VirtualAccounts(['account_number' => '0123456789']));
        $safeHaven->shouldReceive('nameEnquiry')->andReturn(['sessionId' => 'S1']);
        $safeHaven->shouldReceive('transfer')->once()->withArgs(fn ($p) => bccomp((string) $p['amount'], '2000', 8) === 0)->andReturn(['status' => 'success']);
        $this->app->instance(\App\Services\SafeHavenService::class, $safeHaven);
        Config::set('services.safeHeaven.main_account', '9999999999');
        Schema::create('basic_settings', fn (Blueprint $t) => $t->id());
        $this->quidax->shouldNotReceive('fundSubAccount');

        $res = $this->actingAs($user, 'api')->postJson('/api/user/referral/withdraw', ['method' => 'fiat', 'amount' => 2000]);

        $res->assertOk()->assertJsonPath('data.withdrawal.currency', 'NGN');
        $this->assertEquals(2000, $res->json('data.withdrawal.amount'));
        $this->assertNull($res->json('data.withdrawal.amount_usd'));
    }
}
