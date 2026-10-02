<?php

namespace Database\Seeders\Admin;

use Exception;
use Illuminate\Database\Seeder;
use App\Models\Admin\BasicSettings;

class FreshBasicSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'site_name'         => "iBanking",
            'site_title'        => "Comprehensive Digital Banking and Financial Solution",
            'base_color'        => "#007bff",
            'otp_exp_seconds'   => "3600",
            'timezone'          => "Asia/Dhaka",
            'broadcast_config'  => [
                "method" => "",
                "app_id" => "", 
                "primary_key" => "", 
                "secret_key" => "", 
                "cluster" => "" 
            ],
            'push_notification_config'  => [
                "method" => "", 
                "instance_id" => "", 
                "primary_key" => ""
            ],
            'kyc_verification'  => true,
            'mail_config'       => [
                "method"        => "",
                "host"          => "",
                "port"          => "",
                "encryption"    => "",
                "password"      => "",
                "username"      => "",
                "from"          => "",
                "mail_address"  => "", 
                "app_name"      => "",
            ],
            'email_verification'    => true,
            'user_registration'     => 1,
            'agree_policy'          => 1,
            'site_logo_dark'        => "seeder/logo.webp",
            'site_logo'             => "seeder/logo.webp",
            'site_fav_dark'         => "seeder/favicon.webp",
            'site_fav'              => "seeder/favicon.webp",
            'web_version'           => "1.0.0",
            'admin_version'         => "2.5.0",
        ];

        try{
            $basic_data     = BasicSettings::firstOrCreate($data);
            $env_modify_keys = [
                "MAIL_MAILER"       => $basic_data->mail_config->method,
                "MAIL_HOST"         => $basic_data->mail_config->host,
                "MAIL_PORT"         => $basic_data->mail_config->port,
                "MAIL_USERNAME"     => $basic_data->mail_config->username,
                "MAIL_PASSWORD"     => $basic_data->mail_config->password,
                "MAIL_ENCRYPTION"   => $basic_data->mail_config->encryption,
                "MAIL_FROM_ADDRESS" => $basic_data->mail_config->mail_address,
                "MAIL_FROM_NAME"    => $basic_data->mail_config->app_name,
                "PUSHER_APP_ID"     => $basic_data->broadcast_config->app_id,
                "PUSHER_APP_KEY"    => $basic_data->broadcast_config->primary_key,
                "PUSHER_APP_SECRET" => $basic_data->broadcast_config->secret_key,
            ];
            modifyEnv($env_modify_keys);
        }catch(Exception $e){}
    }
}
