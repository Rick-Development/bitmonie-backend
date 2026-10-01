<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Services\ReferralService;

class ReferralCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $referralService = new ReferralService();
        
        // Get all users without referral codes
        $users = User::whereNull('referral_code')->get();
        
        $count = 0;
        foreach ($users as $user) {
            $referralService->generateReferralCode($user);
            $count++;
        }
        
        $this->command->info("Generated referral codes for {$count} users.");
    }
}
