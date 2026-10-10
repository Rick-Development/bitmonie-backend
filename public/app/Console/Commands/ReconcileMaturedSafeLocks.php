<?php

namespace App\Console\Commands;

use App\Models\OrderTransaction;
use App\Models\SafeLock;
use App\Models\SavingsTransaction;
use App\Models\UserWallet;
use App\Notifications\User\SavingsNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileMaturedSafeLocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'savings:reconcile-matured-safelocks {--user_id= : Specific user ID to reconcile} {--dry-run : Show affected locks without making database changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile matured SafeLocks that were redeemed with missing or zero interest, crediting users with their earned profit.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->option('user_id');
        $dryRun = $this->option('dry-run');

        $this->info('Starting SafeLock profit reconciliation' . ($dryRun ? ' (DRY RUN)' : '') . '...');

        $query = SafeLock::query()
            ->where(function ($q) {
                $q->where('is_redeemed', true)
                  ->orWhereIn('status', ['completed', 'matured']);
            });

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $locks = $query->get();

        if ($locks->isEmpty()) {
            $this->info('No redeemed/matured SafeLocks found.');
            return Command::SUCCESS;
        }

        $reconciledCount = 0;
        $totalProfitCredited = '0';

        foreach ($locks as $lock) {
            $amount = (string) ($lock->amount ?? '0');
            $rate = (string) ($lock->interest_rate ?? '0');

            if (bccomp($amount, '0', 8) <= 0 || bccomp($rate, '0', 8) <= 0) {
                continue;
            }

            // Calculate expected promised profit
            $expectedProfit = bcmul($amount, bcdiv($rate, '100', 8), 8);
            $currentAccrued = (string) ($lock->interest_accrued ?? '0');
            $missingProfit = bcsub($expectedProfit, $currentAccrued, 8);

            if (bccomp($missingProfit, '0.01', 2) <= 0) {
                continue;
            }

            $formattedMissing = number_format((float) $missingProfit, 2, '.', '');

            $this->line(sprintf(
                'Found SafeLock #%d (User #%d): Principal ₦%s, Rate %s%%, Expected ₦%s, Paid ₦%s, Missing ₦%s',
                $lock->id,
                $lock->user_id,
                number_format((float) $amount, 2),
                $rate,
                number_format((float) $expectedProfit, 2),
                number_format((float) $currentAccrued, 2),
                $formattedMissing
            ));

            if ($dryRun) {
                $reconciledCount++;
                $totalProfitCredited = bcadd($totalProfitCredited, $missingProfit, 8);
                continue;
            }

            DB::transaction(function () use ($lock, $missingProfit, $formattedMissing, &$reconciledCount, &$totalProfitCredited) {
                $wallet = UserWallet::where('user_id', $lock->user_id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    $this->error("Wallet (NGN) not found for User #{$lock->user_id}");
                    return;
                }

                // Credit the missing profit to the wallet
                $wallet->balance = bcadd((string) $wallet->balance, $missingProfit, 8);
                $wallet->save();

                // Update SafeLock state
                $lock->interest_accrued = bcadd((string) $lock->interest_accrued, $missingProfit, 8);
                $lock->is_redeemed = true;
                $lock->status = 'completed';
                $lock->save();

                $reference = 'safelock:profit-adjustment:' . $lock->id . ':' . Str::uuid();

                // Ledger entries
                SavingsTransaction::create([
                    'user_id' => $lock->user_id,
                    'savingsable_id' => $lock->id,
                    'savingsable_type' => SafeLock::class,
                    'amount' => $missingProfit,
                    'balance_after' => 0,
                    'type' => 'interest',
                    'status' => 'success',
                    'source' => 'safelock',
                    'narration' => "SafeLock Profit Adjustment: {$lock->title} (₦{$formattedMissing})",
                ]);

                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'amount' => $missingProfit,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => [
                        'source' => 'savings',
                        'savings_type' => 'safelock_profit_adjustment',
                        'savings_id' => $lock->id,
                        'adjusted_profit' => $formattedMissing,
                    ],
                ]);

                if ($user = $lock->user) {
                    try {
                        $user->notify(new SavingsNotification('SafeLock Profit Adjustment', 'Credited', (float) $missingProfit));
                    } catch (\Throwable $e) {
                        // Suppress notification errors if mail/queue not configured
                    }
                }

                $reconciledCount++;
                $totalProfitCredited = bcadd($totalProfitCredited, $missingProfit, 8);
            });
        }

        $this->info(sprintf(
            'Reconciliation complete! %d SafeLock(s) adjusted. Total profit credited: ₦%s',
            $reconciledCount,
            number_format((float) $totalProfitCredited, 2)
        ));

        return Command::SUCCESS;
    }
}
