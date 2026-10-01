<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnsureMultiWalletsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'NGN',
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'rate' => 1,
                'sender' => 1,
                'receiver' => 1,
                'status' => 1,
                'default' => 1
            ],
            [
                'code' => 'USD',
                'name' => 'United States Dollar',
                'symbol' => '$',
                'rate' => 1500, // Example rate
                'sender' => 1,
                'receiver' => 1,
                'status' => 1,
                'default' => 0
            ]
        ];

        foreach ($currencies as $currencyData) {
            $currency = \App\Models\Admin\Currency::firstOrCreate(
                ['code' => $currencyData['code']],
                array_merge($currencyData, ['admin_id' => 1])
            );

            // Ensure all users have this wallet
            $users = \App\Models\User::get();
            foreach ($users as $user) {
                \App\Models\UserWallet::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'currency_id' => $currency->id,
                    ],
                    [
                        'currency_code' => $currency->code,
                        'balance' => 0,
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
