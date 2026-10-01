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
        Schema::create('payscribe_virtual_card_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('card_id');
            $table->string('card_name');
            $table->string('card_number');
            $table->string('card_type');
            $table->string('currency');
            $table->string('brand');
            $table->string('masked');
            $table->string('expiry_date');
            $table->string('ccv');
            $table->json('billing_address');
            $table->string('trans_id');
            $table->string('ref')->nullable();
            $table->string('balance')->nullable();
            $table->string('prev_balance')->nullable();
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
        Schema::dropIfExists('payscribe_virtual_card_details');
    }
};
