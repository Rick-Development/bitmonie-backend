<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('safe_locks', 'last_interest_date')) {
            Schema::table('safe_locks', function (Blueprint $table) {
                $table->timestamp('last_interest_date')->nullable()->after('interest_accrued');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('safe_locks', 'last_interest_date')) {
            Schema::table('safe_locks', function (Blueprint $table) {
                $table->dropColumn('last_interest_date');
            });
        }
    }
};
