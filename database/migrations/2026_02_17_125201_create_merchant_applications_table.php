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
        Schema::create('merchant_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            
            // Contact Information
            $table->string('email');
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('business_name')->nullable();
            
            // USDT Balance Verification
            $table->decimal('quidax_usdt_balance', 20, 8);
            $table->decimal('min_usdt_required', 20, 8);
            $table->timestamp('balance_verified_at')->nullable();
            
            // Application Status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('admins')->onDelete('set null');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_applications');
    }
};
