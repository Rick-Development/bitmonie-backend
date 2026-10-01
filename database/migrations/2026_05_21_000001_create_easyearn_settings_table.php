<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easyearn_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('return_multiplier', 18, 8)->default('2.00000000');
            $table->unsignedInteger('term_days')->default(30);
            $table->boolean('early_withdrawal_enabled')->default(false);
            $table->decimal('early_withdrawal_penalty_percent', 8, 4)->default('100.0000');
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        DB::table('easyearn_settings')->insert([
            'return_multiplier' => '2.00000000',
            'term_days' => 30,
            'early_withdrawal_enabled' => false,
            'early_withdrawal_penalty_percent' => '100.0000',
            'terms' => 'USDT is locked until maturity. At maturity, the configured PowerBonus return is credited to the user USDT wallet. Early withdrawal is locked by default.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('easyearn_settings');
    }
};
