<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('multi_currency_wallets', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Owner
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Application Wallet
            |--------------------------------------------------------------------------
            */

            $table->char('currency', 3);

            $table->enum('account_type', [
                'individual',
                'corporate',
            ])->default('individual');

            $table->string('provider', 50);


            /*
            |--------------------------------------------------------------------------
            | Fincra / Provider Business
            |--------------------------------------------------------------------------
            */

            $table->string('provider_business_id')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Local / Virtual Account
            |--------------------------------------------------------------------------
            */

            $table->string('virtual_account_number')
                ->nullable();

            $table->string('account_name')
                ->nullable();

            $table->string('bank_name')
                ->nullable();

            $table->string('bank_code')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Provider Wallet
            |--------------------------------------------------------------------------
            |
            | Fincra uses a wallet number for account-to-account
            | transfers.
            |
            */

            $table->string('provider_wallet_number')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Provider References
            |--------------------------------------------------------------------------
            */

            $table->string('provider_account_reference')
                ->nullable();

            $table->string('provider_account_id')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'active',
                'suspended',
                'blocked',
                'closed',
                'failed',
            ])->default('pending');


            /*
            |--------------------------------------------------------------------------
            | Capabilities
            |--------------------------------------------------------------------------
            */

            $table->boolean('can_receive')
                ->default(true);

            $table->boolean('can_send')
                ->default(true);


            /*
            |--------------------------------------------------------------------------
            | Provider Data
            |--------------------------------------------------------------------------
            */

            $table->json('provider_metadata')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'user_id',
                'currency',
            ]);

            $table->index([
                'provider',
                'currency',
            ]);

            $table->index('provider_business_id');

            $table->index('provider_account_reference');

            $table->index('provider_account_id');

            $table->index('provider_wallet_number');

            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('multi_currency_wallets');
    }
};