<?php

namespace Tests\Feature;

use App\Jobs\ReconcileRampTransaction;
use App\Models\RampTransaction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReconcileRampTransactionsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('ramp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->string('merchant_reference')->unique();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Queue::fake();
    }

    public function test_on_ramp_option_dispatches_confirmed_buys_and_skips_sells(): void
    {
        $confirmedBuy = RampTransaction::create([
            'merchant_reference' => 'ONRAMP_CONFIRMED',
            'type' => 'on_ramp',
            'status' => 'confirmed',
        ]);
        RampTransaction::create([
            'merchant_reference' => 'OFFRAMP_PROCESSING',
            'type' => 'off_ramp',
            'status' => 'processing',
        ]);
        RampTransaction::create([
            'merchant_reference' => 'ONRAMP_SUCCESSFUL',
            'type' => 'on_ramp',
            'status' => 'successful',
        ]);

        $this->artisan('ramp:reconcile', ['--type' => 'on_ramp'])
            ->assertSuccessful();

        Queue::assertPushed(ReconcileRampTransaction::class, 1);
        Queue::assertPushed(ReconcileRampTransaction::class, function (ReconcileRampTransaction $job) use ($confirmedBuy) {
            return $job->transactionId === $confirmedBuy->id;
        });
    }
}
