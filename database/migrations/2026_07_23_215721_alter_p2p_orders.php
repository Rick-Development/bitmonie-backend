<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::table('p2p_orders', function (Blueprint $table) {


    $table->foreignId('crypto_seller_id')
        ->nullable()
        ->after('taker_id')
        ->constrained('users')
        ->cascadeOnDelete();


    $table->foreignId('crypto_buyer_id')
        ->nullable()
        ->after('crypto_seller_id')
        ->constrained('users')
        ->cascadeOnDelete();


    $table->foreignId('fiat_buyer_id')
        ->nullable()
        ->after('crypto_buyer_id')
        ->constrained('users')
        ->cascadeOnDelete();


    $table->foreignId('fiat_seller_id')
        ->nullable()
        ->after('fiat_buyer_id')
        ->constrained('users')
        ->cascadeOnDelete();


    $table->foreignId('fiat_escrow_id')
        ->nullable()
        ->after('escrow_enabled')
        ->constrained('p2p_escrows')
        ->nullOnDelete();


    $table->foreignId('crypto_escrow_id')
        ->nullable()
        ->after('fiat_escrow_id')
        ->constrained('p2p_escrows')
        ->nullOnDelete();

});
    }
public function down(): void
{
    Schema::table('p2p_orders', function (Blueprint $table) {


        $table->dropForeign(['crypto_seller_id']);
        $table->dropForeign(['crypto_buyer_id']);

        $table->dropForeign(['fiat_buyer_id']);
        $table->dropForeign(['fiat_seller_id']);

        $table->dropForeign(['fiat_escrow_id']);
        $table->dropForeign(['crypto_escrow_id']);


        $table->dropColumn([

            'crypto_seller_id',
            'crypto_buyer_id',

            'fiat_buyer_id',
            'fiat_seller_id',

            'fiat_escrow_id',
            'crypto_escrow_id',
        ]);

    });
}
};