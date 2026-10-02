<?php

namespace Database\Seeders;

use App\Models\UsdtEasyearnSetting;
use Illuminate\Database\Seeder;

class UsdtEasyearnSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UsdtEasyearnSetting::create([
            'current_monthly_rate' => 10.00,
            'min_investment' => 10.00,
            'max_investment' => null,
            'is_active' => true,
            'payout_day' => 10,
            'auto_credit_enabled' => false,
        ]);
    }
}
