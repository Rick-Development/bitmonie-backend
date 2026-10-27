<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usdt_easyearn_investments')) {
            Schema::table('usdt_easyearn_investments', function (Blueprint $table) {
                if (!Schema::hasColumn('usdt_easyearn_investments', 'duration_days')) {
                    $table->integer('duration_days')->default(365)->after('duration_months')->comment('Plan duration in days: 30, 60, or 365');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usdt_easyearn_investments')) {
            Schema::table('usdt_easyearn_investments', function (Blueprint $table) {
                if (Schema::hasColumn('usdt_easyearn_investments', 'duration_days')) {
                    $table->dropColumn('duration_days');
                }
            });
        }
    }
};
