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
        Schema::create('graph_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('graph_wallet_id')->nullable();
            $table->string('transaction_id')->unique()->comment('Transaction ID from Graph API');
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'conversion'])->default('deposit');
            $table->decimal('amount', 28, 8);
            $table->string('currency', 10);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('graph_wallet_id')->references('id')->on('graph_wallets')->onDelete('set null');
            
            $table->index(['user_id', 'type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('graph_transactions');
    }
};
