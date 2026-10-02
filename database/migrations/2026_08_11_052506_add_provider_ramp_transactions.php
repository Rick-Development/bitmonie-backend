<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ramp_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('ramp_transactions', 'provider')) {
                $table->string('provider', 32)
                    ->nullable()
                    ->default('quidax')
                    ->after('type')
                    ->index();
            }
        });

        // Backfill existing rows as Quidax
        if (Schema::hasColumn('ramp_transactions', 'provider')) {
            DB::table('ramp_transactions')
                ->whereNull('provider')
                ->update(['provider' => 'quidax']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ramp_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('ramp_transactions', 'provider')) {
                $table->dropIndex(['provider']);
                $table->dropColumn('provider');
            }
        });
    }
};