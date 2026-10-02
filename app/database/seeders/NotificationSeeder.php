<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::where('email', 'rickdevelopmentcompany@gmail.com')->first();

        if (!$user) {
            $this->command->error("User not found!");
            return;
        }

        $notifications = [
            [
                'id' => Str::uuid()->toString(),
                'type' => 'App\Notifications\SystemNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Welcome to Bitmonie',
                    'message' => 'Thank you for joining Bitmonie. Start trading today.',
                    'image' => null
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'type' => 'App\Notifications\SystemNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Identity Verified',
                    'message' => 'Your KYC Tier 2 verification was successful.',
                    'image' => null
                ]),
                'read_at' => null,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'id' => Str::uuid()->toString(),
                'type' => 'App\Notifications\SystemNotification',
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => 'Deposit Successful',
                    'message' => 'You have received 500 USDT.',
                    'image' => null
                ]),
                'read_at' => now()->subDays(1), // Read
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
        ];

        DB::table('notifications')->insert($notifications);
        $this->command->info('Notifications seeded successfully for ' . $user->email);
    }
}
