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
        Schema::create('crypto_card_setups', function (Blueprint $table) {
            $table->id();

            // Card fees (USD)
            $table->decimal('physical_card_fee', 15, 2)->default(0);
            $table->decimal('card_issuance_fee', 15, 2)->default(0);
            $table->decimal('card_funding_fee', 15, 2)->default(0);
            $table->decimal('monthly_card_maintenance_fee', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_card_setups');
    }
};