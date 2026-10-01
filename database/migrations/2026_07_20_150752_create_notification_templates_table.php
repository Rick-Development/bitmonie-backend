<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Template Identity
            |--------------------------------------------------------------------------
            */

            $table->string('template_key')
                ->unique();

            /*
             0 = User
             1 = Admin
            */
            $table->unsignedTinyInteger('notify_for')
                ->default(0)
                ->index();


            /*
            |--------------------------------------------------------------------------
            | Localization
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('language_id')
                ->nullable()
                ->index();


            /*
            |--------------------------------------------------------------------------
            | Notification Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->string('subject')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Channel Contents
            |--------------------------------------------------------------------------
            */

            $table->longText('email')
                ->nullable();

            $table->longText('sms')
                ->nullable();

            $table->longText('push')
                ->nullable();

            $table->longText('in_app')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Channel Status
            |
            | Example:
            |
            | {
            |   "mail":true,
            |   "sms":true,
            |   "push":true,
            |   "in_app":true
            | }
            |--------------------------------------------------------------------------
            */

            $table->json('status')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Email Sender Override
            |--------------------------------------------------------------------------
            */

            $table->string('email_from')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Security / Transactional Flags
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_transactional')
                ->default(false);

            $table->boolean('is_security')
                ->default(false);


            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $table->timestamps();


            $table->index([
                'template_key',
                'notify_for',
                'language_id'
            ]);

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};