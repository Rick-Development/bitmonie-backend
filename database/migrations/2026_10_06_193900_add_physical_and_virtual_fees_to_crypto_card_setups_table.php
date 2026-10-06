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
        Schema::table('crypto_card_setups', function (Blueprint $table) {
            // Physical card specific fees
            if (!Schema::hasColumn('crypto_card_setups', 'physical_card_funding_fee')) {
                $table->decimal('physical_card_funding_fee', 15, 2)->default(0)->after('physical_card_fee');
            }
            if (!Schema::hasColumn('crypto_card_setups', 'physical_monthly_card_maintenance_fee')) {
                $table->decimal('physical_monthly_card_maintenance_fee', 15, 2)->default(0)->after('physical_card_funding_fee');
            }

            // Virtual card specific fees
            if (!Schema::hasColumn('crypto_card_setups', 'virtual_card_issuance_fee')) {
                $table->decimal('virtual_card_issuance_fee', 15, 2)->default(0)->after('physical_monthly_card_maintenance_fee');
            }
            if (!Schema::hasColumn('crypto_card_setups', 'virtual_card_funding_fee')) {
                $table->decimal('virtual_card_funding_fee', 15, 2)->default(0)->after('virtual_card_issuance_fee');
            }
            if (!Schema::hasColumn('crypto_card_setups', 'virtual_monthly_card_maintenance_fee')) {
                $table->decimal('virtual_monthly_card_maintenance_fee', 15, 2)->default(0)->after('virtual_card_funding_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crypto_card_setups', function (Blueprint $table) {
            $columns = [
                'physical_card_funding_fee',
                'physical_monthly_card_maintenance_fee',
                'virtual_card_issuance_fee',
                'virtual_card_funding_fee',
                'virtual_monthly_card_maintenance_fee',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('crypto_card_setups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
