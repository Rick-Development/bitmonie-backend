<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Seed the application's feature catalog.
     */
    public function run(): void
    {
        $features = [
            [
                'name' => 'Virtual Account',
                'description' => 'Create and manage virtual bank accounts.',
                'status' => 'active',
            ],
            [
                'name' => 'Multi-Currency Wallet',
                'description' => 'Hold and manage balances across multiple currencies.',
                'status' => 'active',
            ],
            [
                'name' => 'Crypto Wallet',
                'description' => 'Manage cryptocurrency wallets and balances.',
                'status' => 'active',
            ],
            [
                'name' => 'Crypto On-Ramp',
                'description' => 'Convert fiat currency into cryptocurrency.',
                'status' => 'active',
            ],
            [
                'name' => 'Crypto Off-Ramp',
                'description' => 'Convert cryptocurrency into fiat currency.',
                'status' => 'active',
            ],
            [
                'name' => 'Currency Conversion',
                'description' => 'Convert one supported currency into another.',
                'status' => 'active',
            ],
            [
                'name' => 'Cross-Border Payment',
                'description' => 'Send and receive payments across supported countries.',
                'status' => 'active',
            ],
            [
                'name' => 'Virtual Card',
                'description' => 'Create and manage virtual payment cards.',
                'status' => 'active',
            ],
            [
                'name' => 'Card Funding',
                'description' => 'Fund supported virtual and physical cards.',
                'status' => 'active',
            ],
            [
                'name' => 'Usdt EasyEarn',
                'description' => 'Earn returns on eligible cryptocurrency investments.',
                'status' => 'active',
            ],
             [
                'name' => 'Flex Savings',
                'description' => 'Earn returns on eligible fiat savings.',
                'status' => 'active',
            ]
        ];

        foreach ($features as $feature) {
            Feature::query()->updateOrCreate(
                [
                    'name' => $feature['name'],
                ],
                [
                    'description' => $feature['description'],
                    'status' => $feature['status'],
                ]
            );
        }
    }
}