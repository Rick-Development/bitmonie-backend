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
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->json('payment_method_ids')
                  ->nullable()
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->json('payment_method_ids')
                  ->nullable(false)
                  ->change();
        });
    }
};