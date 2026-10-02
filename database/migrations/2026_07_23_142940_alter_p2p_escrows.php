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
        Schema::table('p2p_escrows', function (Blueprint $table) {

            // Distinguishes crypto from fiat
            $table->enum('currency_type', ['crypto', 'fiat'])
                ->nullable()
                ->after('asset');

            $table->timestamp('refunded_at')
                ->nullable();
            // Optional remarks
            $table->text('notes')
                ->nullable()
                ->after('transaction_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_escrows', function (Blueprint $table) {

            $table->dropForeign(['counterparty_user_id']);

            $table->dropColumn([
                'currency_type',
                'refunded_at',
                'notes',
            ]);
        });
    }
};