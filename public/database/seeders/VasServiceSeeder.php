<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\VasService;

class VasServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $services = [
            [
                "safehaven_id" => "61efab78b5ce7eaad3b405d0",
                "name" => "UTILITY BILLS",
                "identifier" => "UTILITY",
                "description" => "Power and Disco bills",
            ],
            [
                "safehaven_id" => "61efaba1da92348f9dde5f6c",
                "name" => "Mobile Recharge",
                "identifier" => "AIRTIME",
                "description" => "Airtime Recharge",
            ],
            [
                "safehaven_id" => "61efabb2da92348f9dde5f6e",
                "name" => "DATA PURCHASE",
                "identifier" => "DATA",
                "description" => "Data bundle subscription",
            ],
            [
                "safehaven_id" => "61efabbeda92348f9dde5f70",
                "name" => "CABLE TV",
                "identifier" => "CABLETV",
                "description" => "Cable tv subscription",
            ]
        ];

        foreach ($services as $service) {
            VasService::updateOrCreate(
                ['identifier' => $service['identifier']],
                $service
            );
        }
    }
}
