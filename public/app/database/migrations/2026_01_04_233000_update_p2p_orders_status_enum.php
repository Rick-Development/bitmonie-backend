<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add 'paid' to the enum list
        DB::statement("ALTER TABLE p2p_orders MODIFY COLUMN status ENUM('draft', 'open', 'accepted', 'funded', 'released', 'completed', 'cancelled', 'paid') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert to original enum (warning: 'paid' rows might be truncated or fail)
        // We generally don't revert enum updates that add values if data exists.
    }
};
