<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('p2p_disclaimer_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disclaimer_id')->constrained('p2p_disclaimers')->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'disclaimer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('p2p_disclaimer_acceptances');
    }
};
