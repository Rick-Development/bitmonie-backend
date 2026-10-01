<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->boolean('identity_encrypted')
                ->default(false)
                ->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->dropColumn('identity_encrypted');
        });
    }
};
