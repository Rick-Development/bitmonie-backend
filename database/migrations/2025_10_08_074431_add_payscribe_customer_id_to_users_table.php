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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('payscribe_customer_id')->nullable()->unique()->after('account_no');
            $table->string('payscribe_tier')->nullable()->after('payscribe_customer_id');
            $table->string('payscribe_customer_phone')->nullable()->after('payscribe_tier')->unique();
            $table->string('payscribe_customer_country')->nullable()->after('payscribe_customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn('payscribe_customer_id');
            $table->dropColumn('payscribe_tier', 'payscribe_customer_phone', 'payscribe_customer_country');
        });
    }
};
