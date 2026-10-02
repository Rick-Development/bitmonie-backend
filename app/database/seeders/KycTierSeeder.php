<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KycTier;

class KycTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tiers = [
            [
                'level' => 1,
                'name' => 'Tier 1',
                'description' => 'Basic verification to start transacting.',
                'requirements' => 'BVN, Selfie',
                'vform_id' => 'vform_tier_1_id', // Placeholder, admin can change this
            ],
            [
                'level' => 2,
                'name' => 'Tier 2',
                'description' => 'Full identity verification for higher limits.',
                'requirements' => 'BVN, NIN, Date of Birth',
                'vform_id' => 'vform_tier_2_id',
            ],
            [
                'level' => 3,
                'name' => 'Tier 3',
                'description' => 'Address verification for maximum limits.',
                'requirements' => 'Proof of Address (Utility Bill)',
                'vform_id' => 'vform_tier_3_id',
            ],
        ];

        foreach ($tiers as $tier) {
            KycTier::updateOrCreate(['level' => $tier['level']], $tier);
        }
    }
}
