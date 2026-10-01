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
       Schema::create('crypto_card_webhook_logs', function (Blueprint $table) {
    $table->id();
    $table->string('provider')->default('sudo')->index();
    $table->string('event_type')->index();
    $table->string('event_id')->nullable()->index();
    $table->string('provider_card_id')->nullable()->index();
    $table->string('provider_customer_id')->nullable();
    $table->foreignId('crypto_card_id')->nullable()->constrained('crypto_cards')->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('direction')->default('inbound');
    $table->string('status')->default('received')->index();
    $table->unsignedSmallInteger('http_status')->nullable();
    $table->string('response_code', 10)->nullable();
    $table->string('decision')->nullable(); // approved / declined
    $table->decimal('amount', 16, 2)->nullable();
    $table->string('currency', 10)->nullable();
    $table->unsignedInteger('processing_time_ms')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->json('payload')->nullable();
    $table->json('response_body')->nullable();
    $table->text('error_message')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['provider', 'event_type', 'created_at']);
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crypto_card_webhook_logs');
    }
};
