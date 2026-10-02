<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table) {

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
            | Notification identification
            |--------------------------------------------------------------------------
            */

            $table->string('template_key')
                ->nullable()
                ->index();

            /*
             * Examples:
             *
             * P2P_ORDER_CREATED
             * WALLET_DEPOSIT_SUCCESS
             * LOGIN_SUCCESS
             * KYC_APPROVED
             */


            $table->string('type')
                ->default('general')
                ->index();

            /*
             * Examples:
             *
             * transaction
             * security
             * order
             * promotion
             * system
             */


            /*
            |--------------------------------------------------------------------------
            | Message
            |--------------------------------------------------------------------------
            */

            $table->string('title')
                ->nullable();


            $table->text('message');


            /*
            |--------------------------------------------------------------------------
            | Action payload
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | {
            |   "route":"p2p/order/123",
            |   "icon":"fa fa-wallet",
            |   "button":"View Order"
            | }
            |
            */

            $table->json('action')
                ->nullable();



            /*
            |--------------------------------------------------------------------------
            | Related business object
            |--------------------------------------------------------------------------
            |
            | Allows linking notification to:
            |
            | P2P Order
            | Wallet transaction
            | Payment
            |
            */

            $table->string('reference_type')
                ->nullable()
                ->index();


            $table->string('reference_id')
                ->nullable()
                ->index();


            /*
            |--------------------------------------------------------------------------
            | Delivery status
            |--------------------------------------------------------------------------
            */

            $table->timestamp('read_at')
                ->nullable();


            $table->timestamp('delivered_at')
                ->nullable();



            /*
            |--------------------------------------------------------------------------
            | Priority
            |--------------------------------------------------------------------------
            */

            $table->string('priority')
                ->default('normal');

            /*
             * low
             * normal
             * high
             * urgent
             */



            /*
            |--------------------------------------------------------------------------
            | Cleanup
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();


            $table->timestamps();



            /*
            |--------------------------------------------------------------------------
            | Performance indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'user_id',
                'read_at',
                'created_at'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
    }
};