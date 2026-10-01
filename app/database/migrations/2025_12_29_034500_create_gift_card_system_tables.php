<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_card_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_card_countries', function (Blueprint $table) {
            $table->string('iso_name')->primary();
            $table->string('name');
            $table->string('currency_code');
            $table->string('flag_url')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained('user_wallets')->onDelete('cascade');
            $table->string('reloadly_transaction_id')->unique()->nullable();
            $table->string('custom_identifier')->unique();
            $table->enum('status', ['PENDING', 'SUCCESSFUL', 'FAILED', 'REFUNDED'])->default('PENDING');
            
            $table->decimal('amount', 18, 8); // User wallet debit amount
            $table->string('currency', 10); // User wallet currency (NGN/USD)
            $table->decimal('fee', 18, 8)->default(0);
            $table->decimal('discount', 18, 8)->default(0);
            
            $table->integer('product_id');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 18, 8); // Product currency
            $table->string('product_currency', 10);
            
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            
            // Redemption data (Encypted/Stored securely)
            $table->text('card_number')->nullable();
            $table->text('pin_code')->nullable();
            $table->text('redemption_url')->nullable();
            
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_card_countries');
        Schema::dropIfExists('gift_card_categories');
    }
};
