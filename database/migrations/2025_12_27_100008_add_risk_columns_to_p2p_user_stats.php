<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('p2p_user_stats', function (Blueprint $table) {
            $table->decimal('risk_score', 5, 2)->default(0); // 0-100 (100 is highest risk)
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->integer('cancelled_orders_last_30d')->default(0);
        });
    }

    public function down()
    {
        Schema::table('p2p_user_stats', function (Blueprint $table) {
            $table->dropColumn(['risk_score', 'risk_level', 'cancelled_orders_last_30d']);
        });
    }
};
