<?php

namespace Tests\Feature\Savings;

use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnWallet;
use App\Models\User;
use App\Services\QuidaxService;
use App\Services\QuidaxSpendableBalanceService;
use App\Services\UsdtEasyearnService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * USDT EasyEarn investment creation with Quidax faked. USDT never touches the
 * NGN wallet: the principal moves from the user's Quidax sub-account into the
 * platform escrow and is tracked in usdt_easyearn_* tables.
 */
class UsdtEasyearnTest extends SavingsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', fn (Blueprint $t) => $t->string('quidax_id')->nullable());

        Schema::create('usdt_easyearn_settings', function (Blueprint $t) {
            $t->id();
            $t->decimal('current_monthly_rate', 5, 2)->default(10);
            $t->decimal('min_investment', 20, 8)->default(10);
            $t->decimal('max_investment', 20, 8)->nullable();
            $t->boolean('is_active')->default(true);
            $t->tinyInteger('payout_day')->default(10);
            $t->boolean('auto_credit_enabled')->default(false);
            $t->boolean('early_withdrawal_enabled')->default(true);
            $t->decimal('early_withdrawal_penalty_percent', 5, 2)->default(5);
            $t->decimal('base_apy', 5, 2)->default(15);
            $t->decimal('power_bonus_multiplier', 5, 2)->default(2);
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('usdt_easyearn_investments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->decimal('amount', 20, 8);
            $t->decimal('initial_deposit_amount', 20, 8)->nullable();
            $t->tinyInteger('duration_months')->default(12);
            $t->integer('duration_days')->default(365);
            $t->decimal('interest_rate', 5, 2);
            $t->boolean('auto_compound')->default(false);
            $t->decimal('power_bonus_multiplier', 5, 2)->default(1);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status')->default('active');
            $t->decimal('total_interest_earned', 20, 8)->default(0);
            $t->decimal('power_bonus_amount', 20, 8)->nullable();
            $t->timestamp('power_bonus_credited_at')->nullable();
            $t->date('last_interest_credit_date')->nullable();
            $t->timestamps();
        });
        Schema::create('usdt_easyearn_wallets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->decimal('balance', 20, 8)->default(0);
            $t->decimal('locked_balance', 20, 8)->default(0);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        DB::table('usdt_easyearn_settings')->insert([
            'current_monthly_rate' => 10, 'min_investment' => 1, 'is_active' => true, 'payout_day' => 10,
            'early_withdrawal_enabled' => true, 'early_withdrawal_penalty_percent' => 5,
            'base_apy' => 15, 'power_bonus_multiplier' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function cryptoUser(string $usdt = '100'): User
    {
        $user = $this->makeUser('5000.00');
        DB::table('users')->where('id', $user->id)->update(['quidax_id' => 'qdx_' . $user->id]);

        // Spendable-balance service reads the withdrawals/ramp tables; replace it.
        $spendable = Mockery::mock(QuidaxSpendableBalanceService::class);
        $spendable->shouldReceive('augmentWalletPayload')->andReturnUsing(fn ($u, $w) => ['available_balance' => $w['balance']]);
        $this->app->instance(QuidaxSpendableBalanceService::class, $spendable);

        $this->usdtBalance = $usdt;

        return $user->refresh();
    }

    private string $usdtBalance = '100';

    private function quidax(?callable $escrow = null, array $refunds = null): \Mockery\MockInterface
    {
        $q = Mockery::mock(QuidaxService::class);
        $q->shouldReceive('fetchUserWallet')->andReturnUsing(fn () => ['data' => ['balance' => $this->usdtBalance]]);
        if ($escrow) {
            $q->shouldReceive('transferToEscrow')->andReturnUsing($escrow);
        }
        $this->app->instance(QuidaxService::class, $q);

        return $q;
    }

    public function test_investment_locks_usdt_in_escrow_and_creates_active_plan(): void
    {
        $user = $this->cryptoUser('100');
        $calls = [];
        $q = $this->quidax(function (...$args) use (&$calls) {
            $calls[] = $args;

            return ['data' => ['id' => 'wd_1', 'status' => 'pending']];
        });

        $inv = app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);

        $this->assertSame('active', $inv->status);
        $this->assertSame('5.00000000', (string) $inv->amount);
        $this->assertCount(1, $calls);
        $this->assertSame('qdx_' . $user->id, $calls[0][0]);
        $this->assertEquals(5, $calls[0][1]);
        $this->assertSame('usdt', $calls[0][2]);
        $this->assertSame('5.00000000', (string) UsdtEasyearnWallet::where('user_id', $user->id)->value('locked_balance'));
        // NGN wallet is not touched by a USDT plan.
        $this->assertSame('5000.00', $this->walletBalance($user));
    }

    public function test_insufficient_usdt_never_calls_quidax_and_creates_nothing(): void
    {
        $user = $this->cryptoUser('2');
        $q = $this->quidax();
        $q->shouldNotReceive('transferToEscrow');

        $this->expectExceptionMessage('Insufficient USDT balance');
        try {
            app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);
        } finally {
            $this->assertSame(0, UsdtEasyearnInvestment::count());
        }
    }

    public function test_quidax_failure_creates_no_investment_and_locks_nothing(): void
    {
        $user = $this->cryptoUser('100');
        $this->quidax(function () {
            throw new \RuntimeException('quidax 503');
        });

        try {
            app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);
            $this->fail('Expected failure');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Failed to lock USDT', $e->getMessage());
        }

        $this->assertSame(0, UsdtEasyearnInvestment::count());
        $this->assertSame('0.00000000', (string) (UsdtEasyearnWallet::where('user_id', $user->id)->value('locked_balance') ?? '0.00000000'));
    }

    public function test_response_without_data_is_not_treated_as_success(): void
    {
        $user = $this->cryptoUser('100');
        $this->quidax(fn () => ['status' => 'error', 'message' => 'insufficient funds']);

        try {
            app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);
            $this->fail('Expected failure');
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertSame(0, UsdtEasyearnInvestment::count());
    }

    public function test_amount_below_minimum_is_rejected_before_any_transfer(): void
    {
        DB::table('usdt_easyearn_settings')->update(['min_investment' => 10]);
        $user = $this->cryptoUser('100');
        $q = $this->quidax();
        $q->shouldNotReceive('transferToEscrow');

        $this->expectExceptionMessage('Investment amount must be between');
        app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);
    }

    public function test_local_failure_after_escrow_transfer_refunds_the_user(): void
    {
        $user = $this->cryptoUser('100');
        $refunds = [];
        $q = $this->quidax(fn () => ['data' => ['id' => 'wd_1']]);
        $q->shouldReceive('fundSubAccount')->andReturnUsing(function (...$args) use (&$refunds) {
            $refunds[] = $args;

            return ['data' => ['id' => 'refund_1']];
        });

        UsdtEasyearnInvestment::creating(function () {
            throw new \RuntimeException('database went away');
        });

        try {
            app(UsdtEasyearnService::class)->createInvestment($user, 5.0, false);
            $this->fail('Expected failure');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(0, UsdtEasyearnInvestment::count());
        $this->assertCount(1, $refunds, 'escrowed USDT must be returned when the local records roll back');
        $this->assertSame('qdx_' . $user->id, $refunds[0][0]);
        $this->assertEquals(5, $refunds[0][1]);
        $this->assertSame('usdt', $refunds[0][2]);
    }

    public function test_investment_supports_30_days_60_days_and_365_days(): void
    {
        $user = $this->cryptoUser('100');
        $this->quidax(fn () => ['data' => ['id' => 'wd_test']]);

        $service = app(UsdtEasyearnService::class);

        // 30 days
        $inv30 = $service->createInvestment($user, 5.0, false, 30);
        $this->assertSame(30, $inv30->duration_days);
        $this->assertSame(1, $inv30->duration_months);
        $this->assertSame('30 Days', $inv30->getDurationLabel());
        $this->assertSame(30, (int) round($inv30->start_date->diffInDays($inv30->end_date)));

        // 60 days
        $inv60 = $service->createInvestment($user, 5.0, false, 60);
        $this->assertSame(60, $inv60->duration_days);
        $this->assertSame(2, $inv60->duration_months);
        $this->assertSame('60 Days', $inv60->getDurationLabel());
        $this->assertSame(60, (int) round($inv60->start_date->diffInDays($inv60->end_date)));

        // 365 days / 1 year default
        $inv365 = $service->createInvestment($user, 5.0, false, 365);
        $this->assertSame(365, $inv365->duration_days);
        $this->assertSame(12, $inv365->duration_months);
        $this->assertSame('1 Year', $inv365->getDurationLabel());
        $this->assertTrue($inv365->end_date->gt($inv60->end_date));
    }
}
