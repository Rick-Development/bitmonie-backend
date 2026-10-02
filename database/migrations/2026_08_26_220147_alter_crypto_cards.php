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
        Schema::table('crypto_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('crypto_cards', 'card_holder_id')) {
                $table->unsignedBigInteger('card_holder_id')
                    ->nullable()
                    ->after('id');

                $table->index('card_holder_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crypto_cards', function (Blueprint $table) {
            if (Schema::hasColumn('crypto_cards', 'card_holder_id')) {
                $table->dropIndex(['card_holder_id']);
                $table->dropColumn('card_holder_id');
            }
        });
    }
};