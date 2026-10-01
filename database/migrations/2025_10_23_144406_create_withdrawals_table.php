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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('reference')->nullable();
            $table->string('type')->nullable();
            $table->string('currency')->nullable();
            $table->string('amount')->nullable();
            $table->string('fee')->nullable();
            $table->string('total')->nullable();
            $table->string('trans_id')->nullable();
            $table->string('transaction_note')->nullable();
            $table->json('recipient_data')->nullable();
            $table->json('wallet')->nullable();
            $table->json('user')->nullable();
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
        Schema::dropIfExists('withdrawals');
    }
};
