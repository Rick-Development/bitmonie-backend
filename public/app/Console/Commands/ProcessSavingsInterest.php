<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessSavingsInterest extends Command
{
    protected $signature = 'savings:process-interest';

    protected $description = 'Process daily interest accrual and check for SafeLock maturity';

    public function handle(\App\Services\SavingsService $savingsService)
    {
        $this->info('Starting Savings Interest and Maturity Processing...');
        
        $savingsService->calculateDailyInterest();
        $this->info('Daily interest calculated.');

        $savingsService->processSafeLockMaturity();
        $this->info('SafeLock maturity processed.');

        $this->info('Savings processing completed.');
        return Command::SUCCESS;
    }
}
