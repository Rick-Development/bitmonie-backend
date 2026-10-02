<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('resident_country_iso_code', 2)->nullable()->after('address');
        });

        // Fill existing records with Nigeria's ISO code ('NG')
        DB::table('users')->whereNull('resident_country_iso_code')->update([
            'resident_country_iso_code' => 'NG'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('resident_country_iso_code');
        });
    }
};