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
        // Settings table
        Schema::create('usdt_easyearn_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('current_monthly_rate', 5, 2)->default(10.00)->comment('Monthly interest rate 5-10%');
            $table->decimal('min_investment', 20, 8)->default(10.00);
            $table->decimal('max_investment', 20, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('payout_day')->default(10)->comment('Day of month for payouts');
            $table->boolean('auto_credit_enabled')->default(false);
            $table->timestamp('updated_at')->nullable();
        });

        // Investments table
        Schema::create('usdt_easyearn_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 20, 8)->comment('Principal amount in USDT');
            $table->tinyInteger('duration_months')->default(12);
            $table->decimal('interest_rate', 5, 2)->comment('Monthly interest rate at time of investment');
            $table->boolean('auto_compound')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->decimal('total_interest_earned', 20, 8)->default(0);
            $table->date('last_interest_credit_date')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('end_date');
        });

        // Interest credits table
        Schema::create('usdt_easyearn_interest_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('usdt_easyearn_investments')->onDelete('cascade');
            $table->decimal('amount', 20, 8)->comment('Interest amount credited');
            $table->date('credit_date');
            $table->foreignId('credited_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['investment_id', 'credit_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usdt_easyearn_interest_credits');
        Schema::dropIfExists('usdt_easyearn_investments');
        Schema::dropIfExists('usdt_easyearn_settings');
    }
};
