<?php

namespace App\Services;

use App\Models\FlexSavings;
use App\Models\SafeLock;
use App\Models\TargetSavings;
use App\Models\UserWallet;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SavingsService
{
    /**
     * Process Daily Interest Accrual for Flex and SafeLock.
     */
    public function calculateDailyInterest()
    {
        DB::transaction(function () {
            // 1. Flex Savings Interest (10% per annum)
            $this->processFlexInterest();

            // 2. SafeLock Interest (Rate depends on the lock)
            $this->processSafeLockInterest();
        });
    }

    protected function processFlexInterest()
    {
        $flexAccounts = FlexSavings::where('status', true)
            ->where('balance', '>', 0)
            ->get();

        foreach ($flexAccounts as $flex) {
            // Daily rate = 10% / 365
            $dailyRate = 0.10 / 365;
            $interest = $flex->balance * $dailyRate;

            $flex->accrued_interest += $interest;
            $flex->last_interest_date = now();
            $flex->save();
        }
    }

    protected function processSafeLockInterest()
    {
        $activeLocks = SafeLock::where('status', 'active')
            ->where('is_redeemed', false)
            ->get();

        foreach ($activeLocks as $lock) {
            // Daily rate = Interest Rate / 365
            $dailyRate = ($lock->interest_rate / 100) / 365;
            $interest = $lock->amount * $dailyRate;

            $lock->interest_accrued += $interest;
            $lock->save();
        }
    }

    /**
     * Process SafeLock Maturity.
     */
    public function processSafeLockMaturity()
    {
        $maturedLocks = SafeLock::where('status', 'active')
            ->where('is_redeemed', false)
            ->where('maturity_date', '<=', now())
            ->get();

        foreach ($maturedLocks as $lock) {
            DB::transaction(function () use ($lock) {
                $wallet = UserWallet::where('user_id', $lock->user_id)
                    ->where('currency_code', 'NGN')
                    ->first();

                if ($wallet) {
                    $totalAmount = $lock->amount + $lock->interest_accrued;
                    $wallet->balance += $totalAmount;
                    $wallet->save();

                    $lock->is_redeemed = true;
                    $lock->status = 'matured';
                    $lock->save();

                    SavingsTransaction::create([
                        'user_id' => $lock->user_id,
                        'savingsable_id' => $lock->id,
                        'savingsable_type' => SafeLock::class,
                        'amount' => $totalAmount,
                        'balance_after' => 0,
                        'type' => 'withdrawal',
                        'status' => 'success',
                        'source' => 'safelock',
                        'narration' => 'SafeLock Maturity: ' . $lock->title
                    ]);
                }
            });
        }
    }

    /**
     * Process Target Savings Auto-Save.
     */
    public function processTargetAutoSave()
    {
        $targets = TargetSavings::where('status', 'active')
            ->where('next_save_date', '<=', now())
            ->where('auto_save_amount', '>', 0)
            ->get();

        foreach ($targets as $target) {
            DB::transaction(function () use ($target) {
                $wallet = UserWallet::where('user_id', $target->user_id)
                    ->where('currency_code', 'NGN')
                    ->first();

                if ($wallet && $wallet->balance >= $target->auto_save_amount) {
                    $wallet->balance -= $target->auto_save_amount;
                    $wallet->save();

                    $target->current_balance += $target->auto_save_amount;
                    
                    // Update next save date
                    $target->next_save_date = $this->calculateNextSaveDate($target->frequency, $target->next_save_date);
                    $target->save();

                    SavingsTransaction::create([
                        'user_id' => $target->user_id,
                        'savingsable_id' => $target->id,
                        'savingsable_type' => TargetSavings::class,
                        'amount' => $target->auto_save_amount,
                        'balance_after' => $target->current_balance,
                        'type' => 'deposit',
                        'status' => 'success',
                        'source' => 'wallet',
                        'narration' => 'Target Auto-Save: ' . $target->title
                    ]);
                } else {
                    Log::warning("Target Auto-Save failed for User {$target->user_id}: Insufficient Wallet Balance.");
                }
            });
        }
    }

    protected function calculateNextSaveDate($frequency, $currentDate)
    {
        $date = Carbon::parse($currentDate);
        switch ($frequency) {
            case 'daily':
                return $date->addDay();
            case 'weekly':
                return $date->addWeek();
            case 'monthly':
                return $date->addMonth();
            default:
                return $date->addMonth();
        }
    }
}
