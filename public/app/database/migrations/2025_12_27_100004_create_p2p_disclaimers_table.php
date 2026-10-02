<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('p2p_disclaimers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'welcome', 'buyer_confirm', etc.
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['info', 'warning', 'critical'])->default('info');
            $table->boolean('requires_acceptance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('p2p_disclaimers');
    }
};
