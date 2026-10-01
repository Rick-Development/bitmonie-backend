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
        Schema::table('p2p_traders', function (Blueprint $table) {
            //
            $table->enum('type', ['buy', 'sell'])->after('supported_currencies');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('p2p_traders', function (Blueprint $table) {
            //
            $table->dropColumn('type');
        });
    }
};
