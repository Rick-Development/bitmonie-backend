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
        Schema::create('crypto_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    $table->string('card_provider')->default('sudo');
    $table->string('card_provider_id')->nullable()->index();
    $table->string('card_provider_customer_id')->nullable()->index();
    $table->string('card_provider_account_id')->nullable();
    $table->string('card_provider_funding_source_id')->nullable();

    $table->string('card_type')->nullable();          // virtual | physical
    $table->string('card_brand')->nullable();
    $table->string('card_currency', 10)->nullable();
    $table->string('card_status')->default('active');

    $table->string('masked_pan')->nullable();
    $table->string('card_last_four', 4)->nullable();
    $table->string('expiry_month', 2)->nullable();
    $table->string('expiry_year', 4)->nullable();

    $table->unsignedBigInteger('spending_limits_amount')->nullable();
    $table->string('spending_limits_interval')->nullable(); // daily, weekly, monthly...
    $table->json('spending_controls')->nullable();

    $table->boolean('is_2fa_enrolled')->default(false);
    $table->boolean('is_default_pin_changed')->default(false);
    $table->boolean('is_disposable')->default(false);
    $table->boolean('is_deleted')->default(false);

    $table->json('metadata')->nullable();
    $table->json('raw_response')->nullable();

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
        Schema::dropIfExists('crypto_cards');
    }
};
