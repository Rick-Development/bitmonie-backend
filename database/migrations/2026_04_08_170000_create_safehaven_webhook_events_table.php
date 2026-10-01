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
        Schema::create('safehaven_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_reference')->unique();
            $table->string('event_type')->nullable();
            $table->string('webhook_type')->nullable();
            $table->string('direction')->nullable();
            $table->string('account_number')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('user_wallets')->nullOnDelete();
            $table->decimal('credit_amount', 28, 8)->nullable();
            $table->decimal('balance_after', 28, 8)->nullable();
            $table->string('status')->default('processing');
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
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
        Schema::dropIfExists('safehaven_webhook_events');
    }
};
