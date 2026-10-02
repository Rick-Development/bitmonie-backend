<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VasService;
use App\Models\VasServiceCategory;

class VasServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Airtime
        $airtimeService = VasService::where('name', 'Mobile Recharge')->orWhere('name', 'Airtime')->first();
        if ($airtimeService) {
            $categories = [
                ['name' => 'MTN', 'identifier' => '61efacbcda92348f9dde5f92'],
                ['name' => 'GLO', 'identifier' => '61efacc8da92348f9dde5f95'],
                ['name' => 'AIRTEL', 'identifier' => '61efacd3da92348f9dde5f98'],
                ['name' => '9MOBILE', 'identifier' => '61efacdeda92348f9dde5f9b'],
            ];

            foreach ($categories as $cat) {
                VasServiceCategory::updateOrCreate(
                    ['vas_service_id' => $airtimeService->id, 'name' => $cat['name']],
                    ['identifier' => $cat['identifier'], 'status' => true]
                );
            }
        }

        // 2. Data
        $dataService = VasService::where('name', 'DATA PURCHASE')->orWhere('name', 'Data')->first();
        if ($dataService) {
            $categories = [
                ['name' => 'MTN', 'identifier' => '6502eb6e65463b201bf8065f'],
                ['name' => 'GLO', 'identifier' => '61efad06da92348f9dde5fa1'],
                ['name' => 'AIRTEL', 'identifier' => '61efad12da92348f9dde5fa4'],
                ['name' => '9MOBILE', 'identifier' => '61efad1dda92348f9dde5fa7'],
            ];

            foreach ($categories as $cat) {
                 VasServiceCategory::updateOrCreate(
                    ['vas_service_id' => $dataService->id, 'name' => $cat['name']],
                    ['identifier' => $cat['identifier'], 'status' => true]
                );
            }
        }

        // 3. Cable TV
        $cableService = VasService::where('name', 'CABLE TV')->orWhere('name', 'Cable')->first();
        if ($cableService) {
            $categories = [
                ['name' => 'DSTV', 'identifier' => '61efad38da92348f9dde5faa'],
                ['name' => 'GOTV', 'identifier' => '61efad45da92348f9dde5fad'],
                ['name' => 'STARTIMES', 'identifier' => '61efad50da92348f9dde5fb0'],
            ];

            foreach ($categories as $cat) {
                 VasServiceCategory::updateOrCreate(
                    ['vas_service_id' => $cableService->id, 'name' => $cat['name']],
                    ['identifier' => $cat['identifier'], 'status' => true]
                );
            }
        }

        // 4. Utility Bills
        $utilityService = VasService::where('name', 'UTILITY BILLS')->orWhere('name', 'Utility')->first();
        if ($utilityService) {
            $categories = [
                ['name' => 'EKEDC', 'identifier' => '61efac27da92348f9dde5f74'],
                ['name' => 'IKEDC', 'identifier' => '61efac5eda92348f9dde5f80'],
                ['name' => 'AEDC', 'identifier' => '61efac35da92348f9dde5f77'],
                ['name' => 'PHED', 'identifier' => '61efac94da92348f9dde5f8c'], // PHEDC -> PHED
                ['name' => 'KEDCO', 'identifier' => '61efac87da92348f9dde5f89'],
                ['name' => 'BEDC', 'identifier' => '61efac19b5ce7eaad3b405d4'],
                ['name' => 'EEDC', 'identifier' => '61efac42da92348f9dde5f7a'],
                ['name' => 'IBEDC', 'identifier' => '61efac51da92348f9dde5f7d'],
                ['name' => 'JEDC', 'identifier' => '61efac6ada92348f9dde5f83'],
                ['name' => 'KAEDC', 'identifier' => '61efac78da92348f9dde5f86'],
                ['name' => 'YEDC', 'identifier' => '61efaca1da92348f9dde5f8f'],
            ];

            foreach ($categories as $cat) {
                 VasServiceCategory::updateOrCreate(
                    ['vas_service_id' => $utilityService->id, 'name' => $cat['name']],
                    ['identifier' => $cat['identifier'], 'status' => true]
                );
            }
        }
    }
}
