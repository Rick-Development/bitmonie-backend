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
        // 1. Categories
        if (!Schema::hasTable('gift_card_trade_categories')) {
            Schema::create('gift_card_trade_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        // 2. Types
        if (!Schema::hasTable('gift_card_trade_types')) {
            Schema::create('gift_card_trade_types', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Physical or Digital
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        // 3. Countries
        if (!Schema::hasTable('gift_card_trade_countries')) {
            Schema::create('gift_card_trade_countries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 3)->unique(); // ISO code
                $table->string('flag_icon')->nullable();
                $table->timestamps();
            });
        }

        // 4. Rates
        if (!Schema::hasTable('gift_card_trade_rates')) {
            Schema::create('gift_card_trade_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('gift_card_trade_categories')->onDelete('cascade');
                $table->foreignId('type_id')->constrained('gift_card_trade_types')->onDelete('cascade');
                $table->foreignId('country_id')->constrained('gift_card_trade_countries')->onDelete('cascade');
                $table->decimal('min_amount', 10, 2)->default(0);
                $table->decimal('max_amount', 10, 2)->default(0);
                $table->decimal('rate_per_dollar', 10, 2); // NGN per $1
                $table->string('currency', 10)->default('USD');
                $table->boolean('status')->default(true);
                $table->timestamps();
                
                $table->unique(['category_id', 'type_id', 'country_id']);
            });
        }

        // 5. User Submissions (Trades)
        if (!Schema::hasTable('gift_card_trade_submissions')) {
            Schema::create('gift_card_trade_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('gift_card_trade_categories');
                $table->foreignId('type_id')->constrained('gift_card_trade_types');
                $table->foreignId('country_id')->constrained('gift_card_trade_countries');
                $table->foreignId('rate_id')->nullable()->constrained('gift_card_trade_rates');
                $table->decimal('card_amount', 10, 2);
                $table->string('card_currency', 10)->default('USD');
                $table->decimal('ngn_amount', 28, 8);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->text('admin_note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->string('card_code')->nullable(); // For digital cards
                $table->timestamps();
                
                $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
            });
        }

        // 6. Trade Images
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
        Schema::dropIfExists('gift_card_trade_submissions');
        Schema::dropIfExists('gift_card_trade_rates');
        Schema::dropIfExists('gift_card_trade_countries');
        Schema::dropIfExists('gift_card_trade_types');
        Schema::dropIfExists('gift_card_trade_categories');
    }
};
