<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\SavingsService;
use Illuminate\Support\Facades\Log;

class ProcessDailyInterestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SavingsService $savingsService)
    {
        Log::info('Background Job: Starting Savings Interest and Maturity Processing...');
        
        $summary = $savingsService->calculateDailyInterest();
        Log::info('Background Job: Daily interest calculated.', $summary);

        $savingsService->processSafeLockMaturity();
        Log::info('Background Job: SafeLock maturity processed.');

        Log::info('Background Job: Savings processing completed.');
    }
}
