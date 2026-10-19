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
                if (!Schema::hasColumn('usdt_easyearn_investments', 'initial_deposit_amount')) {
                    $table->decimal('initial_deposit_amount', 20, 8)->nullable()->after('amount')->comment('Original principal invested');
                }
                if (!Schema::hasColumn('usdt_easyearn_investments', 'power_bonus_multiplier')) {
                    $table->decimal('power_bonus_multiplier', 5, 2)->default(2.00)->after('auto_compound')->comment('PowerBonus multiplier locked at creation');
                }
                if (!Schema::hasColumn('usdt_easyearn_investments', 'power_bonus_amount')) {
                    $table->decimal('power_bonus_amount', 20, 8)->default(0)->after('total_interest_earned')->comment('PowerBonus credited at maturity');
                }
                if (!Schema::hasColumn('usdt_easyearn_investments', 'power_bonus_credited_at')) {
                    $table->timestamp('power_bonus_credited_at')->nullable()->after('power_bonus_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usdt_easyearn_investments')) {
            Schema::table('usdt_easyearn_investments', function (Blueprint $table) {
                $columns = ['initial_deposit_amount', 'power_bonus_multiplier', 'power_bonus_amount', 'power_bonus_credited_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('usdt_easyearn_investments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
