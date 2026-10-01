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
            if (!Schema::hasColumn('user_wallets', 'reserved')) {
                $table->decimal('reserved', 20, 8)->default(0)->after('balance');
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
        Schema::table('user_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('user_wallets', 'reserved')) {
                $table->dropColumn('reserved');
            }
        });
    }
};
