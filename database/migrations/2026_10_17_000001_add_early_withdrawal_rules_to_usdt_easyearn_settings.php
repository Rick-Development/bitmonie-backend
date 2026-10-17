<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usdt_easyearn_settings')) {
            Schema::table('usdt_easyearn_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('usdt_easyearn_settings', 'early_withdrawal_enabled')) {
                    $table->boolean('early_withdrawal_enabled')->default(true);
                }
                if (!Schema::hasColumn('usdt_easyearn_settings', 'early_withdrawal_penalty_percent')) {
                    $table->decimal('early_withdrawal_penalty_percent', 5, 2)->default(5.00)->comment('Penalty fee % for early withdrawal');
                }
                if (!Schema::hasColumn('usdt_easyearn_settings', 'base_apy')) {
                    $table->decimal('base_apy', 5, 2)->default(15.00)->comment('Base vault APY e.g. 15%');
                }
                if (!Schema::hasColumn('usdt_easyearn_settings', 'power_bonus_multiplier')) {
                    $table->decimal('power_bonus_multiplier', 5, 2)->default(2.00)->comment('2X PowerBonus multiplier if held to maturity');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usdt_easyearn_settings')) {
            Schema::table('usdt_easyearn_settings', function (Blueprint $table) {
                $columns = ['early_withdrawal_enabled', 'early_withdrawal_penalty_percent', 'base_apy', 'power_bonus_multiplier'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('usdt_easyearn_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
