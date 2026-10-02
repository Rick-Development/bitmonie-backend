<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE p2p_escrows
            MODIFY COLUMN status
            ENUM('pending', 'held', 'released', 'refunded', 'disputed')
            NOT NULL DEFAULT 'held'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE p2p_escrows
            MODIFY COLUMN status
            ENUM('held', 'released', 'refunded', 'disputed')
            NOT NULL DEFAULT 'held'
        ");
    }
};