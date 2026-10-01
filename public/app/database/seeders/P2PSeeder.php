<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\P2PAd;
use App\Models\P2POrder;
use App\Models\P2PPaymentMethod;
use Carbon\Carbon;

class P2PSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::take(5)->get(); // Get first 5 users
        
        if ($users->count() < 2) {
            $this->command->info('Not enough users to seed P2P data. Please seed users first.');
            return;
        }

        $fiat = 'NGN';
        $assets = ['USDT', 'BTC', 'ETH'];
        $adTypes = ['BUY', 'SELL'];

        // 1. Create Payment Methods for users
        foreach ($users as $user) {
            // Check if user already has payment methods
            if (P2PPaymentMethod::where('user_id', $user->id)->exists()) {
                 continue;
            }

            P2PPaymentMethod::create([
                'user_id' => $user->id,
                'name' => 'Bank Transfer',
                'provider' => 'Access Bank',
                'details' => [
                    'bank_name' => 'Access Bank',
                    'account_number' => '123456789' . $user->id,
                    'account_name' => $user->firstname . ' ' . $user->lastname
                ],
                'status' => 1
            ]);
            
            P2PPaymentMethod::create([
                'user_id' => $user->id,
                'name' => 'Kuda Bank',
                'provider' => 'Kuda',
                'details' => [
                    'bank_name' => 'Kuda Microfinance Bank',
                    'account_number' => '2000' . rand(100000, 999999),
                    'account_name' => $user->firstname . ' ' . $user->lastname
                ],
                'status' => 1
            ]);
        }

        // 1b. Create User Stats
        foreach ($users as $user) {
            if (\App\Models\P2PUserStat::where('user_id', $user->id)->exists()) {
                continue;
            }
            \App\Models\P2PUserStat::create([
                'user_id' => $user->id,
                'total_trades' => rand(0, 100),
                'completed_trades' => rand(0, 90),
                'completion_rate' => 95.5,
                'avg_release_time_minutes' => rand(5, 60),
                'rating' => 4.8,
                'reviews_count' => rand(0, 50),
            ]);
        }

        // 2. Create Ads
        foreach ($users as $user) {
            // Get user's payment methods
            $paymentMethodIds = P2PPaymentMethod::where('user_id', $user->id)->pluck('id')->toArray();
            if (empty($paymentMethodIds)) continue;

            foreach ($assets as $asset) {
                foreach ($adTypes as $type) {
                    // Randomize prices slightly
                    $marketPrice = $asset === 'USDT' ? 1500 : ($asset === 'BTC' ? 90000000 : 4000000); // Rough NGN prices
                    $price = $type === 'SELL' ? $marketPrice * 1.02 : $marketPrice * 0.98;

                    P2PAd::create([
                        'user_id' => $user->id,
                        'type' => $type,
                        'asset' => $asset,
                        'fiat' => $fiat,
                        'price_type' => 'FIXED',
                        'price' => $price,
                        'margin' => 0,
                        'total_amount' => rand(100, 1000),
                        'available_amount' => rand(50, 1000),
                        'min_limit' => 1000,
                        'max_limit' => 1000000,
                        'payment_method_ids' => $paymentMethodIds,
                        'terms' => 'No third party payments. Fast release.',
                        'auto_reply' => 'I am online, pay immediately.',
                        'time_limit' => 15, // minutes
                        'status' => 1,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }
            }
        }

        // 3. Create active orders
        $ads = P2PAd::inRandomOrder()->take(10)->get();
        
        foreach ($ads as $ad) {
            // Find a taker (different from maker)
            $taker = $users->where('id', '!=', $ad->user_id)->random();
            if (!$taker) continue;

            $amount = rand(10, 50); // Asset amount
            $price = $ad->price;
            $total = $amount * $price; // Fiat total

            P2POrder::create([
                'ad_id' => $ad->id,
                'maker_id' => $ad->user_id,
                'taker_id' => $taker->id,
                'type' => strtolower($ad->type), // Ensure lowercase for enum
                'asset' => $ad->asset,
                'quote_currency' => $ad->fiat,
                'amount' => $amount,
                'price' => $price,
                'locked_price' => $price,
                'total' => $total,
                'escrow_enabled' => true,
                'status' => 'open', // Changed from 'pending' to 'open' (valid enum value)
                'payment_deadline' => Carbon::now()->addMinutes($ad->time_limit),
                'expires_at' => Carbon::now()->addMinutes($ad->time_limit),
                'meta' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
