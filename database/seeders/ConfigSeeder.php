<?php
namespace Database\Seeders;

use App\Models\BasicControl;
use App\Models\Admin\BasicSettings;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BasicControl::updateOrCreate(
            ['id' => 1],
            [
                /*
                |--------------------------------------------------------------------------
                | Application
                |--------------------------------------------------------------------------
                */
                'site_title' => config('app.name'),
                'time_zone' => config('app.timezone'),

                /*
                |--------------------------------------------------------------------------
                | Currency
                |--------------------------------------------------------------------------
                */
                'base_currency' => 'NGN',
                'currency_symbol' => '₦',

                /*
                |--------------------------------------------------------------------------
                | Email
                |--------------------------------------------------------------------------
                */
                'sender_email' => config('mail.from.address'),
                'sender_email_name' => config('mail.from.name'),
            ]
        );
        BasicSettings::updateOrCreate(
               ['id' => 1],
            [
                /*
                |--------------------------------------------------------------------------
                | Application
                |--------------------------------------------------------------------------
                */
                'site_title' =>'Your Crypto Powered Neo Bank',
                 'site_name' => config('app.name'),
            ]

        );
    }
}
