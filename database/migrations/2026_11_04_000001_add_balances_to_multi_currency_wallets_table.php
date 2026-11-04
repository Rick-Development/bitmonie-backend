<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('multi_currency_wallets', function (Blueprint $table): void {
            if (! Schema::hasColumn('multi_currency_wallets', 'available_balance')) {
                $table->decimal('available_balance', 20, 8)
                    ->default(0)
                    ->after('status');
            }

            if (! Schema::hasColumn('multi_currency_wallets', 'ledger_balance')) {
                $table->decimal('ledger_balance', 20, 8)
                    ->default(0)
                    ->after('available_balance');
            }

            if (! Schema::hasColumn('multi_currency_wallets', 'locked_balance')) {
                $table->decimal('locked_balance', 20, 8)
                    ->default(0)
                    ->after('ledger_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('multi_currency_wallets', function (Blueprint $table): void {
            foreach (['available_balance', 'ledger_balance', 'locked_balance'] as $column) {
                if (Schema::hasColumn('multi_currency_wallets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
