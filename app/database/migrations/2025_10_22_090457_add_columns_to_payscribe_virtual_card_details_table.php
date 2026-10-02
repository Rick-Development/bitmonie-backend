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
        Schema::table('payscribe_virtual_card_details', function (Blueprint $table) {
            //
            $table->string('card_status')->after('card_type')->nullable();
            $table->string('is_terminated')->after('prev_balance')->nullable();
            $table->string('termination_date')->after('is_terminated')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payscribe_virtual_card_details', function (Blueprint $table) {
            //
            $table->dropColumn(['card_status', 'is_terminated', 'termination_date']);
        });
    }
};
