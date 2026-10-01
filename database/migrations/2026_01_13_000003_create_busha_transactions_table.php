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
        Schema::create('busha_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('busha_order_id')->nullable();
            $table->string('type'); // buy, sell
            $table->string('pair'); // BTC-NGN
            $table->decimal('amount', 20, 8); // Crypto amount
            $table->decimal('total', 20, 2); // Fiat Cost/Proceeds
            $table->decimal('rate', 20, 2);
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
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
        Schema::dropIfExists('busha_transactions');
    }
};
