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
        Schema::table('basic_settings', function (Blueprint $table) {
            // Sudo credentials & settlement configuration
            $table->string('sudo_base_url')->nullable();
            $table->text('sudo_api_key')->nullable();
            $table->string('sudo_settlement_bank_code')->nullable();
            $table->string('sudo_settlement_account_number')->nullable();
            $table->string('sudo_settlement_account_name')->nullable();
            $table->string('sudo_settlement_debit_account_id')->nullable();

            // Fincra credentials & configuration
            $table->string('fincra_base_url')->nullable();
            $table->text('fincra_secret_key')->nullable();
            $table->text('fincra_public_key')->nullable();
            $table->text('fincra_webhook_encryption_key')->nullable();
            $table->string('fincra_business_id')->nullable();
            $table->string('fincra_settlement_destination')->nullable();

            // SafeHaven bank code
            $table->string('safehaven_bank_code')->nullable();
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
            $table->dropColumn([
                'sudo_base_url',
                'sudo_api_key',
                'sudo_settlement_bank_code',
                'sudo_settlement_account_number',
                'sudo_settlement_account_name',
                'sudo_settlement_debit_account_id',
                'fincra_base_url',
                'fincra_secret_key',
                'fincra_public_key',
                'fincra_webhook_encryption_key',
                'fincra_business_id',
                'fincra_settlement_destination',
                'safehaven_bank_code',
            ]);
        });
    }
};
