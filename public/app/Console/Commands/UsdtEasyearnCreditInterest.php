<?php

namespace App\Console\Commands;

use App\Models\UsdtEasyearnInvestment;
use App\Services\UsdtEasyearnService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UsdtEasyearnCreditInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usdt:easyearn:credit-interest 
                            {--month= : Specific month (1-12)}
                            {--year= : Specific year}
                            {--dry-run : Preview without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Credit monthly interest to all eligible USDT EasyEarn investments';

    protected $easyearnService;

    public function __construct(UsdtEasyearnService $easyearnService)
    {
        parent::__construct();
        $this->easyearnService = $easyearnService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting USDT EasyEarn Interest Credit Process...');

        $eligibleInvestments = UsdtEasyearnInvestment::eligibleForCredit()->with('user')->get();

        if ($eligibleInvestments->isEmpty()) {
            $this->info('No eligible investments found.');
            return 0;
        }

        $this->info("Found {$eligibleInvestments->count()} eligible investments.");

        // Calculate total required
        $totalRequired = $eligibleInvestments->sum(function($inv) {
            return $inv->calculateMonthlyInterest();
        });

        $this->info("Total USDT required: {$totalRequired}");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No actual credits will be made');
            
            $this->table(
                ['Investment ID', 'User', 'Amount', 'Interest', 'Auto-Compound'],
                $eligibleInvestments->map(function($inv) {
                    return [
                        $inv->id,
                        $inv->user->email,
                        $inv->amount,
                        $inv->calculateMonthlyInterest(),
                        $inv->auto_compound ? 'Yes' : 'No'
                    ];
                })
            );

            return 0;
        }

        // Confirm execution
        if (!$this->confirm('Do you want to proceed with crediting interest?')) {
            $this->warn('Operation cancelled.');
            return 0;
        }

        // Execute bulk credit
        $this->info('Processing credits...');
        $results = $this->easyearnService->bulkCreditInterest();

        $this->info("Completed! Success: {$results['success']}, Failed: {$results['failed']}");

        if (!empty($results['errors'])) {
            $this->error('Errors occurred:');
            foreach ($results['errors'] as $error) {
                $this->error("Investment {$error['investment_id']}: {$error['error']}");
            }
        }

        Log::info('USDT EasyEarn Bulk Credit Completed', $results);

        return 0;
    }
}
