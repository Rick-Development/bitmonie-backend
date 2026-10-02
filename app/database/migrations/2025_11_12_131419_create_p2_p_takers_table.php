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
        Schema::create('p2p_takers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taker_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('maker_id')->nullable();
            $table->enum('type', ['buy', 'sell'])->nullable();
            $table->string('quote_currency')->nullable();
            $table->string('amount')->nullable();
            $table->string(column: 'price')->nullable();
            $table->string('total')->nullable();
            $table->enum('status', ['draft', 'open', 'accepted', 'funded', 'released', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('p2p_takers');
    }
};
