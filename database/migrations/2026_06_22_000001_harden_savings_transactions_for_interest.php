<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('savings_transactions')) {
            return;
        }

        DB::statement('ALTER TABLE savings_transactions MODIFY amount DECIMAL(20,8) NOT NULL');
        DB::statement('ALTER TABLE savings_transactions MODIFY type VARCHAR(40) NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('savings_transactions')) {
            return;
        }

        DB::statement('ALTER TABLE savings_transactions MODIFY amount DECIMAL(15,2) NOT NULL');
        DB::statement("ALTER TABLE savings_transactions MODIFY type ENUM('deposit','withdrawal') NOT NULL");
    }
};
