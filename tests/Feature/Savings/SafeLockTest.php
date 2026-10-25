<?php

namespace Tests\Feature\Savings;

use App\Models\SafeLock;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SafeLock create/break through the real HTTP route, with the SafeHaven
 * funding provider replaced by a fake.
 */
class SafeLockTest extends SavingsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('safe_locks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('title')->default('Safe Lock');
            $t->decimal('amount', 20, 8);
            $t->decimal('interest_rate', 5, 2);
            $t->decimal('interest_accrued', 20, 8)->default(0);
            $t->timestamp('last_interest_date')->nullable();
            $t->timestamp('lock_date')->nullable();
            $t->timestamp('maturity_date')->nullable();
            $t->boolean('is_redeemed')->default(false);
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('savings_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->integer('duration_days');
            $t->decimal('interest_rate', 5, 2);
            $t->decimal('min_amount', 20, 8)->default(0);
            $t->decimal('max_amount', 20, 8)->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::create('savings_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('savings_id')->nullable();
            $t->unsignedBigInteger('savingsable_id')->nullable();
            $t->string('savingsable_type')->nullable();
            $t->decimal('amount', 20, 8);
            $t->decimal('balance_after', 20, 8)->nullable();
            $t->string('type');
            $t->string('status')->nullable();
            $t->string('source')->nullable();
            $t->text('narration')->nullable();
            $t->timestamps();
        });

        DB::table('savings_plans')->insert([
            'name' => '30 Days', 'slug' => '30-days', 'duration_days' => 30, 'interest_rate' => '10.00',
            'min_amount' => '1000', 'max_amount' => null, 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fakeProvider(bool $depositFails = false): object
    {
        $fake = new class($depositFails) implements SavingsFundingProviderInterface {
            public array $deposits = [];
            public array $withdrawals = [];

            public function __construct(private bool $depositFails)
            {
            }

            public function deposit(\App\Models\User $user, string $amount, string $reference, $narration = ''): array
            {
                if ($this->depositFails) {
                    throw new \RuntimeException('provider down');
                }
                $this->deposits[] = [$amount, $reference];

                return ['status' => 'Completed'];
            }

            public function withdraw(\App\Models\User $user, string $amount, string $reference, $narration = ''): array
            {
                $this->withdrawals[] = [$amount, $reference];

                return ['status' => 'Completed'];
            }

            public function getTransferStatus(string $sessionId, string $paymentReference): array
            {
                return ['status' => 'Completed'];
            }
        };
        $this->app->instance(SavingsFundingProviderInterface::class, $fake);

        return $fake;
    }

    public function test_create_debits_wallet_and_funds_provider(): void
    {
        $user = $this->makeUser('5000.00');
        $provider = $this->fakeProvider();

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00', 'title' => 'Rent',
        ]);

        $res->assertStatus(200)->assertJsonPath('type', 'success');
        $this->assertSame('3000.00', $this->walletBalance($user));
        $this->assertCount(1, $provider->deposits);
        $this->assertSame(1, SafeLock::where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_create_with_insufficient_wallet_balance_changes_nothing(): void
    {
        $user = $this->makeUser('500.00');
        $provider = $this->fakeProvider();

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00',
        ]);

        $res->assertJsonPath('type', 'error');
        $this->assertSame('500.00', $this->walletBalance($user));
        $this->assertCount(0, $provider->deposits);
        $this->assertSame(0, SafeLock::count());
    }

    public function test_create_rejects_amount_below_plan_minimum(): void
    {
        $user = $this->makeUser('5000.00');
        $this->fakeProvider();

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '100.00',
        ]);

        $res->assertJsonPath('type', 'error');
        $this->assertSame('5000.00', $this->walletBalance($user));
        $this->assertSame(0, SafeLock::count());
    }

    public function test_provider_failure_leaves_wallet_untouched(): void
    {
        $user = $this->makeUser('5000.00');
        $this->fakeProvider(depositFails: true);

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00',
        ]);

        $res->assertJsonPath('type', 'error');
        $this->assertSame('5000.00', $this->walletBalance($user));
        $this->assertSame(0, SafeLock::count());
    }

    public function test_local_failure_after_provider_deposit_reverses_the_provider_transfer(): void
    {
        $user = $this->makeUser('5000.00');
        $provider = $this->fakeProvider();

        SafeLock::creating(function () {
            throw new \RuntimeException('database went away');
        });

        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00',
        ]);

        $res->assertJsonPath('type', 'error');
        $this->assertSame('5000.00', $this->walletBalance($user));
        $this->assertCount(1, $provider->deposits);
        $this->assertCount(1, $provider->withdrawals, 'provider money must be returned when local records roll back');
        $this->assertSame('2000', rtrim(rtrim($provider->withdrawals[0][0], '0'), '.'));
    }

    public function test_break_early_returns_principal_minus_one_percent_penalty(): void
    {
        $user = $this->makeUser('5000.00');
        $provider = $this->fakeProvider();

        $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00',
        ])->assertJsonPath('type', 'success');

        $lock = SafeLock::first();
        $res = $this->actingAs($user, 'api')->postJson('/api/v1/savings/safelock/break', ['lock_id' => $lock->id]);

        $res->assertJsonPath('type', 'success');
        $this->assertSame('4980.00', $this->walletBalance($user)); // 3000 + 1980
        $this->assertSame('broken', $lock->refresh()->status);
        $this->assertCount(1, $provider->withdrawals);
    }

    public function test_cannot_break_another_users_lock(): void
    {
        $alice = $this->makeUser('5000.00');
        $bob = $this->makeUser('5000.00');
        $this->fakeProvider();

        $this->actingAs($alice, 'api')->postJson('/api/v1/savings/safelock/create', [
            'plan_id' => 1, 'amount' => '2000.00',
        ])->assertJsonPath('type', 'success');

        $res = $this->actingAs($bob, 'api')->postJson('/api/v1/savings/safelock/break', ['lock_id' => SafeLock::first()->id]);

        $res->assertJsonPath('type', 'error');
        $this->assertSame('5000.00', $this->walletBalance($bob));
        $this->assertSame('active', SafeLock::first()->status);
    }
}
