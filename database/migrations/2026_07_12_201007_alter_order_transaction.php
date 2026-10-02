<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            // 1. Ensure the column type supports indexing (varchar standard instead of text)
            $table->string('reference', 150)->nullable()->change();

            // 2. Add the composite performance index for idempotent checks
            $table->index(['reference', 'user_wallet_id'], 'idx_order_tx_ref_wallet');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            // 1. Drop the composite performance index safely
            $table->dropIndex('idx_order_tx_ref_wallet');

            // 2. Revert to a standard nullable string if needed (adjust size if previous was default 255)
            $table->string('reference', 255)->nullable()->change();
        });
    }
};