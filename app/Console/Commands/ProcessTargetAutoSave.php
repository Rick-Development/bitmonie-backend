<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessTargetAutoSave extends Command
{
    protected $signature = 'savings:auto-save';

    protected $description = 'Process scheduled auto-saves for Target Savings';

    public function handle(\App\Services\SavingsService $savingsService)
    {
        $this->info('Starting Target Auto-Save Processing...');
        
        $savingsService->processTargetAutoSave();

        $this->info('Target Auto-Save processing completed.');
        return Command::SUCCESS;
    }
}
