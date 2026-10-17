<?php

namespace App\Console\Commands;

use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnInterestCredit;
use App\Services\UsdtEasyearnService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsdtEasyearnRecalculateInterest extends Command
{
    protected $signature = 'usdt:easyearn:recalculate-interest {--apply : Apply the recalculated amounts to the database}';

    protected $description = 'Audit, recalculate, and optionally backfill accrued interest for active USDT EasyEarn plans';

    public function handle(UsdtEasyearnService $service)
    {
        $this->info('Auditing USDT EasyEarn plans for accrued interest...');

        $investments = UsdtEasyearnInvestment::with('user')->where('status', 'active')->get();

        if ($investments->isEmpty()) {
            $this->warn('No active USDT EasyEarn investments found in the database.');
            return 0;
        }

        $now = Carbon::now();
        $rows = [];
        $updates = [];

        foreach ($investments as $inv) {
            $startDate = Carbon::parse($inv->start_date)->startOfDay();
            $daysSinceStart = max(0, $startDate->diffInDays($now->copy()->startOfDay()));
            $daysEligible = $inv->dailyCreditDays();
            $dailyRate = $inv->calculateDailyDisplay();

            // Total interest that should have accrued from start_date to today
            $totalShouldHaveEarned = bcmul($dailyRate, (string) $daysSinceStart, 8);
            $currentRecorded = (string) ($inv->total_interest_earned ?? '0');
            $discrepancy = bcsub($totalShouldHaveEarned, $currentRecorded, 8);

            $rows[] = [
                'ID' => $inv->id,
                'User' => $inv->user?->email ?? "User #{$inv->user_id}",
                'Amount' => $inv->amount . ' USDT',
                'Start Date' => $startDate->toDateString(),
                'Days Running' => $daysSinceStart,
                'Current Stored' => $currentRecorded . ' USDT',
                'Correct Accrued' => $totalShouldHaveEarned . ' USDT',
                'Adjustment Needed' => $discrepancy . ' USDT',
            ];

            if (bccomp($discrepancy, '0', 8) > 0) {
                $updates[] = [
                    'investment' => $inv,
                    'adjustment' => $discrepancy,
                    'total_accrued' => $totalShouldHaveEarned,
                ];
            }
        }

        $this->table(
            ['Plan ID', 'User', 'Principal', 'Start Date', 'Days Running', 'Current Stored', 'Correct Accrued', 'Adjustment Needed'],
            $rows
        );

        if (!$this->option('apply')) {
            $this->info("\nDRY RUN COMPLETE. No database records were modified.");
            $this->comment("Run with '--apply' flag to commit these adjustments to the database.");
            return 0;
        }

        $this->info("\nApplying adjustments to " . count($updates) . " plans...");

        DB::transaction(function () use ($updates, $now) {
            foreach ($updates as $item) {
                /** @var UsdtEasyearnInvestment $inv */
                $inv = $item['investment'];
                $adjustment = $item['adjustment'];
                $totalAccrued = $item['total_accrued'];

                // Update investment
                $inv->update([
                    'total_interest_earned' => $totalAccrued,
                    'last_interest_credit_date' => $now->toDateString(),
                ]);

                // Create interest credit log
                UsdtEasyearnInterestCredit::create([
                    'investment_id' => $inv->id,
                    'amount' => $adjustment,
                    'credit_date' => $now->toDateString(),
                    'credited_by' => null,
                    'transaction_id' => null,
                ]);

                Log::channel('easyearn_usdt')->info('Accrued interest recalculated and corrected', [
                    'investment_id' => $inv->id,
                    'adjustment' => $adjustment,
                    'new_total_earned' => $totalAccrued,
                ]);
            }
        });

        $this->info("Successfully updated " . count($updates) . " plans with corrected accrued interest!");
        return 0;
    }
}
