<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crypto_card_holders', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | OWNERSHIP
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | CORE IDENTITY
            |--------------------------------------------------------------------------
            */

            $table->string('type', 30)
                ->default('individual')
                ->index();

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->boolean('is_approved')
                ->default(false)
                ->index();

            $table->string('name')
                ->nullable();

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            $table->string('other_names')
                ->nullable();

            $table->string('email')
                ->nullable()
                ->index();

            $table->string('phone_number', 30)
                ->nullable()
                ->index();

            $table->date('date_of_birth')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | IDENTITY / KYC
            |--------------------------------------------------------------------------
            */

            $table->string('identity_type', 50)
                ->nullable()
                ->index();

            $table->string('identity_number')
                ->nullable();

            $table->string('identity_country', 10)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | BILLING / RESIDENTIAL ADDRESS
            |--------------------------------------------------------------------------
            */

            $table->string('address_line1')
                ->nullable();

            $table->string('address_line2')
                ->nullable();

            $table->string('city')
                ->nullable();

            $table->string('state')
                ->nullable();

            $table->string('postal_code', 30)
                ->nullable();

            $table->string('country', 10)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | KYC DOCUMENTS
            |--------------------------------------------------------------------------
            */

            $table->text('id_front_url')
                ->nullable();

            $table->text('id_back_url')
                ->nullable();

            $table->text('address_verification_url')
                ->nullable();

            $table->json('other_documents')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | PROVIDER MAPPINGS
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | {
            |     "sudo": {
            |         "customer_id": "64a1...",
            |         "business_id": "...",
            |         "status": "active",
            |         "is_approved": true
            |     },
            |     "stripe": {
            |         "customer_id": "cus_...",
            |         "status": "active"
            |     }
            | }
            |
            */

            $table->json('provider_customers')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | METADATA / RAW PROVIDER RESPONSES
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')
                ->nullable();

            $table->json('raw_responses')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | COMPOSITE INDEXES
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['user_id', 'status'],
                'cch_user_status_idx'
            );

            $table->index(
                ['user_id', 'type'],
                'cch_user_type_idx'
            );

            $table->index(
                ['user_id', 'is_approved'],
                'cch_user_approved_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_card_holders');
    }
};