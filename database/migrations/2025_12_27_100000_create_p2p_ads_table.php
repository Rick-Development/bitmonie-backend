<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell']);
            $table->string('asset', 16); // USDT, BTC
            $table->string('fiat', 16); // NGN, USD
            $table->enum('price_type', ['fixed', 'floating'])->default('fixed');
            $table->decimal('price', 20, 8);
            $table->decimal('margin', 5, 2)->nullable(); // For floating price
            $table->decimal('total_amount', 20, 8);
            $table->decimal('available_amount', 20, 8);
            $table->decimal('min_limit', 20, 8);
            $table->decimal('max_limit', 20, 8);
            $table->json('payment_method_ids'); // [1,2,3]
            $table->text('terms')->nullable();
            $table->text('auto_reply')->nullable();
            $table->integer('time_limit')->default(15); // minutes
            $table->enum('status', ['online', 'offline', 'deleted'])->default('offline');
            $table->timestamps();
            
            $table->index(['status', 'asset', 'fiat']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('p2p_ads');
    }
};
