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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('merchant_status', ['none', 'pending', 'approved', 'rejected'])
                  ->default('none')
                  ->after('kyc_tier');
            $table->timestamp('merchant_approved_at')->nullable()->after('merchant_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['merchant_status', 'merchant_approved_at']);
        });
    }
};
