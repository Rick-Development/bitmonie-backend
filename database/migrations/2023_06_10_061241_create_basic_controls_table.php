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
        // Schema::create('basic_controls', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('theme')->nullable();
        //     $table->string('site_title')->nullable();
        //     $table->string('primary_color')->nullable();
        //     $table->string('secondary_color')->nullable();
        //     $table->string('time_zone')->nullable();
        //     $table->string('base_currency')->nullable();
        //     $table->string('currency_symbol')->nullable();
        //     $table->string('currency_rate')->nullable();
        //     $table->string('admin_prefix')->nullable();
        //     $table->enum('is_currency_position', ['left', 'right'])->default('left');
        //     $table->boolean('has_space_between_currency_and_amount')->default(false);
        //     $table->boolean('is_force_ssl')->default(false);
        //     $table->boolean('is_maintenance_mode')->default(false);
        //     $table->integer('paginate')->nullable();
        //     $table->boolean('strong_password')->default(false);
        //     $table->boolean('registration')->default(false);
        //     $table->integer('fraction_number')->nullable();
        //     $table->string('sender_email', 255)->nullable();
        //     $table->string('sender_email_name', 255)->nullable();
        //     $table->text('email_description')->nullable();
        //     $table->boolean('push_notification')->default(false);
        //     $table->boolean('in_app_notification')->default(false);
        //     $table->boolean('email_notification')->default(false);
        //     $table->boolean('email_verification')->default(false);
        //     $table->boolean('sms_notification')->default(false);
        //     $table->boolean('sms_verification')->default(false);
        //     $table->string('tawk_id', 255)->nullable();
        //     $table->boolean('tawk_status')->default(false);
        //     $table->boolean('fb_messenger_status')->default(false);
        //     $table->string('fb_app_id', 255)->nullable();
        //     $table->string('fb_page_id', 255)->nullable();
        //     $table->boolean('manual_recaptcha')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('google_recaptcha')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('manual_recaptcha_admin_login')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('manual_recaptcha_login')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('manual_recaptcha_register')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('google_recaptcha_admin_login')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('google_recaptcha_login')->default(0)->comment('0=>inactive,1=>active');
        //     $table->boolean('google_recaptcha_register')->default(0)->comment('0=>inactive,1=>active');

        //     $table->string('measurement_id')->nullable();
        //     $table->boolean('analytic_status')->nullable();
        //     $table->boolean('error_log')->nullable();
        //     $table->boolean('is_active_cron_notification')->nullable();
        //     $table->string('logo')->nullable();
        //     $table->string('logo_driver')->nullable();
        //     $table->string('favicon')->nullable();
        //     $table->string('favicon_driver')->nullable();
        //     $table->string('admin_logo')->nullable();
        //     $table->string('admin_logo_driver')->nullable();
        //     $table->string('admin_dark_mode_logo')->nullable();
        //     $table->string('admin_dark_mode_logo_driver')->nullable();
        //     $table->string('currency_layer_access_key', 255)->nullable();
        //     $table->string('currency_layer_auto_update_at', 255)->nullable();
        //     $table->string('currency_layer_auto_update', 1)->nullable();
        //     $table->string('coin_market_cap_app_key', 255)->nullable();
        //     $table->string('coin_market_cap_auto_update_at', 255)->nullable();
        //     $table->boolean('coin_market_cap_auto_update')->default(false);
        //     $table->boolean('automatic_payout_permission')->default(false);
        //     $table->string('date_time_format', 255)->nullable();
        //     $table->boolean('virtual_card')->default(0)->comment('0=>Inactive,1=>Active');
        //     $table->boolean('v_card_multiple')->default(0)->comment('0=>Inactive,1=>Active');
        //     $table->double('v_card_charge',10,2)->nullable();
        //     $table->decimal('min_amount',18,2)->nullable();
        //     $table->decimal('max_amount',18,2)->nullable();
        //     $table->decimal('min_transfer_fee',18,2)->nullable();
        //     $table->decimal('max_transfer_fee',18,2)->nullable();
        //     $table->boolean('refer_status')->default(1)->comment('1=>Active, 0=>Inactive');
        //     $table->string('refer_title')->nullable();
        //     $table->decimal('refer_earn_amount',18,2)->nullable();
        //     $table->decimal('refer_free_transfer',18,2)->nullable();
        //     $table->boolean('cookie_status')->default(0)->comment('0=>off,1=>on');
        //     $table->string('cookie_title')->nullable();
        //     $table->string('cookie_sub_title')->nullable();
        //     $table->string('cookie_url')->nullable();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basic_controls');
    }
};
