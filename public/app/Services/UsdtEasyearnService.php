<?php

namespace App\Services;

use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnInterestCredit;
use App\Models\UsdtEasyearnSetting;
use App\Models\User;
use App\Models\Admin;
use App\Services\WalletService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsdtEasyearnService
{
    /**
     * Create a new USDT EasyEarn investment
     */
    public function createInvestment(User $user, float $amount, bool $autoCompound = false)
    {
        $settings = UsdtEasyearnSetting::getSettings();

        // Validate settings
        if (!$settings->investmentsEnabled()) {
            throw new Exception("USDT EasyEarn investments are currently disabled.");
        }

        if (!$settings->isValidAmount($amount)) {
            throw new Exception("Investment amount must be between {$settings->min_investment} and " . ($settings->max_investment ?? 'unlimited') . " USDT.");
        }

        // Check USDT balance from Quidax
        if (!$user->quidax_id) {
            throw new Exception("Quidax account not found. Please complete your account setup.");
        }

        $quidaxService = new \App\Services\QuidaxService();
        $walletResponse = $quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        
        if (!isset($walletResponse['data'])) {
            throw new Exception("Unable to fetch USDT wallet from Quidax.");
        }

        $quidaxBalance = $walletResponse['data']['balance'] ?? 0;
        
        // Get or create user's USDT EasyEarn internal wallet (for tracking locked amounts)
        $easyearnWallet = $this->getOrCreateEasyearnWallet($user);
        
        // Calculate total locked amount in EasyEarn
        $lockedAmount = $easyearnWallet->locked_balance;
        
        // Available balance = Quidax balance - locked in EasyEarn
        $availableBalance = bcsub($quidaxBalance, $lockedAmount, 8);

        if (bccomp($availableBalance, $amount, 8) < 0) {
            throw new Exception("Insufficient USDT balance. Available: {$availableBalance} USDT)");
        }

        return DB::transaction(function () use ($user, $amount, $autoCompound, $easyearnWallet, $settings, $quidaxService, $quidaxBalance) {
            // Transfer USDT from user's Quidax account to main account (escrow)
            try {
                $withdrawalResponse = $quidaxService->transferToEscrow($user->quidax_id, $amount, 'usdt');
                
                if (!isset($withdrawalResponse['data'])) {
                    throw new Exception("Failed to lock USDT in escrow. Please try again.");
                }
                
                Log::info("USDT transferred to escrow for EasyEarn", [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'withdrawal_id' => $withdrawalResponse['data']['id'] ?? null
                ]);
                
            } catch (\Exception $e) {
                throw new Exception("Failed to lock USDT: " . $e->getMessage());
            }

            // Lock the amount in EasyEarn wallet (internal tracking only)
            $easyearnWallet->lockAmount($amount);

            // Calculate dates
            $startDate = Carbon::now();
            $endDate = $startDate->copy()->addMonths(12);

            // Create investment
            $investment = UsdtEasyearnInvestment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'duration_months' => 12,
                'interest_rate' => $settings->current_monthly_rate,
                'auto_compound' => $autoCompound,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
                'total_interest_earned' => 0,
            ]);

            Log::info("USDT EasyEarn Investment Created", [
                'user_id' => $user->id,
                'investment_id' => $investment->id,
                'amount' => $amount,
                'auto_compound' => $autoCompound,
                'quidax_balance' => $quidaxBalance ?? 'unknown'
            ]);

            return $investment;
        });
    }

    /**
     * Get or create USDT EasyEarn wallet for user
     */
    private function getOrCreateEasyearnWallet(User $user)
    {
        $wallet = $user->easyearnWallet;
        
        if ($wallet) {
            return $wallet;
        }

        // Create new EasyEarn wallet
        $wallet = \App\Models\UsdtEasyearnWallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'locked_balance' => 0,
            'status' => true,
        ]);

        Log::info("USDT EasyEarn wallet created for user", ['user_id' => $user->id]);

        return $wallet;
    }

    /**
     * Credit monthly interest for a specific investment
     */
    public function creditMonthlyInterest(UsdtEasyearnInvestment $investment, ?Admin $admin = null)
    {
        if ($investment->status !== 'active') {
            throw new Exception("Investment is not active.");
        }

        if (!$investment->isEligibleForCredit()) {
            throw new Exception("Investment is not eligible for interest credit yet.");
        }

        $interestAmount = $investment->calculateMonthlyInterest();

        return DB::transaction(function () use ($investment, $interestAmount, $admin) {
            $user = $investment->user;
            $transactionId = null;

            // If not auto-compound, credit to user's EasyEarn wallet
            if (!$investment->auto_compound) {
                $wallet = $this->getOrCreateEasyearnWallet($user);
                
                // Credit interest to EasyEarn wallet balance
                $wallet->credit($interestAmount);

                Log::info("Interest credited to EasyEarn wallet", [
                    'user_id' => $user->id,
                    'amount' => $interestAmount
                ]);
            }

            // Update investment
            $updateData = [
                'total_interest_earned' => bcadd($investment->total_interest_earned, $interestAmount, 8),
                'last_interest_credit_date' => Carbon::now(),
            ];

            // If auto-compound, add interest to the principal amount
            if ($investment->auto_compound) {
                $updateData['amount'] = bcadd($investment->amount, $interestAmount, 8);
                
                // Also update the locked balance in the wallet
                $wallet = $this->getOrCreateEasyearnWallet($user);
                $wallet->update([
                    'locked_balance' => bcadd($wallet->locked_balance, $interestAmount, 8)
                ]);
            }

            $investment->update($updateData);

            // Create credit record
            $credit = UsdtEasyearnInterestCredit::create([
                'investment_id' => $investment->id,
                'amount' => $interestAmount,
                'credit_date' => Carbon::now(),
                'credited_by' => $admin?->id,
                'transaction_id' => $transactionId,
            ]);

            Log::info("USDT EasyEarn Interest Credited", [
                'investment_id' => $investment->id,
                'amount' => $interestAmount,
                'auto_compound' => $investment->auto_compound,
                'admin_id' => $admin?->id
            ]);

            return $credit;
        });
    }

    /**
     * Bulk credit interest for all eligible investments
     */
    public function bulkCreditInterest(?Admin $admin = null)
    {
        $eligibleInvestments = UsdtEasyearnInvestment::eligibleForCredit()->get();
        
        $results = [
            'total' => $eligibleInvestments->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($eligibleInvestments as $investment) {
            try {
                $this->creditMonthlyInterest($investment, $admin);
                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'investment_id' => $investment->id,
                    'error' => $e->getMessage()
                ];
                Log::error("Failed to credit interest for investment {$investment->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Withdraw accumulated interest (non-auto-compound only)
     */
    public function withdrawInterest(UsdtEasyearnInvestment $investment)
    {
        if (!$investment->canWithdrawInterest()) {
            throw new Exception("Cannot withdraw interest from this investment.");
        }

        $withdrawableAmount = $investment->getTotalWithdrawableInterest();

        if (bccomp($withdrawableAmount, 0, 8) <= 0) {
            throw new Exception("No interest available to withdraw.");
        }

        return DB::transaction(function () use ($investment, $withdrawableAmount) {
            // Interest is already in user's wallet (credited monthly)
            // Just reset the total_interest_earned counter
            $investment->update([
                'total_interest_earned' => 0,
            ]);

            Log::info("USDT EasyEarn Interest Withdrawn", [
                'investment_id' => $investment->id,
                'amount' => $withdrawableAmount
            ]);

            return $withdrawableAmount;
        });
    }

    /**
     * Withdraw principal at maturity
     */
    public function withdrawPrincipal(UsdtEasyearnInvestment $investment)
    {
        if (!$investment->canWithdrawPrincipal()) {
            throw new Exception("Investment has not matured yet.");
        }

        if ($investment->status !== 'active') {
            throw new Exception("Investment is not active.");
        }

        $withdrawableAmount = $investment->getTotalWithdrawableAtMaturity();

        return DB::transaction(function () use ($investment, $withdrawableAmount) {
            $user = $investment->user;
            $easyearnWallet = $this->getOrCreateEasyearnWallet($user);

            // Transfer USDT from main account back to user's Quidax account
            try {
                $quidaxService = new \App\Services\QuidaxService();
                $fundResponse = $quidaxService->fundSubAccount($user->quidax_id, $withdrawableAmount, 'usdt');
                
                if (!isset($fundResponse['data'])) {
                    throw new Exception("Failed to return USDT to your account. Please contact support.");
                }
                
                Log::info("USDT returned from escrow to user", [
                    'user_id' => $user->id,
                    'investment_id' => $investment->id,
                    'amount' => $withdrawableAmount
                ]);
                
            } catch (\Exception $e) {
                throw new Exception("Failed to return USDT: " . $e->getMessage());
            }

            // Unlock the principal
            $easyearnWallet->unlockAmount($investment->amount);

            // Mark investment as completed
            $investment->update([
                'status' => 'completed',
            ]);

            Log::info("USDT EasyEarn Principal Withdrawn", [
                'investment_id' => $investment->id,
                'amount' => $withdrawableAmount,
                'auto_compound' => $investment->auto_compound
            ]);

            return $withdrawableAmount;
        });
    }

    /**
     * Top up an existing investment
     */
    public function topUp(User $user, UsdtEasyearnInvestment $investment, float $amount)
    {
        if ($investment->status !== 'active') {
            throw new Exception("Only active investments can be topped up.");
        }

        $settings = UsdtEasyearnSetting::getSettings();

        // Check USDT balance from Quidax
        if (!$user->quidax_id) {
            throw new Exception("Quidax account not found. Please complete your account setup.");
        }

        $quidaxService = new \App\Services\QuidaxService();
        $walletResponse = $quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        
        if (!isset($walletResponse['data'])) {
            throw new Exception("Unable to fetch USDT wallet from Quidax.");
        }

        $quidaxBalance = $walletResponse['data']['balance'] ?? 0;
        
        $easyearnWallet = $this->getOrCreateEasyearnWallet($user);
        $lockedAmount = $easyearnWallet->locked_balance;
        $availableBalance = bcsub($quidaxBalance, $lockedAmount, 8);

        if (bccomp($availableBalance, $amount, 8) < 0) {
            throw new Exception("Insufficient USDT balance to top up. Available: {$availableBalance} USDT");
        }

        return DB::transaction(function () use ($user, $investment, $amount, $easyearnWallet, $quidaxService) {
            // Transfer USDT to escrow
            try {
                $withdrawalResponse = $quidaxService->transferToEscrow($user->quidax_id, $amount, 'usdt');
                if (!isset($withdrawalResponse['data'])) {
                    throw new Exception("Failed to lock USDT in escrow.");
                }
            } catch (\Exception $e) {
                throw new Exception("Failed to lock USDT: " . $e->getMessage());
            }

            // Lock in EasyEarn wallet
            $easyearnWallet->lockAmount($amount);

            // Update investment
            $investment->update([
                'amount' => bcadd($investment->amount, $amount, 8)
            ]);

            Log::info("USDT EasyEarn Investment Topped Up", [
                'user_id' => $user->id,
                'investment_id' => $investment->id,
                'top_up_amount' => $amount,
                'new_total_amount' => $investment->amount
            ]);

            return $investment;
        });
    }

    /**
     * Calculate daily display amount for UI
     */
    public function calculateDailyDisplay(UsdtEasyearnInvestment $investment)
    {
        return $investment->calculateDailyDisplay();
    }

    /**
     * Get investment summary for user
     */
    public function getInvestmentSummary(UsdtEasyearnInvestment $investment)
    {
        return [
            'id' => $investment->id,
            'amount' => $investment->amount,
            'interest_rate' => $investment->interest_rate,
            'auto_compound' => $investment->auto_compound,
            'rate_label' => $investment->auto_compound ? '200% Annual Compounding' : $investment->interest_rate . '% Monthly',
            'start_date' => $investment->start_date->format('Y-m-d'),
            'end_date' => $investment->end_date->format('Y-m-d'),
            'status' => $investment->status,
            'total_interest_earned' => $investment->total_interest_earned,
            'withdrawable_interest' => $investment->getTotalWithdrawableInterest(),
            'withdrawable_at_maturity' => $investment->getTotalWithdrawableAtMaturity(),
            'daily_display' => $this->calculateDailyDisplay($investment),
            'months_completed' => $investment->getMonthsCompleted(),
            'days_remaining' => $investment->getDaysRemaining(),
            'is_matured' => $investment->isMatured(),
            'can_withdraw_interest' => $investment->canWithdrawInterest(),
            'can_withdraw_principal' => $investment->canWithdrawPrincipal(),
        ];
    }
}
