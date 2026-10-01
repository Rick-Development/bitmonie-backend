<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GiftCardTradeType;
use App\Models\GiftCardTradeCategory;
use App\Models\GiftCardTradeCountry;
use Illuminate\Support\Str;

class GiftCardTradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Types
        $types = ['Physical Card', 'E-Code'];
        foreach ($types as $type) {
            GiftCardTradeType::firstOrCreate(
                ['slug' => Str::slug($type)],
                ['name' => $type]
            );
        }

        // 2. Categories
        $categories = [
            'Amazon' => 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg',
            'iTunes' => 'https://upload.wikimedia.org/wikipedia/commons/d/df/ITunes_logo.svg',
            'Steam' => 'https://upload.wikimedia.org/wikipedia/commons/8/83/Steam_icon_logo.svg',
            'Google Play' => 'https://upload.wikimedia.org/wikipedia/commons/d/d0/Google_Play_Arrow_logo.svg',
            'Netflix' => 'https://upload.wikimedia.org/wikipedia/commons/0/08/Netflix_2015_logo.svg',
            'eBay' => 'https://upload.wikimedia.org/wikipedia/commons/1/1b/EBay_logo.svg',
            'Razer Gold' => 'https://razerid-assets.razerzone.com/static/img/razer-gold-logo.png',
            'Vanilla Visa' => 'https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg'
        ];
        foreach ($categories as $name => $icon) {
            GiftCardTradeCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name, 
                    'icon' => $icon,
                    'status' => 1
                ]
            );
        }

        // 3. Countries
        $countries = [
            ['name' => 'United States', 'code' => 'USA', 'flag' => 'https://flagcdn.com/w320/us.png'],
            ['name' => 'United Kingdom', 'code' => 'UK', 'flag' => 'https://flagcdn.com/w320/gb.png'],
            ['name' => 'Canada', 'code' => 'CAN', 'flag' => 'https://flagcdn.com/w320/ca.png'],
            ['name' => 'Australia', 'code' => 'AUS', 'flag' => 'https://flagcdn.com/w320/au.png'],
            ['name' => 'Germany', 'code' => 'DEU', 'flag' => 'https://flagcdn.com/w320/de.png'],
        ];
        foreach ($countries as $country) {
            GiftCardTradeCountry::updateOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'flag_icon' => $country['flag']
                ]
            );
        }

        // 4. Rates (New)
        $amazon = GiftCardTradeCategory::where('slug', 'amazon')->first();
        $steam = GiftCardTradeCategory::where('slug', 'steam')->first();
        $physical = GiftCardTradeType::where('slug', 'physical-card')->first();
        $ecode = GiftCardTradeType::where('slug', 'e-code')->first();
        $usa = GiftCardTradeCountry::where('code', 'USA')->first();
        $uk = GiftCardTradeCountry::where('code', 'UK')->first();

        // Sample Rate 1: Amazon Physical USA
        if ($amazon && $physical && $usa) {
            \App\Models\GiftCardTradeRate::firstOrCreate(
                [
                    'category_id' => $amazon->id,
                    'type_id' => $physical->id,
                    'country_id' => $usa->id,
                ],
                [
                    'min_amount' => 50,
                    'max_amount' => 500,
                    'rate_per_dollar' => 1200, // Example rate
                    'currency' => 'USD',
                    'status' => 1,
                ]
            );
        }

        // Sample Rate 2: Steam E-Code USA
        if ($steam && $ecode && $usa) {
            \App\Models\GiftCardTradeRate::firstOrCreate(
                [
                    'category_id' => $steam->id,
                    'type_id' => $ecode->id,
                    'country_id' => $usa->id,
                ],
                [
                    'min_amount' => 20,
                    'max_amount' => 200,
                    'rate_per_dollar' => 1100, // Example rate
                    'currency' => 'USD',
                    'status' => 1,
                ]
            );
        }
        
        // Sample Rate 3: iTunes Physical UK
        if ($categories && $uk && $physical) {
             $itunes = GiftCardTradeCategory::where('slug', 'itunes')->first();
             if($itunes) {
                \App\Models\GiftCardTradeRate::firstOrCreate(
                    [
                        'category_id' => $itunes->id,
                        'type_id' => $physical->id,
                        'country_id' => $uk->id,
                    ],
                    [
                        'min_amount' => 25,
                        'max_amount' => 500,
                        'rate_per_dollar' => 1500, // Example rate for GBP
                        'currency' => 'GBP',
                        'status' => 1,
                    ]
                );
             }
        }
    }
}
