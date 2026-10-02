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
        Schema::create('locked_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 18, 2);
            // $table->string('transaction_pin')->nullable();
            $table->string('reason')->nullable(); // e.g. "pending withdrawal"
            $table->enum('status', ['locked', 'released', 'deducted', 'expired'])->default('locked');
            $table->timestamp('locked_until')->nullable();
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
        Schema::dropIfExists('locked_funds');
    }
};
