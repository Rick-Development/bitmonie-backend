<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessSavingsInterest extends Command
{
    protected $signature = 'savings:process-interest';

    protected $description = 'Process daily interest accrual and check for SafeLock maturity';

    public function handle()
    {
        $this->info('Dispatching Savings Interest and Maturity Processing to background queue...');
        
        \App\Jobs\ProcessDailyInterestJob::dispatch();

        $this->info('Job dispatched successfully.');
        return Command::SUCCESS;
    }
}
