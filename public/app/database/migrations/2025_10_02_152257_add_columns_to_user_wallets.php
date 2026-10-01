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
        Schema::table('user_wallets', function (Blueprint $table) {
            //
            $table->uuid()->unique()->nullable()->after('id');
            $table->string('currency_code')->nullable();
            $table->tinyInteger('default')->default(0)->comment('1 = Yes, 0 = No');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_wallets', function (Blueprint $table) {
            //
            $table->dropColumn('uuid');
        });
    }
};
