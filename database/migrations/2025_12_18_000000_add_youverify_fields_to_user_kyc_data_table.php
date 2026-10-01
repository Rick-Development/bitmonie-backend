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
        Schema::table('user_kyc_data', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('user_id');
            $table->string('youverify_reference')->nullable()->index()->after('provider');
            $table->string('youverify_status')->nullable()->after('youverify_reference');
            $table->longText('youverify_payload')->nullable()->after('youverify_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_kyc_data', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'youverify_reference',
                'youverify_status',
                'youverify_payload',
            ]);
        });
    }
};



