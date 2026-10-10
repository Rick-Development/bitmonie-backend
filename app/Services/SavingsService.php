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
    protected int $scale = 18;
    protected int $storageScale = 8;

    /**
     * Process Daily Interest Accrual for Flex and SafeLock.
     */
    public function calculateDailyInterest(?int $userId = null): array
    {
        $summary = [
            'user_id' => $userId,
            'flex_accounts' => 0,
            'flex_interest' => '0',
            'safe_locks' => 0,
            'safe_lock_interest' => '0',
        ];

        Log::info('Savings daily interest calculation started.', [
            'user_id' => $userId,
            'date' => now()->toDateString(),
        ]);

        DB::transaction(function () use (&$summary, $userId) {
            $flexSummary = $this->processFlexInterest($userId);
            $safeLockSummary = $this->processSafeLockInterest($userId);

            $summary['flex_accounts'] = $flexSummary['count'];
            $summary['flex_interest'] = $flexSummary['interest'];
            $summary['safe_locks'] = $safeLockSummary['count'];
            $summary['safe_lock_interest'] = $safeLockSummary['interest'];
        });

        Log::info('Savings daily interest calculation completed.', $summary);

        return $summary;
    }

    public function recalculateUserSavings(int $userId): array
    {
        return $this->calculateDailyInterest($userId);
    }

    protected function processFlexInterest(?int $userId = null): array
    {
        $today = now()->startOfDay();
        $query = FlexSavings::where('status', true)
            ->where('balance', '>', 0)
            ->where(function ($query) use ($today) {
                $query->whereNull('last_interest_date')
                    ->orWhereDate('last_interest_date', '<', $today->toDateString());
            })
            ->lockForUpdate();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $summary = ['count' => 0, 'interest' => '0'];

        foreach ($query->get() as $flex) {
            $days = $this->eligibleInterestDays($flex->last_interest_date, $flex->created_at, $today);

            if ($days <= 0) {
                continue;
            }

            // Exclude deposits made since the last interest date to prevent instant credit.
            $startDate = $flex->last_interest_date 
                ? Carbon::parse($flex->last_interest_date) 
                : Carbon::parse($flex->created_at);
                
            $recentDeposits = \App\Models\SavingsTransaction::where('savingsable_id', $flex->id)
                ->where('savingsable_type', FlexSavings::class)
                ->where('type', 'deposit')
                ->where('created_at', '>=', $startDate)
                ->sum('amount');

            $eligibleBalance = $this->sub($flex->balance, $recentDeposits);

            if ($this->compare($eligibleBalance, '0') <= 0) {
                continue;
            }

            // 5% Monthly Yield = ~60% APR
            $interest = $this->calculateInterest($eligibleBalance, '60', $days);
            if ($this->compare($interest, '0') <= 0) {
                continue;
            }

            $flex->balance = $this->formatDecimal($this->add($flex->balance, $interest));
            $flex->accrued_interest = $this->formatDecimal($this->add($flex->accrued_interest, $interest));
            $flex->last_interest_date = $today;
            $flex->save();

            SavingsTransaction::create([
                'user_id' => $flex->user_id,
                'savingsable_id' => $flex->id,
                'savingsable_type' => FlexSavings::class,
                'amount' => $this->formatDecimal($interest),
                'balance_after' => $flex->balance,
                'type' => 'interest',
                'status' => 'success',
                'source' => 'daily_interest',
                'narration' => 'Flex Savings Daily Interest: ' . $days . ' day(s) at 5% Monthly (60% APR)',
            ]);

            $summary['count']++;
            $summary['interest'] = $this->formatDecimal($this->add($summary['interest'], $interest));

            Log::info('Flex savings interest accrued.', [
                'user_id' => $flex->user_id,
                'flex_savings_id' => $flex->id,
                'days' => $days,
                'balance' => (string) $flex->balance,
                'interest' => $this->formatDecimal($interest),
                'accrued_interest' => (string) $flex->accrued_interest,
            ]);
        }

        return $summary;
    }

    protected function processSafeLockInterest(?int $userId = null): array
    {
        $today = now()->startOfDay();
        $query = SafeLock::where('status', 'active')
            ->where('is_redeemed', false)
            ->where('amount', '>', 0)
            ->where(function ($query) use ($today) {
                $query->whereNull('last_interest_date')
                    ->orWhereDate('last_interest_date', '<', $today->toDateString());
            })
            ->lockForUpdate();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $summary = ['count' => 0, 'interest' => '0'];

        foreach ($query->get() as $lock) {
            $interestEndDate = $this->safeLockInterestEndDate($lock, $today);
            $days = $this->eligibleInterestDays($lock->last_interest_date, $lock->lock_date, $interestEndDate);

            if ($days <= 0) {
                continue;
            }

            $durationDays = max(1, Carbon::parse($lock->lock_date)->startOfDay()->diffInDays(Carbon::parse($lock->maturity_date)->startOfDay()));
            $flatRateDecimal = $this->div($this->decimal($lock->interest_rate), '100');
            $dailyRate = $this->div($flatRateDecimal, (string) $durationDays);
            $dailyInterest = $this->mul($lock->amount, $dailyRate);
            $interest = $this->mul($dailyInterest, (string) $days);

            if ($this->compare($interest, '0') <= 0) {
                continue;
            }

            $lock->interest_accrued = $this->formatDecimal($this->add($lock->interest_accrued, $interest));
            $lock->last_interest_date = $interestEndDate;
            $lock->save();

            $summary['count']++;
            $summary['interest'] = $this->formatDecimal($this->add($summary['interest'], $interest));

            Log::info('SafeLock interest accrued.', [
                'user_id' => $lock->user_id,
                'safe_lock_id' => $lock->id,
                'days' => $days,
                'amount' => (string) $lock->amount,
                'interest_rate' => (string) $lock->interest_rate,
                'interest' => $this->formatDecimal($interest),
                'interest_accrued' => (string) $lock->interest_accrued,
                'maturity_date' => optional($lock->maturity_date)->toDateString(),
            ]);
        }

        return $summary;
    }

    /**
     * Process SafeLock Maturity.
     */
    public function processSafeLockMaturity(?int $userId = null): void
    {
        $maturedLocks = SafeLock::where('status', 'active')
            ->where('is_redeemed', false)
            ->where('maturity_date', '<=', now());

        if ($userId !== null) {
            $maturedLocks->where('user_id', $userId);
        }

        foreach ($maturedLocks->get() as $lock) {
            DB::transaction(function () use ($lock) {
                $this->recalculateUserSavings((int) $lock->user_id);
                $lock->refresh();

                $wallet = UserWallet::where('user_id', $lock->user_id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $expectedProfit = bcmul(
                        (string) $lock->amount,
                        bcdiv((string) ($lock->interest_rate ?? '0'), '100', 8),
                        8
                    );

                    if (bccomp((string) ($lock->interest_accrued ?? '0'), $expectedProfit, 8) < 0) {
                        $lock->interest_accrued = $expectedProfit;
                    }

                    $totalAmount = $this->formatDecimal($this->add($lock->amount, $lock->interest_accrued));
                    $wallet->balance = $this->formatDecimal($this->add($wallet->balance, $totalAmount));
                    $wallet->save();

                    $lock->is_redeemed = true;
                    $lock->status = 'completed';
                    $lock->save();

                    $reference = 'safelock:auto-maturity:' . $lock->id . ':' . \Illuminate\Support\Str::uuid();

                    SavingsTransaction::create([
                        'user_id' => $lock->user_id,
                        'savingsable_id' => $lock->id,
                        'savingsable_type' => SafeLock::class,
                        'amount' => $totalAmount,
                        'balance_after' => 0,
                        'type' => 'withdrawal',
                        'status' => 'success',
                        'source' => 'safelock',
                        'narration' => "SafeLock Matured - Principal: {$lock->amount}, Interest: {$lock->interest_accrued}",
                    ]);

                    \App\Models\OrderTransaction::create([
                        'user_wallet_id' => $wallet->id,
                        'type' => 'credit',
                        'amount' => $totalAmount,
                        'balance_after' => $wallet->balance,
                        'reference' => $reference,
                        'metadata' => [
                            'source' => 'savings',
                            'savings_type' => 'safelock',
                            'savings_id' => $lock->id,
                            'interest_earned' => (string) $lock->interest_accrued,
                            'principal' => (string) $lock->amount,
                        ],
                    ]);

                    Log::info('SafeLock matured and credited to wallet.', [
                        'user_id' => $lock->user_id,
                        'safe_lock_id' => $lock->id,
                        'principal' => (string) $lock->amount,
                        'interest_accrued' => (string) $lock->interest_accrued,
                        'total_amount' => $totalAmount,
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
            $this->recalculateUserSavings((int) $target->user_id);

            DB::transaction(function () use ($target) {
                $wallet = UserWallet::where('user_id', $target->user_id)
                    ->where('currency_code', 'NGN')
                    ->lockForUpdate()
                    ->first();

                if ($wallet && $this->compare($wallet->balance, $target->auto_save_amount) >= 0) {
                    $wallet->balance = $this->formatDecimal($this->sub($wallet->balance, $target->auto_save_amount));
                    $wallet->save();

                    $target->current_balance = $this->formatDecimal($this->add($target->current_balance, $target->auto_save_amount));
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
                        'narration' => 'Target Auto-Save: ' . $target->title,
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

    protected function safeLockInterestEndDate(SafeLock $lock, Carbon $today): Carbon
    {
        if ($lock->maturity_date && $lock->maturity_date->copy()->startOfDay()->lt($today)) {
            return $lock->maturity_date->copy()->startOfDay();
        }

        return $today;
    }

    protected function eligibleInterestDays($lastInterestDate, $createdDate, Carbon $endDate): int
    {
        $startDate = $lastInterestDate
            ? Carbon::parse($lastInterestDate)->startOfDay()
            : Carbon::parse($createdDate ?: now())->startOfDay();

        return max(0, $startDate->diffInDays($endDate));
    }

    protected function calculateInterest($principal, $annualRatePercent, int $days): string
    {
        if ($days <= 0) {
            return '0';
        }

        $rate = $this->div($this->decimal($annualRatePercent), '100');
        $dailyRate = $this->div($rate, '365');
        $dailyInterest = $this->mul($principal, $dailyRate);

        return $this->mul($dailyInterest, (string) $days);
    }

    protected function decimal($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            $value = sprintf('%.18F', $value);
        }

        $value = trim((string) $value);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        return bcadd($value, '0', $this->scale);
    }

    protected function add($left, $right): string
    {
        return bcadd($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function sub($left, $right): string
    {
        return bcsub($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function mul($left, $right): string
    {
        return bcmul($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function div($left, $right): string
    {
        if ($this->compare($right, '0') === 0) {
            return '0';
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function compare($left, $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), $this->storageScale);
    }

    protected function formatDecimal($value): string
    {
        $formatted = bcadd($this->decimal($value), '0', $this->storageScale);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * Format a savings amount with proper currency code, symbol, and precision.
     */
    public static function formatSavingsCurrency($amount, string $currency = 'NGN'): array
    {
        $curr = strtoupper(trim($currency));
        $val = (float) ($amount ?? 0);

        if ($curr === 'USDT' || $curr === 'USD') {
            $formattedAmount = number_format($val, 8, '.', '');
            $displayFormatted = rtrim(rtrim($formattedAmount, '0'), '.') . ' ' . $curr;
            return [
                'amount' => $formattedAmount,
                'currency' => $curr,
                'currency_symbol' => $curr,
                'display' => $displayFormatted,
            ];
        }

        $formattedAmount = number_format($val, 2, '.', ',');
        return [
            'amount' => number_format($val, 2, '.', ''),
            'currency' => 'NGN',
            'currency_symbol' => '₦',
            'display' => '₦' . $formattedAmount,
        ];
    }

    /**
     * Aggregate user's entire multi-currency savings portfolio (NGN savings + USDT EasyEarn).
     */
    public function getUserSavingsPortfolio(int $userId): array
    {
        $flexBalance = FlexSavings::where('user_id', $userId)->where('status', true)->sum('balance') ?? 0;
        $safeLockBalance = SafeLock::where('user_id', $userId)->where('status', 'active')->sum('amount') ?? 0;
        $targetBalance = TargetSavings::where('user_id', $userId)->where('status', 'active')->sum('current_amount') ?? 0;

        $totalNgn = bcadd(
            bcadd((string) $flexBalance, (string) $safeLockBalance, 2),
            (string) $targetBalance,
            2
        );

        $usdtWallet = \App\Models\UsdtEasyearnWallet::where('user_id', $userId)->first();
        $usdtActivePrincipal = $usdtWallet ? (string) $usdtWallet->active_investment : '0.00000000';
        $usdtEarnedInterest = $usdtWallet ? (string) $usdtWallet->total_earned : '0.00000000';

        return [
            'ngn_savings' => [
                'currency' => 'NGN',
                'currency_symbol' => '₦',
                'total_balance' => $totalNgn,
                'flex_balance' => number_format((float) $flexBalance, 2, '.', ''),
                'safelock_balance' => number_format((float) $safeLockBalance, 2, '.', ''),
                'target_balance' => number_format((float) $targetBalance, 2, '.', ''),
            ],
            'usdt_savings' => [
                'currency' => 'USDT',
                'currency_symbol' => 'USDT',
                'active_principal' => $usdtActivePrincipal,
                'total_earned_interest' => $usdtEarnedInterest,
            ],
        ];
    }
}
