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
        if (!Schema::hasTable('gift_card_trade_images')) {
            Schema::create('gift_card_trade_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trade_id')->constrained('gift_card_trade_submissions')->onDelete('cascade');
                $table->string('image_path');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_card_trade_images');
    }
};
