<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_withdrawal_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_withdrawal_requests', 'method')) {
                $table->string('method', 20)->default('bank')->after('currency_code');
            }
            if (! Schema::hasColumn('wallet_withdrawal_requests', 'crypto_currency')) {
                $table->string('crypto_currency', 20)->nullable()->after('method');
            }
            if (! Schema::hasColumn('wallet_withdrawal_requests', 'network')) {
                $table->string('network', 40)->nullable()->after('crypto_currency');
            }
            if (! Schema::hasColumn('wallet_withdrawal_requests', 'wallet_address')) {
                $table->string('wallet_address', 255)->nullable()->after('network');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_withdrawal_requests', function (Blueprint $table) {
            foreach (['wallet_address', 'network', 'crypto_currency', 'method'] as $column) {
                if (Schema::hasColumn('wallet_withdrawal_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
