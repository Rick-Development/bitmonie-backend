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
        Schema::create('safe_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->default('Safe Lock');
            $table->decimal('amount', 20, 8);
            $table->decimal('interest_rate', 5, 2); // e.g. 12.50%
            $table->decimal('interest_accrued', 20, 8)->default(0);
            $table->timestamp('lock_date')->useCurrent();
            $table->timestamp('maturity_date')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->string('status')->default('active'); // active, matured, redeemed
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
        Schema::dropIfExists('safe_locks');
    }
};
