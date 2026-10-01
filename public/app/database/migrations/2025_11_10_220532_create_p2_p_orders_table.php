<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('p2p_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('taker_id')->nullable()->constrained('users');
            $table->enum('type', ['buy', 'sell']);
            // $table->string('asset', 16); // base asset
            $table->string('quote_currency', 16); // quote currency
            $table->string('amount');
            $table->decimal('price', 36, 2); // price per base in quote
            $table->decimal('total', 36, 2);
            $table->boolean('escrow_enabled')->default(true);
            $table->enum('status', ['draft', 'open', 'accepted', 'funded', 'released', 'completed', 'cancelled'])->default('draft');
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['status','quote_currency']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('p2_p_orders');
    }
};
