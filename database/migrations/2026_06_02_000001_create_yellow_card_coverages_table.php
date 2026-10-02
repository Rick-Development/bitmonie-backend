<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yellow_card_coverages', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id')->unique();
            $table->string('country_code', 10)->nullable()->index();
            $table->string('country_name')->nullable();
            $table->string('currency_code', 10)->nullable()->index();
            $table->string('channel_type', 40)->nullable()->index();
            $table->string('ramp_type', 40)->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('settlement_time')->nullable();
            $table->string('status', 40)->nullable()->index();
            $table->decimal('min_amount', 36, 8)->nullable();
            $table->decimal('max_amount', 36, 8)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['country_code', 'currency_code']);
            $table->index(['country_code', 'ramp_type', 'channel_type'], 'yellow_card_coverage_route_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yellow_card_coverages');
    }
};
