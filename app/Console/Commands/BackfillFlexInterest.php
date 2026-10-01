<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FlexSavings;
use App\Models\SavingsTransaction;
use App\Services\SavingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillFlexInterest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backfill:flex-interest {--dry-run : Simulate the backfill without making database changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing Flex Savings interest from April 2026';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SavingsService $savingsService)
    {
        $this->info('Starting Flex Savings Interest Backfill...');
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->info('RUNNING IN DRY-RUN MODE: No database changes will be made.');
        } else {
            $this->warn('WARNING: Running in LIVE mode. Database will be updated.');
            if (!$this->confirm('Do you wish to continue?')) {
                return Command::SUCCESS;
            }
        }

        // We assume April 1st, 2026 as the starting point for missing interest.
        $startDate = Carbon::parse('2026-04-01')->startOfDay();
        $today = now()->startOfDay();

        $flexAccounts = FlexSavings::where('status', true)->get();
        $totalAccrued = 0;
        $accountsProcessed = 0;

        foreach ($flexAccounts as $flex) {
            // Find the period we need to backfill
            $accountStartDate = $flex->created_at->copy()->startOfDay();
            $effectiveStart = $accountStartDate->gt($startDate) ? $accountStartDate : $startDate;
            
            // If they received interest after our effective start, use that.
            if ($flex->last_interest_date && $flex->last_interest_date->gt($effectiveStart)) {
                $effectiveStart = $flex->last_interest_date->copy()->startOfDay();
            }

            $daysMissed = max(0, $effectiveStart->diffInDays($today));

            if ($daysMissed <= 0) {
                continue;
            }

            // Simple approximation for backfill based on current balance
            // For a highly accurate backfill we would need to reconstruct the daily balance ledger.
            // Using the current balance as a baseline for the missing period.
            // In a real scenario with high balances, we'd loop through day-by-day calculating balance from transactions.
            
            // Reconstruct balance at $today by getting all deposits/withdrawals since $effectiveStart
            // But to keep it simple and safe, we'll calculate interest on the minimum balance over that period
            $withdrawals = SavingsTransaction::where('savingsable_id', $flex->id)
                ->where('savingsable_type', FlexSavings::class)
                ->where('type', 'withdrawal')
                ->whereBetween('created_at', [$effectiveStart, $today])
                ->sum('amount');
                
            $deposits = SavingsTransaction::where('savingsable_id', $flex->id)
                ->where('savingsable_type', FlexSavings::class)
                ->where('type', 'deposit')
                ->whereBetween('created_at', [$effectiveStart, $today])
                ->sum('amount');
                
            // Estimated balance before recent activity
            $estimatedHistoricalBalance = $flex->balance - $deposits + $withdrawals;
            
            if ($estimatedHistoricalBalance <= 0) {
                continue;
            }

            // Using reflection to access protected calculateInterest method
            $reflection = new \ReflectionClass($savingsService);
            $method = $reflection->getMethod('calculateInterest');
            $method->setAccessible(true);
            
            $interest = $method->invoke($savingsService, $estimatedHistoricalBalance, '60', $daysMissed);

            if ($interest > 0) {
                $accountsProcessed++;
                $totalAccrued += $interest;

                $this->line("User ID: {$flex->user_id} | Missed Days: {$daysMissed} | Est. Bal: {$estimatedHistoricalBalance} | Interest: {$interest}");

                if (!$isDryRun) {
                    DB::transaction(function () use ($flex, $interest, $daysMissed) {
                        $flex->balance += $interest;
                        $flex->accrued_interest += $interest;
                        $flex->save();

                        SavingsTransaction::create([
                            'user_id' => $flex->user_id,
                            'savingsable_id' => $flex->id,
                            'savingsable_type' => FlexSavings::class,
                            'amount' => $interest,
                            'balance_after' => $flex->balance,
                            'type' => 'interest',
                            'status' => 'success',
                            'source' => 'backfill_interest',
                            'narration' => "Backfilled Flex Savings Interest for {$daysMissed} day(s) from April",
                        ]);
                    });
                }
            }
        }

        $this->info("Completed. Accounts Processed: {$accountsProcessed} | Total Interest Accrued: {$totalAccrued}");

        return Command::SUCCESS;
    }
}
