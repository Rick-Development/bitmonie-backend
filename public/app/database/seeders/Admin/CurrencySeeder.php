<?php

namespace Database\Seeders\Admin;

use App\Models\Admin\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currencies = array(
            array('id' => '1','admin_id' => '1','country' => 'United States','name' => 'United States dollar','code' => 'USD','symbol' => '$','type' => 'FIAT','flag' => '1e4551f9-2216-4fcc-83b3-3a9b85c5c379.webp','rate' => '1.00000000','sender' => '1','receiver' => '1','default' => '1','status' => '1','created_at' => '2024-01-17 07:17:53','updated_at' => '2024-01-17 07:18:24')
        );

        Currency::insert($currencies);
        // Currency::factory()->times(50)->create();
    }
}
