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
        Schema::create('target_savings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->decimal('target_amount', 20, 8);
            $table->decimal('current_balance', 20, 8)->default(0);
            $table->decimal('interest_accrued', 20, 8)->default(0);
            
            // Auto-Save Configuration
            $table->string('frequency')->nullable(); // daily, weekly, monthly
            $table->decimal('auto_save_amount', 20, 8)->nullable();
            $table->timestamp('next_save_date')->nullable();

            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('target_date')->nullable();
            
            $table->string('status')->default('active'); // active, completed, broken
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
        Schema::dropIfExists('target_savings');
    }
};
