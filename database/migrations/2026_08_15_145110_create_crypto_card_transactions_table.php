<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crypto_card_transactions', function (Blueprint $table) {

            $table->id();

            // User
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Card
            $table->foreignId('crypto_card_id')
                ->constrained('crypto_cards')
                ->cascadeOnDelete();

            // Provider
            $table->string('card_provider', 30)
                ->default('sudo');

            $table->string('provider_transaction_id')
                ->nullable()
                ->index('cct_provider_tx_idx');

            $table->string('provider_authorization_id')
                ->nullable()
                ->index('cct_provider_auth_idx');

            $table->string('provider_card_id')
                ->nullable()
                ->index('cct_provider_card_idx');

            $table->string('provider_customer_id')
                ->nullable();

            // Transaction
            $table->string('transaction_type', 30)
                ->index('cct_type_idx');

            $table->string('transaction_status', 30)
                ->index('cct_status_idx');

            $table->string('entry_type', 20)
                ->default('debit');

            // Amount
            $table->decimal('amount', 16, 2)
                ->default(0);

            $table->decimal('fee', 16, 2)
                ->default(0);

            $table->string('currency', 10)
                ->default('NGN');

            $table->unsignedBigInteger('amount_in_minor')
                ->nullable();

            // Merchant
            $table->string('merchant_name')
                ->nullable();

            $table->string('merchant_id')
                ->nullable();

            $table->string('merchant_category_code', 10)
                ->nullable();

            $table->string('merchant_city')
                ->nullable();

            $table->string('merchant_country')
                ->nullable();

            $table->string('channel', 30)
                ->nullable();

            // Card information
            $table->string('masked_pan')
                ->nullable();

            $table->string('card_last_four', 4)
                ->nullable();

            // Dates
            $table->timestamp('transaction_date')
                ->nullable();

            $table->timestamp('settled_at')
                ->nullable();

            $table->timestamp('authorized_at')
                ->nullable();

            // Additional provider data
            $table->json('metadata')
                ->nullable();

            $table->json('raw_response')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Composite Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['user_id', 'transaction_date'],
                'cct_user_date_idx'
            );

            $table->index(
                ['crypto_card_id', 'transaction_date'],
                'cct_card_date_idx'
            );

            $table->index(
                ['transaction_status', 'transaction_date'],
                'cct_status_date_idx'
            );

            /*
            |--------------------------------------------------------------------------
            | Additional Useful Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['card_provider', 'transaction_status'],
                'cct_provider_status_idx'
            );

            $table->index(
                ['provider_card_id', 'transaction_date'],
                'cct_provider_card_date_idx'
            );

            $table->index(
                ['provider_transaction_id', 'card_provider'],
                'cct_provider_tx_provider_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_card_transactions');
    }
};