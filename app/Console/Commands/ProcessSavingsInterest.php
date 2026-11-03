<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailyInterestJob;
use Illuminate\Console\Command;

class ProcessSavingsInterest extends Command
{
    protected $signature = 'savings:process-interest';

    protected $description = 'Process daily interest accrual and check for SafeLock maturity';

    public function handle()
    {
        $this->info('Starting savings interest and maturity processing...');

        // Run inline so midnight cron accrues interest even when queue workers are down.
        // dispatchSync still goes through the bus but never depends on a worker process.
        ProcessDailyInterestJob::dispatchSync();

        $this->info('Savings interest and maturity processing completed.');

        return Command::SUCCESS;
    }
}
