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
        Schema::table('p2p_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('p2p_orders', 'asset')) {
                $table->string('asset', 16)->nullable()->after('type');
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
        Schema::table('p2p_orders', function (Blueprint $table) {
            if (Schema::hasColumn('p2p_orders', 'asset')) {
                $table->dropColumn('asset');
            }
        });
    }
};
