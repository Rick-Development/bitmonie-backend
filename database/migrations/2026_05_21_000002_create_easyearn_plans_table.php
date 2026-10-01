<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easyearn_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_wallet_id')->constrained('user_wallets')->cascadeOnDelete();
            $table->decimal('usdt_amount_deposited', 36, 18);
            $table->decimal('expected_return', 36, 18);
            $table->decimal('return_multiplier', 18, 8)->default('2.00000000');
            $table->dateTime('maturity_date')->index();
            $table->dateTime('midpoint_notify_at')->nullable()->index();
            $table->dateTime('midpoint_notified_at')->nullable();
            $table->dateTime('matured_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easyearn_plans');
    }
};
