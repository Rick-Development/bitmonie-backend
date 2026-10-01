<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('crypto_cards', function (Blueprint $table) {
            $table->decimal('card_balance', 16, 2)
                ->default(0)
                ->after('card_currency');

            $table->decimal('pending_holds', 16, 2)
                ->default(0)
                ->after('card_balance');

            $table->decimal('ledger_balance', 16, 2)
                ->nullable()
                ->after('pending_holds');

            $table->timestamp('last_balance_synced_at')
                ->nullable()
                ->after('ledger_balance');
        });
    }

    public function down()
    {
        Schema::table('crypto_cards', function (Blueprint $table) {
            $table->dropColumn([
                'card_balance',
                'pending_holds',
                'ledger_balance',
                'last_balance_synced_at',
            ]);
        });
    }
};