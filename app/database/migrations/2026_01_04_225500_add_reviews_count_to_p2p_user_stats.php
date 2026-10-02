<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('p2p_user_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('p2p_user_stats', 'reviews_count')) {
                $table->integer('reviews_count')->default(0)->after('rating');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p2p_user_stats', function (Blueprint $table) {
            if (Schema::hasColumn('p2p_user_stats', 'reviews_count')) {
                $table->dropColumn('reviews_count');
            }
        });
    }
};
