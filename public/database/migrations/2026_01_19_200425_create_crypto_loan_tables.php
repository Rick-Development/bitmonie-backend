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
        Schema::create('loan_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('asset'); // e.g. USDT, BTC (Decoupled from currencies table)
            $table->decimal('amount', 28, 8);
            $table->decimal('remaining_amount', 28, 8)->default(0);
            $table->decimal('min_interest_rate', 5, 2)->default(5.00); // Monthly %
            $table->integer('duration_days'); // 30, 60, 90, 180
            $table->enum('status', ['pending', 'matched', 'cancelled', 'completed'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('loan_borrow_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('asset'); // Asset to borrow
            $table->decimal('amount', 28, 8);
            $table->integer('duration_days');
            $table->string('collateral_asset'); // e.g. SOL
            $table->decimal('collateral_amount', 28, 8);
            $table->enum('status', ['pending', 'matched', 'cancelled', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->unsignedBigInteger('borrow_request_id')->nullable();
            $table->unsignedBigInteger('loan_offer_id')->nullable();
            $table->unsignedBigInteger('borrower_id');
            $table->unsignedBigInteger('lender_id');
            $table->string('asset');
            $table->decimal('amount', 28, 8);
            $table->string('collateral_asset');
            $table->decimal('collateral_amount', 28, 8);
            $table->decimal('interest_rate', 5, 2)->default(5.00); // Monthly %
            $table->decimal('total_interest', 28, 8);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['active', 'completed', 'overdue', 'liquidated'])->default('active');
            $table->timestamps();

            $table->foreign('borrow_request_id')->references('id')->on('loan_borrow_requests')->onDelete('set null');
            $table->foreign('loan_offer_id')->references('id')->on('loan_offers')->onDelete('set null');
            $table->foreign('borrower_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_borrow_requests');
        Schema::dropIfExists('loan_offers');
    }
};
