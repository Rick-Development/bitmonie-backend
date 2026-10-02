<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE usdt_easyearn_investments
            MODIFY COLUMN status
            ENUM('active', 'completed', 'cancelled', 'terminated')
            NOT NULL
            DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE usdt_easyearn_investments
            MODIFY COLUMN status
            ENUM('active', 'completed', 'cancelled')
            NOT NULL
            DEFAULT 'active'
        ");
    }
};