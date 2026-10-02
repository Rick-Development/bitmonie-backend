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
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default minimum USDT requirement
        DB::table('admin_settings')->insert([
            'setting_key' => 'merchant_min_usdt',
            'setting_value' => '100',
            'description' => 'Minimum USDT balance required to apply for merchant status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
