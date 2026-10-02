<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autosave_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autosave_plan_id')->constrained('autosave_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->foreignId('source_order_transaction_id')->nullable()->constrained('order_transactions')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->decimal('amount', 36, 8);
            $table->decimal('source_amount', 36, 8)->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('reference')->nullable()->index();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['autosave_plan_id', 'created_at']);
            $table->unique(['autosave_plan_id', 'source_order_transaction_id'], 'autosave_plan_source_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autosave_transactions');
    }
};
