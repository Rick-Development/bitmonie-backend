<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easyearn_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('easyearn_plan_id')->nullable()->constrained('easyearn_plans')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('status', 40)->index();
            $table->decimal('amount', 36, 18);
            $table->decimal('wallet_balance_after', 36, 18)->nullable();
            $table->string('reference')->unique();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['easyearn_plan_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easyearn_transactions');
    }
};
