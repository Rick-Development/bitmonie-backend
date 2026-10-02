<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('p2p_user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('total_trades')->default(0);
            $table->integer('completed_trades')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->integer('avg_release_time_minutes')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('disputes_raised')->default(0);
            $table->integer('disputes_won')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('p2p_user_stats');
    }
};
