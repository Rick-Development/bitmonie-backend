<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('p2p_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('p2p_chats');
    }
};
