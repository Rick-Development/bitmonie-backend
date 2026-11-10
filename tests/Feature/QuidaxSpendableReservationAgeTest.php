<?php

namespace Tests\Feature;

use App\Models\RampTransaction;
use App\Services\QuidaxSpendableBalanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuidaxSpendableReservationAgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('ramp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('from_currency')->nullable();
            $table->decimal('from_amount', 16, 8)->nullable();
            $table->string('network')->nullable();
            $table->string('status')->nullable();
            $table->string('provider_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function makeOffRamp(array $overrides = []): RampTransaction
    {
        $transaction = new RampTransaction(array_merge([
            'user_id' => 106,
            'type' => 'off_ramp',
            'from_currency' => 'usdt',
            'from_amount' => '3.6628',
            'network' => 'bep20',
            'status' => 'processing',
            'provider_status' => 'too_many_requests',
            'metadata' => ['provider_status' => 'too_many_requests'],
        ], $overrides));
        $transaction->save();

        return $transaction->fresh();
    }

    public function test_too_many_requests_off_ramp_stops_reserving_after_created_at_max_age_even_if_updated_at_is_fresh(): void
    {
        Config::set('services.quidax.ramp_reservation_max_age_hours', 24);
        Config::set('services.quidax.withdrawal_reservation_ttl_hours', 24);

        $transaction = $this->makeOffRamp();
        $transaction->created_at = now()->subHours(25);
        $transaction->updated_at = now();
        $transaction->save();

        $decision = app(QuidaxSpendableBalanceService::class)
            ->inspectOffRampReservation($transaction->fresh(), 'usdt');

        $this->assertFalse($decision['active']);
        $this->assertSame('stale_created_at_max_age', $decision['reason']);
    }

    public function test_recent_too_many_requests_off_ramp_is_still_reserved(): void
    {
        Config::set('services.quidax.ramp_reservation_max_age_hours', 24);
        Config::set('services.quidax.withdrawal_reservation_ttl_hours', 24);

        $transaction = $this->makeOffRamp();
        $transaction->created_at = now()->subHours(2);
        $transaction->updated_at = now();
        $transaction->save();

        $decision = app(QuidaxSpendableBalanceService::class)
            ->inspectOffRampReservation($transaction->fresh(), 'usdt');

        $this->assertTrue($decision['active']);
        $this->assertSame('active', $decision['reason']);
    }

    public function test_created_at_max_age_does_not_apply_to_processing_sells_without_provider_errors(): void
    {
        Config::set('services.quidax.ramp_reservation_max_age_hours', 24);
        Config::set('services.quidax.withdrawal_reservation_ttl_hours', 72);

        $transaction = $this->makeOffRamp([
            'provider_status' => 'processing',
            'metadata' => ['provider_status' => 'processing'],
        ]);
        $transaction->created_at = now()->subHours(25);
        $transaction->updated_at = now();
        $transaction->save();

        $decision = app(QuidaxSpendableBalanceService::class)
            ->inspectOffRampReservation($transaction->fresh(), 'usdt');

        $this->assertTrue($decision['active']);
        $this->assertSame('active', $decision['reason']);
    }

    public function test_max_age_of_zero_disables_created_at_cutoff(): void
    {
        Config::set('services.quidax.ramp_reservation_max_age_hours', 0);
        Config::set('services.quidax.withdrawal_reservation_ttl_hours', 72);

        $transaction = $this->makeOffRamp();
        $transaction->created_at = now()->subDays(10);
        $transaction->updated_at = now();
        $transaction->save();

        $decision = app(QuidaxSpendableBalanceService::class)
            ->inspectOffRampReservation($transaction->fresh(), 'usdt');

        $this->assertTrue($decision['active']);
        $this->assertSame('active', $decision['reason']);
    }
}
