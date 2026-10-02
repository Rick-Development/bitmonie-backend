<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\ReferralWalletService;
use App\Services\WalletService;
use Illuminate\Support\Str;

class TestReferralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $referralWalletService = app(ReferralWalletService::class);

        // Find the referrer user
        $referrer = User::where('email', 'unicefdeveloper76@gmail.com')->first();
        
        if (!$referrer) {
            $this->command->error("User with email unicefdeveloper76@gmail.com not found!");
            return;
        }

        // Ensure referrer has a referral code
        if (!$referrer->referral_code) {
            $referrer->update(['referral_code' => strtoupper(Str::random(8))]);
            $this->command->info("Generated referral code for referrer: {$referrer->referral_code}");
        }

        // Get some existing users to use as referred users (excluding the referrer)
        $potentialReferrals = User::where('id', '!=', $referrer->id)
            ->whereNotIn('id', function($query) use ($referrer) {
                $query->select('referred_id')
                    ->from('referrals')
                    ->where('referrer_id', $referrer->id);
            })
            ->limit(5)
            ->get();

        if ($potentialReferrals->isEmpty()) {
            $this->command->error("No available users to create referrals!");
            return;
        }

        $count = 0;
        foreach ($potentialReferrals as $referred) {
            // Create referral record
            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $referred->id,
                'status' => $count < 3 ? 'rewarded' : 'pending', // First 3 are rewarded, rest pending
            ]);

            // If rewarded, create earning record and credit wallet
            if ($referral->status === 'rewarded') {
                // Get or ensure NGN wallet exists
                $wallet = $referrer->wallets()->where('currency_code', 'NGN')->first();
                
                if ($wallet) {
                    $reference = 'REF-BONUS-' . strtoupper(Str::random(10));
                    $referralWalletService->recordEarning($referrer, '500', $reference, [
                        'type' => 'referral_bonus',
                        'description' => "Referral signup bonus for {$referred->username}",
                        'referred_user_id' => $referred->id,
                    ], $referral);

                    WalletService::credit($wallet->id, '500', $reference, [
                        'type' => 'referral_bonus',
                        'description' => "Referral signup bonus for {$referred->username}"
                    ]);

                    // Record the earning
                    ReferralEarning::create([
                        'user_id' => $referrer->id,
                        'referral_id' => $referral->id,
                        'amount' => 500,
                        'currency_code' => 'NGN',
                        'type' => 'signup_bonus',
                        'description' => "Signup bonus for referring {$referred->username}",
                    ]);

                    $this->command->info("Created rewarded referral: {$referred->username} (₦500 credited)");
                } else {
                    $this->command->warn("No NGN wallet found for referrer, skipping credit for {$referred->username}");
                }
            } else {
                $this->command->info("Created pending referral: {$referred->username}");
            }

            $count++;
        }

        $this->command->info("\nTotal referrals created: {$count}");
        $this->command->info("Referrer: {$referrer->email} (Code: {$referrer->referral_code})");
    }
}
