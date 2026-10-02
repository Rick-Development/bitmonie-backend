<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SavingsPlan;
use Illuminate\Support\Str;

class SavingsPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => '10 Days Lock',
                'duration_days' => 10,
                'interest_rate' => 5.00,
                'min_amount' => 1000,
                'max_amount' => 5000000,
            ],
            [
                'name' => '30 Days Lock',
                'duration_days' => 30,
                'interest_rate' => 12.00,
                'min_amount' => 5000,
                'max_amount' => 10000000,
            ],
            [
                'name' => '90 Days Lock',
                'duration_days' => 90,
                'interest_rate' => 20.00,
                'min_amount' => 10000,
                'max_amount' => 50000000,
            ],
        ];

        foreach ($plans as $plan) {
            SavingsPlan::updateOrCreate(
                ['name' => $plan['name']],
                array_merge($plan, ['slug' => Str::slug($plan['name'])])
            );
        }
    }
}
