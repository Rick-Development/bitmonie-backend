<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_card_settings', function (Blueprint $table) {
            $table->id();

            // === Card Issuance ===
            $table->decimal('card_creation_fee', 10, 2)->default(3.00)->comment('Fixed USD fee charged to user when creating a card');
            $table->decimal('min_card_topup', 10, 2)->default(5.00)->comment('Minimum USD amount per top-up');
            $table->decimal('max_card_topup', 10, 2)->default(500.00)->comment('Maximum USD amount per top-up');
            $table->decimal('min_card_withdrawal', 10, 2)->default(1.00)->comment('Minimum USD amount per withdrawal from card');
            $table->decimal('max_card_withdrawal', 10, 2)->default(500.00)->comment('Maximum USD amount per withdrawal from card');

            // === Rate/Conversion ===
            $table->decimal('usdt_to_usd_rate', 10, 4)->default(1.0000)->comment('USDT to USD exchange rate used for card funding');
            $table->decimal('topup_fee_percent', 5, 2)->default(0.00)->comment('Percentage fee charged on each top-up (0 = free)');
            $table->decimal('withdrawal_fee_percent', 5, 2)->default(0.00)->comment('Percentage fee charged on each withdrawal');
            $table->decimal('fx_markup_percent', 5, 2)->default(2.00)->comment('FX markup percentage applied on card transactions');

            // === Card Limits ===
            $table->integer('max_cards_per_user')->default(1)->comment('Max number of active cards per user');
            $table->decimal('max_card_balance', 10, 2)->default(10000.00)->comment('Maximum balance allowed on a single card (USD)');

            // === Card Brand & Type Availability ===
            $table->boolean('visa_enabled')->default(true);
            $table->boolean('mastercard_enabled')->default(false);

            // === Feature Switches ===
            $table->boolean('card_creation_enabled')->default(true)->comment('Allow new card creation');
            $table->boolean('card_topup_enabled')->default(true);
            $table->boolean('card_withdrawal_enabled')->default(true);
            $table->boolean('card_freeze_enabled')->default(true);
            $table->boolean('card_terminate_enabled')->default(true);

            // === Notifications ===
            $table->boolean('email_on_topup')->default(true);
            $table->boolean('email_on_withdrawal')->default(true);
            $table->boolean('email_on_decline')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_card_settings');
    }
};
