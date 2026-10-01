<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class BasicControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'theme', 'site_title', 'primary_color', 'secondary_color', 'time_zone', 'base_currency', 'currency_symbol', 'currency_rate',
        'admin_prefix', 'is_currency_position', 'has_space_between_currency_and_amount', 'is_force_ssl', 'is_maintenance_mode',
        'paginate', 'strong_password', 'registration', 'fraction_number', 'sender_email', 'sender_email_name', 'email_description',
        'push_notification', 'in_app_notification', 'email_notification', 'email_verification', 'sms_notification', 'sms_verification',
        'tawk_id', 'tawk_status', 'fb_messenger_status', 'fb_app_id', 'fb_page_id',
        'manual_recaptcha', 'google_recaptcha', 'manual_recaptcha_admin_login', 'manual_recaptcha_login', 'manual_recaptcha_register',
        'google_recaptcha_admin_login', 'google_recaptcha_login', 'google_recaptcha_register',
        'measurement_id', 'analytic_status', 'error_log', 'is_active_cron_notification',
        'logo', 'logo_driver', 'favicon', 'favicon_driver', 'admin_logo', 'admin_logo_driver', 'admin_dark_mode_logo', 'admin_dark_mode_logo_driver',
        'currency_layer_access_key', 'currency_layer_auto_update_at', 'currency_layer_auto_update', 'coin_market_cap_app_key', 'coin_market_cap_auto_update_at',
        'coin_market_cap_auto_update', 'automatic_payout_permission', 'date_time_format', 'cookie_title', 'cookie_sub_title', 'cookie_url', 'cookie_status',
        'refer_status', 'refer_title',
    ];

    public function getLogo(){
       return getFile($this->logo_driver, $this->logo);
    }

    protected function metaKeywords(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => explode(", ", $value),
        );
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function () {
            \Cache::forget('ConfigureSetting');
        });
    }
}
