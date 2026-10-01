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
        Schema::table('basic_settings', function (Blueprint $table) {
            $table->string('kyc_provider')->default('youverify');
            $table->string('safehaven_client_id')->nullable();
            $table->text('safehaven_client_assertion')->nullable();
            $table->string('safehaven_api_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('basic_settings', function (Blueprint $table) {
            $table->dropColumn(['kyc_provider', 'safehaven_client_id', 'safehaven_client_assertion', 'safehaven_api_url']);
        });
    }
};
