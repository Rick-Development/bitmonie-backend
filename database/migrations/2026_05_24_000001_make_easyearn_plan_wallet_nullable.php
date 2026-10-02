<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('easyearn_plans', function (Blueprint $table) {
            $table->dropForeign(['user_wallet_id']);
        });

        DB::statement('ALTER TABLE easyearn_plans MODIFY user_wallet_id BIGINT UNSIGNED NULL');

        Schema::table('easyearn_plans', function (Blueprint $table) {
            $table->foreign('user_wallet_id')->references('id')->on('user_wallets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('easyearn_plans', function (Blueprint $table) {
            $table->dropForeign(['user_wallet_id']);
        });

        DB::statement('ALTER TABLE easyearn_plans MODIFY user_wallet_id BIGINT UNSIGNED NOT NULL');

        Schema::table('easyearn_plans', function (Blueprint $table) {
            $table->foreign('user_wallet_id')->references('id')->on('user_wallets')->cascadeOnDelete();
        });
    }
};
