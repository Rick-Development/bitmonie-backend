<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_card_orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('card_holder_id')
                ->nullable()
                ->constrained('crypto_card_holders')
                ->nullOnDelete();

            $table->foreignId('card_id')
                ->nullable()
                ->constrained('crypto_cards')
                ->nullOnDelete();

            $table->string('provider', 50)->default('sudo');
            $table->string('reference', 100)->unique();
            $table->string('provider_order_id', 150)->nullable()->index();

            $table->string('order_type', 30)->default('physical');
            $table->string('currency', 10)->nullable();

            $table->decimal('amount', 20, 8)->nullable();
            $table->decimal('fee', 20, 8)->nullable();
            $table->decimal('total_amount', 20, 8)->nullable();

            $table->string('status', 50)->default('pending')->index();

            $table->json('shipping_address')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'provider']);
            $table->index(['card_holder_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_card_orders');
    }
};