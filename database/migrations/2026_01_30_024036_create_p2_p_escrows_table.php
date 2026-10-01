<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('p2p_escrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('ad_id')->nullable(); // constrain manually if needed or via FK
            $table->unsignedBigInteger('order_id')->nullable(); 
            
            $table->enum('type', ['ad_creation', 'order_locking']); // Reason for holding
            $table->string('asset', 20); // USDT, BTC
            $table->decimal('amount', 28, 8);
            $table->decimal('fee', 28, 8)->default(0); 
            
            $table->enum('status', ['held', 'released', 'refunded', 'disputed'])->default('held');
            
            $table->string('transaction_ref')->nullable(); // Quidax Withdrawal ID
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p2p_escrows');
    }
};
