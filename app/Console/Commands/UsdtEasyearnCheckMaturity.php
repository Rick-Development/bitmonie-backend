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
    protected $description = 'Check for matured USDT EasyEarn investments, credit PowerBonus, and notify users';

    protected $easyearnService;

    public function __construct(\App\Services\UsdtEasyearnService $easyearnService)
    {
        parent::__construct();
        $this->easyearnService = $easyearnService;
    }

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
                // If auto_compound investment has not received PowerBonus yet, credit it
                if ($investment->auto_compound && $investment->power_bonus_credited_at === null) {
                    $result = $this->easyearnService->processMaturity($investment);
                    $this->info("Processed maturity PowerBonus for investment #{$investment->id}: {$result['message']}");
                }

                $this->info("Matured investment #{$investment->id} processed for user {$investment->user->email}");
                
                Log::info('USDT EasyEarn Investment Matured', [
                    'investment_id' => $investment->id,
                    'user_id' => $investment->user_id,
                    'amount' => $investment->amount,
                    'power_bonus_amount' => $investment->power_bonus_amount,
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to process matured investment #{$investment->id}: {$e->getMessage()}");
                Log::error("Failed to process matured investment", [
                    'investment_id' => $investment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info('Maturity check completed.');
        return 0;
    }
}
