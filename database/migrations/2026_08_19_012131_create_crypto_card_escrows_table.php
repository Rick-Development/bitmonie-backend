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
       Schema::create('crypto_card_escrows', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('crypto_card_id')->constrained('crypto_cards')->cascadeOnDelete();
    $table->foreignId('crypto_card_transaction_id')->nullable()->constrained('crypto_card_transactions')->nullOnDelete();

    $table->string('card_provider')->default('sudo')->index();
    $table->string('provider_authorization_id')->nullable()->index();
    $table->string('provider_transaction_id')->nullable()->index();

    $table->string('type');                     // hold, capture, release, reversal...
    $table->string('direction');                // debit, credit
    $table->string('status')->default('pending')->index();

    $table->decimal('amount', 16, 2);
    $table->decimal('fee', 16, 2)->default(0);
    $table->string('currency', 10)->default('NGN');

    $table->decimal('master_account_impact', 16, 2)->nullable();
    $table->string('master_account_reference')->nullable();

    $table->string('description')->nullable();
    $table->string('merchant_name')->nullable();
    $table->string('merchant_id')->nullable();
    $table->string('merchant_category_code')->nullable();
    $table->string('channel')->nullable();

    $table->json('metadata')->nullable();
    $table->json('raw_response')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['crypto_card_id', 'status', 'type']);
    $table->index(['user_id', 'created_at']);
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crypto_card_escrows');
    }
};
