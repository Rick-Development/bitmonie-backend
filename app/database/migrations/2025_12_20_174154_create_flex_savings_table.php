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
        Schema::create('flex_savings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique(); // One flex account per user usually
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('accrued_interest', 20, 8)->default(0);
            $table->boolean('auto_save')->default(false); // e.g. auto-debit from standard wallet
            $table->boolean('status')->default(true);
            $table->timestamp('last_interest_date')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('flex_savings');
    }
};
