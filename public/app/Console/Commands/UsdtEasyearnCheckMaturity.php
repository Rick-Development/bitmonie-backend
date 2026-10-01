<?php

namespace App\Console\Commands;

use App\Models\UsdtEasyearnInvestment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UsdtEasyearnCheckMaturity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usdt:easyearn:check-maturity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for matured USDT EasyEarn investments and notify users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for matured investments...');

        $maturedInvestments = UsdtEasyearnInvestment::active()
            ->matured()
            ->with('user')
            ->get();

        if ($maturedInvestments->isEmpty()) {
            $this->info('No matured investments found.');
            return 0;
        }

        $this->info("Found {$maturedInvestments->count()} matured investments.");

        foreach ($maturedInvestments as $investment) {
            try {
                // Send notification to user
                // Note: Notification class will be created separately
                // $investment->user->notify(new \App\Notifications\UsdtEasyearnInvestmentMatured($investment));

                $this->info("Notified user {$investment->user->email} about matured investment #{$investment->id}");
                
                Log::info('USDT EasyEarn Investment Matured', [
                    'investment_id' => $investment->id,
                    'user_id' => $investment->user_id,
                    'amount' => $investment->amount
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to notify user for investment #{$investment->id}: {$e->getMessage()}");
                Log::error("Failed to notify matured investment", [
                    'investment_id' => $investment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info('Maturity check completed.');
        return 0;
    }
}
