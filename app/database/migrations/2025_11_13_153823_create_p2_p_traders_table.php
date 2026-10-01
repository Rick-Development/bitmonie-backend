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
        Schema::create('p2p_traders', function (Blueprint $table) {
            $table->id();
            $table->string('trader_name')->nullable();
            $table->string('trader_email')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->integer('amount')->nullable();
            $table->decimal('price', '36', '2')->nullable();
            $table->decimal('total', '36', '2')->nullable();
             $table->enum('status', ['active', 'inactive', 'banned'])->default('active');

            // Admin controls
            $table->boolean('is_blocked')->default(false);
            $table->text('reason_blocked')->nullable();
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('p2p_traders');
    }
};
