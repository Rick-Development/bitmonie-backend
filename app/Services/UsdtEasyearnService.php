<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\UsdtEasyearnInterestCredit;
use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnSetting;
use App\Models\User;
use App\Services\QuidaxService;
use App\Services\QuidaxSpendableBalanceService;
use App\Traits\Notify;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsdtEasyearnService
{
    use Notify;

    /**
     * Create a new USDT EasyEarn investment.
     */
    public function createInvestment(
        User $user,
        float $amount,
        bool $autoCompound = false
    ): UsdtEasyearnInvestment {
        $settings = UsdtEasyearnSetting::getSettings();

        if (!$settings->investmentsEnabled()) {
            throw new Exception(
                'USDT EasyEarn investments are currently disabled.'
            );
        }

        if (!$settings->isValidAmount($amount)) {
            throw new Exception(
                'Investment amount must be between ' .
                $settings->min_investment .
                ' and ' .
                ($settings->max_investment ?? 'unlimited') .
                ' USDT.'
            );
        }

        $availableBalance = $this->getAvailableUsdtBalance($user);

        if (bccomp($availableBalance, (string) $amount, 8) < 0) {
            throw new Exception(
                "Insufficient USDT balance. Available: {$availableBalance} USDT"
            );
        }

        $easyearnWallet = $this->getOrCreateEasyearnWallet($user);
        $quidaxService = app(QuidaxService::class);
        $quidaxBalance = $availableBalance;

        $investment = DB::transaction(function () use (
            $user,
            $amount,
            $autoCompound,
            $easyearnWallet,
            $settings,
            $quidaxService,
            $quidaxBalance
        ): UsdtEasyearnInvestment {
            $escrowRef = 'EE-INVEST-' . $user->id . '-' . \Illuminate\Support\Str::uuid()->toString();
            try {
                $withdrawalResponse = $quidaxService->transferToEscrow(
                    $user->quidax_id,
                    $amount,
                    'usdt',
                    'EasyEarn investment lock: ' . $escrowRef,
                    $escrowRef
                );

                if (!isset($withdrawalResponse['data'])) {
                    throw new Exception(
                        'Failed to lock USDT in escrow. Please try again.'
                    );
                }

                Log::channel('easyearn_usdt')->info(
                    'USDT transferred to escrow for EasyEarn',
                    [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'withdrawal_id' =>
                            $withdrawalResponse['data']['id'] ?? null,
                    ]
                );
            } catch (Exception $e) {
                Log::channel('easyearn_usdt')->error(
                    'Failed to transfer USDT to EasyEarn escrow',
                    [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'error' => $e->getMessage(),
                    ]
                );

                throw new Exception(
                    'Failed to lock USDT: ' . $e->getMessage()
                );
            }

            try {
                $easyearnWallet->lockAmount($amount);

                $startDate = Carbon::now();
                $endDate = $startDate->copy()->addMonths(12);

                $powerBonusMultiplier = (string) ($settings->power_bonus_multiplier ?? 2.00);

                $investment = UsdtEasyearnInvestment::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'initial_deposit_amount' => $amount,
                    'duration_months' => 12,
                    'interest_rate' => $settings->current_monthly_rate,
                    'auto_compound' => $autoCompound,
                    'power_bonus_multiplier' => $autoCompound ? $powerBonusMultiplier : 1.00,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'total_interest_earned' => 0,
                ]);
            } catch (\Throwable $localFailure) {
                // The DB rolls back, but the escrow transfer already happened:
                // return the USDT to the user's Quidax sub-account.
                Log::channel('easyearn_usdt')->error(
                    'Local failure after USDT escrow transfer; refunding user',
                    [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'reference' => $escrowRef,
                        'error' => $localFailure->getMessage(),
                    ]
                );

                try {
                    $quidaxService->fundSubAccount(
                        $user->quidax_id,
                        $amount,
                        'usdt',
                        $escrowRef . '-REFUND',
                        'EasyEarn investment reversal: ' . $escrowRef
                    );
                } catch (\Throwable $refundFailure) {
                    Log::channel('easyearn_usdt')->critical(
                        'EasyEarn escrow refund FAILED - manual reconciliation required',
                        [
                            'user_id' => $user->id,
                            'amount' => $amount,
                            'reference' => $escrowRef,
                            'error' => $refundFailure->getMessage(),
                        ]
                    );
                }

                throw $localFailure;
            }

            Log::channel('easyearn_usdt')->info(
                'USDT EasyEarn Investment Created',
                [
                    'user_id' => $user->id,
                    'investment_id' => $investment->id,
                    'amount' => $amount,
                    'auto_compound' => $autoCompound,
                    'quidax_balance' => $quidaxBalance,
                ]
            );

            return $investment;
        });

        /*
         * Send notification only after the database transaction
         * has successfully committed.
         */
        $this->sendEasyEarnNotification(
            $user,
            'EASY_EARN_INVESTMENT_CREATED',
            [
                'investment_id'  => $investment->id,
                'amount'         => $investment->amount,
                'asset'          => 'USDT',
                'interest_rate'  => $investment->interest_rate,
                'duration'       => $investment->duration_months,
                'start_date'     => $investment->start_date?->format('Y-m-d'),
                'end_date'       => $investment->end_date?->format('Y-m-d'),
                'auto_compound'  => $investment->auto_compound ? 'Yes' : 'No',
            ]
        );

        return $investment;
    }

    /**
     * Get or create USDT EasyEarn wallet for user.
     */
    private function getOrCreateEasyearnWallet(User $user)
    {
        $wallet = $user->easyearnWallet;

        if ($wallet) {
            return $wallet;
        }

        $wallet = \App\Models\UsdtEasyearnWallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'locked_balance' => 0,
            'status' => true,
        ]);

        Log::channel('easyearn_usdt')->info(
            'USDT EasyEarn wallet created for user',
            [
                'user_id' => $user->id,
            ]
        );

        return $wallet;
    }

    /**
     * Credit daily accrued interest for a specific investment.
     */
    public function creditMonthlyInterest(
        UsdtEasyearnInvestment $investment,
        ?Admin $admin = null
    ): UsdtEasyearnInterestCredit {
        if ($investment->status !== 'active') {
            throw new Exception('Investment is not active.');
        }

        $days = $investment->dailyCreditDays();

        if ($days <= 0) {
            throw new Exception(
                'Investment is not eligible for interest credit today.'
            );
        }

        $interestAmount = $investment->calculateDailyInterest($days);

        $credit = DB::transaction(function () use (
            $investment,
            $interestAmount,
            $admin,
            $days
        ): UsdtEasyearnInterestCredit {
            $user = $investment->user;
            $transactionId = null;

            if (!$investment->auto_compound) {
                $wallet = $this->getOrCreateEasyearnWallet($user);

                $wallet->credit($interestAmount);

                Log::channel('easyearn_usdt')->info(
                    'Interest credited to EasyEarn wallet',
                    [
                        'user_id' => $user->id,
                        'investment_id' => $investment->id,
                        'amount' => $interestAmount,
                        'days' => $days,
                    ]
                );
            }

            $updateData = [
                'total_interest_earned' => bcadd(
                    (string) $investment->total_interest_earned,
                    (string) $interestAmount,
                    8
                ),
                'last_interest_credit_date' =>
                    Carbon::now()->startOfDay(),
            ];

            if ($investment->auto_compound) {
                $updateData['amount'] = bcadd(
                    (string) $investment->amount,
                    (string) $interestAmount,
                    8
                );

                $wallet = $this->getOrCreateEasyearnWallet($user);

                $wallet->update([
                    'locked_balance' => bcadd(
                        (string) $wallet->locked_balance,
                        (string) $interestAmount,
                        8
                    ),
                ]);
            }

            $investment->update($updateData);

            $credit = UsdtEasyearnInterestCredit::create([
                'investment_id' => $investment->id,
                'amount' => $interestAmount,
                'credit_date' => Carbon::now()->startOfDay(),
                'credited_by' => $admin?->id,
                'transaction_id' => $transactionId,
            ]);

            Log::channel('easyearn_usdt')->info(
                'USDT EasyEarn Interest Credited',
                [
                    'investment_id' => $investment->id,
                    'amount' => $interestAmount,
                    'days' => $days,
                    'auto_compound' => $investment->auto_compound,
                    'admin_id' => $admin?->id,
                ]
            );

            return $credit;
        });

        /*
         * Refresh the investment so the notification contains
         * the latest total interest amount.
         */
        $investment->refresh();

        $this->sendEasyEarnNotification(
            $investment->user,
            'EASY_EARN_INTEREST_CREDITED',
            [
                'investment_id'         => $investment->id,
                'amount'                => $interestAmount,
                'asset'                 => 'USDT',
                'days'                  => $days,
                'auto_compound'         => $investment->auto_compound ? 'Yes' : 'No',
                'total_interest_earned' => $investment->total_interest_earned,
            ]
        );

        return $credit;
    }

    /**
     * Bulk credit daily interest for all eligible investments.
     */
    public function bulkCreditInterest(?Admin $admin = null): array
    {
        $eligibleInvestments =
            UsdtEasyearnInvestment::eligibleForCredit()->get();

        $results = [
            'total' => $eligibleInvestments->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($eligibleInvestments as $investment) {
            try {
                $this->creditMonthlyInterest(
                    $investment,
                    $admin
                );

                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;

                $results['errors'][] = [
                    'investment_id' => $investment->id,
                    'error' => $e->getMessage(),
                ];

                Log::channel('easyearn_usdt')->error(
                    'Failed to credit interest',
                    [
                        'investment_id' => $investment->id,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $results;
    }

    /**
     * Withdraw accumulated interest.
     *
     * Non-auto-compound investments only.
     */
    // public function withdrawInterest(
    //     UsdtEasyearnInvestment $investment
    // ): string {
    //     if (!$investment->canWithdrawInterest()) {
    //         throw new Exception(
    //             'Cannot withdraw interest from this investment.'
    //         );
    //     }

    //     $withdrawableAmount =
    //         $investment->getTotalWithdrawableInterest();

    //     if (bccomp((string) $withdrawableAmount, '0', 8) <= 0) {
    //         throw new Exception(
    //             'No interest available to withdraw.'
    //         );
    //     }

    //     $withdrawnAmount = DB::transaction(
    //         function () use (
    //             $investment,
    //             $withdrawableAmount
    //         ): string {
    //             $investment->update([
    //                 'total_interest_earned' => 0,
    //             ]);

    //             Log::channel('easyearn_usdt')->info(
    //                 'USDT EasyEarn Interest Withdrawn',
    //                 [
    //                     'investment_id' => $investment->id,
    //                     'amount' => $withdrawableAmount,
    //                 ]
    //             );

    //             return (string) $withdrawableAmount;
    //         }
    //     );

    //     $this->sendEasyEarnNotification(
    //         $investment->user,
    //         'EASY_EARN_INTEREST_WITHDRAWN',
    //         [
    //             'investment_id' => $investment->id,
    //             'amount'        => $withdrawnAmount,
    //             'asset'         => 'USDT',
    //         ]
    //     );

    //     return $withdrawnAmount;
    // }
    /**
 * Withdraw accumulated interest.
 *
 * Non-auto-compound investments only.
 * Interest was previously credited to the EasyEarn wallet balance.
 * This method:
 *  1. Debits the EasyEarn wallet
 *  2. Sends USDT to the user's Quidax account
 *  3. Resets total_interest_earned to 0
 */
public function withdrawInterest(
    UsdtEasyearnInvestment $investment
): string {
    if (!$investment->canWithdrawInterest()) {
        throw new Exception(
            'Cannot withdraw interest from this investment.'
        );
    }

    $withdrawableAmount = $investment->getTotalWithdrawableInterest();

    if (bccomp((string) $withdrawableAmount, '0', 8) <= 0) {
        throw new Exception(
            'No interest available to withdraw.'
        );
    }

    $user = $investment->user;

    if (!$user || !$user->quidax_id) {
        throw new Exception(
            'Quidax account not found. Please contact support.'
        );
    }

    $withdrawnAmount = DB::transaction(
        function () use (
            $investment,
            $withdrawableAmount,
            $user
        ): string {
            $easyearnWallet = $this->getOrCreateEasyearnWallet($user);

            // Ensure wallet has enough available (unlocked) balance
            if (bccomp(
                (string) $easyearnWallet->balance,
                (string) $withdrawableAmount,
                8
            ) < 0) {
                throw new Exception(
                    'Insufficient EasyEarn wallet balance to withdraw interest.'
                );
            }

            // 1. Debit EasyEarn wallet first (so we don't overpay if Quidax fails after)
            $easyearnWallet->debit($withdrawableAmount);

            // 2. Send USDT to user's Quidax sub-account
            try {
                $quidaxService = new QuidaxService();

                $reference = $this->reference(
                    'interest',
                    $investment->id
                );

                $fundResponse = $quidaxService->fundSubAccount(
                    $user->quidax_id,
                    $withdrawableAmount,
                    'usdt',
                    $reference,
                    'USDT EasyEarn Interest Withdrawal'
                );

                if (
                    !is_array($fundResponse)
                    || ($fundResponse['status'] ?? null) !== 'success'
                    || !is_array($fundResponse['data'] ?? null)
                    || empty($fundResponse['data']['id'])
                ) {
                    Log::channel('easyearn_usdt')->error(
                        'USDT EasyEarn Interest Payout Failed',
                        [
                            'user_id'       => $user->id,
                            'investment_id' => $investment->id,
                            'amount'        => $withdrawableAmount,
                            'response'      => $fundResponse,
                        ]
                    );

                    throw new Exception(
                        $fundResponse['message']
                            ?? 'Failed to send USDT to your Quidax account. Please contact support.'
                    );
                }

                Log::channel('easyearn_usdt')->info(
                    'USDT EasyEarn Interest returned to user',
                    [
                        'user_id'               => $user->id,
                        'investment_id'         => $investment->id,
                        'amount'                => $withdrawableAmount,
                        'quidax_transaction_id' => $fundResponse['data']['id'] ?? null,
                    ]
                );
            } catch (Exception $e) {
                Log::channel('easyearn_usdt')->error(
                    'Failed to return USDT interest',
                    [
                        'user_id'       => $user->id,
                        'investment_id' => $investment->id,
                        'amount'        => $withdrawableAmount,
                        'error'         => $e->getMessage(),
                    ]
                );

                // Re-throw so the transaction rolls back (wallet debit undone)
                throw new Exception(
                    'Failed to return USDT interest: ' . $e->getMessage()
                );
            }

            // 3. Clear interest on the investment
            $investment->update([
                'total_interest_earned' => 0,
            ]);

            Log::channel('easyearn_usdt')->info(
                'USDT EasyEarn Interest Withdrawn',
                [
                    'investment_id' => $investment->id,
                    'user_id'       => $user->id,
                    'amount'        => $withdrawableAmount,
                ]
            );

            return (string) $withdrawableAmount;
        }
    );

    $this->sendEasyEarnNotification(
        $investment->user,
        'EASY_EARN_INTEREST_WITHDRAWN',
        [
            'investment_id' => $investment->id,
            'amount'        => $withdrawnAmount,
            'asset'         => 'USDT',
        ]
    );

    return $withdrawnAmount;
}

      protected function reference(string $type, int $investmentId): string
    {
        return 'usdteasyearn:' . $type . ':' . $investmentId;
    }

    /**
     * Process maturity for an investment: credit 2X PowerBonus if applicable.
     */
    public function processMaturity(UsdtEasyearnInvestment $investment): array
    {
        if ($investment->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Investment is not active.',
            ];
        }

        if (!$investment->isMatured()) {
            return [
                'success' => false,
                'message' => 'Investment has not yet matured.',
            ];
        }

        if (!$investment->auto_compound) {
            return [
                'success' => true,
                'message' => 'Non-auto-compound investment matured. No PowerBonus applicable.',
                'power_bonus' => '0.00000000',
            ];
        }

        if ($investment->power_bonus_credited_at !== null) {
            return [
                'success' => true,
                'message' => 'PowerBonus already credited for this investment.',
                'power_bonus' => (string) $investment->power_bonus_amount,
            ];
        }

        $powerBonus = $investment->calculatePowerBonus();

        DB::transaction(function () use ($investment, $powerBonus) {
            $user = $investment->user;
            $wallet = $this->getOrCreateEasyearnWallet($user);

            if (bccomp($powerBonus, '0', 8) > 0) {
                // Lock the bonus into the user's EasyEarn wallet
                $wallet->update([
                    'locked_balance' => bcadd((string) $wallet->locked_balance, (string) $powerBonus, 8),
                ]);

                // Create interest credit record for ledger audit
                UsdtEasyearnInterestCredit::create([
                    'investment_id' => $investment->id,
                    'amount' => $powerBonus,
                    'credit_date' => Carbon::now()->startOfDay(),
                    'credited_by' => null,
                    'transaction_id' => null,
                ]);
            }

            $newAmount = bcadd((string) $investment->amount, (string) $powerBonus, 8);
            $newTotalInterest = bcadd((string) $investment->total_interest_earned, (string) $powerBonus, 8);

            $investment->update([
                'amount' => $newAmount,
                'total_interest_earned' => $newTotalInterest,
                'power_bonus_amount' => $powerBonus,
                'power_bonus_credited_at' => Carbon::now(),
            ]);

            Log::channel('easyearn_usdt')->info('USDT EasyEarn PowerBonus Credited at Maturity', [
                'investment_id' => $investment->id,
                'user_id' => $user->id,
                'power_bonus_amount' => $powerBonus,
                'new_total_amount' => $newAmount,
            ]);
        });

        $investment->refresh();

        $this->sendEasyEarnNotification(
            $investment->user,
            'EASY_EARN_POWER_BONUS_CREDITED',
            [
                'investment_id' => $investment->id,
                'power_bonus' => $powerBonus,
                'total_amount' => $investment->amount,
                'asset' => 'USDT',
            ]
        );

        return [
            'success' => true,
            'message' => 'PowerBonus credited successfully.',
            'power_bonus' => $powerBonus,
        ];
    }

    /**
     * Withdraw principal at maturity.
     */
    public function withdrawPrincipal(
        UsdtEasyearnInvestment $investment
    ): string {
        if (!$investment->canWithdrawPrincipal()) {
            throw new Exception(
                'Investment has not matured yet.'
            );
        }

        if ($investment->status !== 'active') {
            throw new Exception(
                'Investment is not active.'
            );
        }

        // Ensure maturity PowerBonus is credited if not yet done
        if ($investment->auto_compound && $investment->power_bonus_credited_at === null) {
            $this->processMaturity($investment);
            $investment->refresh();
        }

        $withdrawableAmount =
            $investment->getTotalWithdrawableAtMaturity();

        $withdrawnAmount = DB::transaction(
            function () use (
                $investment,
                $withdrawableAmount
            ): string {
                $user = $investment->user;

                $easyearnWallet =
                    $this->getOrCreateEasyearnWallet($user);

                try {
                    $quidaxService = new QuidaxService();

                    $fundResponse =
                        $quidaxService->fundSubAccount(
                            $user->quidax_id,
                            $withdrawableAmount,
                            'usdt'
                        );

                    if (!isset($fundResponse['data'])) {
                        throw new Exception(
                            'Failed to return USDT to your account. Please contact support.'
                        );
                    }

                    Log::channel('easyearn_usdt')->info(
                        'USDT returned from escrow to user',
                        [
                            'user_id' => $user->id,
                            'investment_id' => $investment->id,
                            'amount' => $withdrawableAmount,
                            'quidax_transaction_id' =>
                                $fundResponse['data']['id'] ?? null,
                        ]
                    );
                } catch (Exception $e) {
                    Log::channel('easyearn_usdt')->error(
                        'Failed to return USDT at maturity',
                        [
                            'user_id' => $user->id,
                            'investment_id' => $investment->id,
                            'amount' => $withdrawableAmount,
                            'error' => $e->getMessage(),
                        ]
                    );

                    throw new Exception(
                        'Failed to return USDT: ' .
                        $e->getMessage()
                    );
                }

                $easyearnWallet->unlockAmount(
                    $withdrawableAmount
                );

                $investment->update([
                    'amount' => '0.00000000',
                    'status' => 'completed',
                ]);

                Log::channel('easyearn_usdt')->info(
                    'USDT EasyEarn Principal Withdrawn',
                    [
                        'investment_id' => $investment->id,
                        'amount' => $withdrawableAmount,
                        'auto_compound' =>
                            $investment->auto_compound,
                    ]
                );

                return (string) $withdrawableAmount;
            }
        );

        $investment->refresh();

        $this->sendEasyEarnNotification(
            $investment->user,
            'EASY_EARN_PRINCIPAL_WITHDRAWN',
            [
                'investment_id' => $investment->id,
                'amount'        => $withdrawnAmount,
                'asset'         => 'USDT',
                'auto_compound' => $investment->auto_compound ? 'Yes' : 'No',
                'status'        => $investment->status,
            ]
        );

        return $withdrawnAmount;
    }

    /**
     * Top up an existing investment.
     */
    public function topUp(
        User $user,
        UsdtEasyearnInvestment $investment,
        float $amount
    ): UsdtEasyearnInvestment {
        if ($investment->status !== 'active') {
            throw new Exception(
                'Only active investments can be topped up.'
            );
        }

        if ($amount <= 0) {
            throw new Exception(
                'Top-up amount must be greater than zero.'
            );
        }

        $settings = UsdtEasyearnSetting::getSettings();

        if (!$settings->investmentsEnabled()) {
            throw new Exception(
                'USDT EasyEarn investments are currently disabled.'
            );
        }

        $availableBalance = $this->getAvailableUsdtBalance($user);

        if (bccomp($availableBalance, (string) $amount, 8) < 0) {
            throw new Exception(
                "Insufficient USDT balance to top up. Available: {$availableBalance} USDT"
            );
        }

        $easyearnWallet = $this->getOrCreateEasyearnWallet($user);
        $quidaxService = new QuidaxService();

        $investment = DB::transaction(
            function () use (
                $user,
                $investment,
                $amount,
                $easyearnWallet,
                $quidaxService
            ): UsdtEasyearnInvestment {
                $topUpRef = 'EE-TOPUP-' . $user->id . '-' . $investment->id . '-' . \Illuminate\Support\Str::uuid()->toString();
                try {
                    $withdrawalResponse =
                        $quidaxService->transferToEscrow(
                            $user->quidax_id,
                            $amount,
                            'usdt',
                            'EasyEarn topup lock: ' . $topUpRef,
                            $topUpRef
                        );

                    if (!isset($withdrawalResponse['data'])) {
                        throw new Exception(
                            'Failed to lock USDT in escrow.'
                        );
                    }
                } catch (Exception $e) {
                    Log::channel('easyearn_usdt')->error(
                        'Failed to top up EasyEarn investment',
                        [
                            'user_id' => $user->id,
                            'investment_id' => $investment->id,
                            'amount' => $amount,
                            'error' => $e->getMessage(),
                        ]
                    );

                    throw new Exception(
                        'Failed to lock USDT: ' .
                        $e->getMessage()
                    );
                }

                $easyearnWallet->lockAmount($amount);

                $investment->update([
                    'amount' => bcadd(
                        (string) $investment->amount,
                        (string) $amount,
                        8
                    ),
                ]);

                Log::channel('easyearn_usdt')->info(
                    'USDT EasyEarn Investment Topped Up',
                    [
                        'user_id' => $user->id,
                        'investment_id' => $investment->id,
                        'top_up_amount' => $amount,
                        'new_total_amount' =>
                            $investment->amount,
                    ]
                );

                return $investment;
            }
        );

        $investment->refresh();

        $this->sendEasyEarnNotification(
            $user,
            'EASY_EARN_TOP_UP',
            [
                'investment_id'    => $investment->id,
                'amount'           => $amount,                 // matches [[amount]] in template
                'new_total_amount' => $investment->amount,
                'asset'            => 'USDT',
            ]
        );

        return $investment;
    }

    /**
     * Calculate daily display amount for UI.
     */
    public function calculateDailyDisplay(
        UsdtEasyearnInvestment $investment
    ) {
        return $investment->calculateDailyDisplay();
    }

    /**
     * Get investment summary for user.
     */
    public function getInvestmentSummary(
        UsdtEasyearnInvestment $investment
    ): array {
        $settings = UsdtEasyearnSetting::getSettings();
        $isMatured = $investment->isMatured();
        $baseApy = (string) ($settings->base_apy ?? 15.00);
        $powerBonusMultiplier = (string) ($settings->power_bonus_multiplier ?? 2.00);
        $powerBonusApplies = (bool) $investment->auto_compound;

        $effectiveTotalInterest = $investment->getEffectiveTotalInterestEarned();
        $uncreditedAccruedInterest = $investment->calculateAccruedInterest();

        $rateLabel = $powerBonusApplies
            ? "{$baseApy}% APY + {$powerBonusMultiplier}X PowerBonus at maturity"
            : $investment->interest_rate . '% Monthly';

        $withdrawalInfo = $this->calculateWithdrawalBreakdown($investment);

        return [
            'id' => $investment->id,
            'amount' => $investment->amount,
            'interest_rate' => $investment->interest_rate,
            'base_apy' => $baseApy,
            'power_bonus_applies' => $powerBonusApplies,
            'power_bonus_multiplier' => $powerBonusMultiplier,
            'auto_compound' => $investment->auto_compound,
            'rate_label' => $rateLabel,

            'start_date' => $investment->start_date ? $investment->start_date->format('Y-m-d') : null,
            'end_date' => $investment->end_date ? $investment->end_date->format('Y-m-d') : null,
            'status' => $investment->status,

            'total_interest_earned' => $effectiveTotalInterest,
            'accrued_interest' => $effectiveTotalInterest,
            'uncredited_accrued_interest' => $uncreditedAccruedInterest,
            'credited_interest' => (string) ($investment->total_interest_earned ?? '0'),

            'withdrawable_interest' => $investment->getTotalWithdrawableInterest(),
            'withdrawable_at_maturity' => $investment->getTotalWithdrawableAtMaturity(),
            'daily_display' => $this->calculateDailyDisplay($investment),
            'months_completed' => $investment->getMonthsCompleted(),
            'days_remaining' => $investment->getDaysRemaining(),
            'is_matured' => $isMatured,

            'estimated_power_bonus' => $investment->calculatePowerBonus(),
            'pending_power_bonus' => $investment->calculatePendingPowerBonus(),
            'power_bonus_amount' => (string) ($investment->power_bonus_amount ?? '0.00000000'),
            'power_bonus_credited_at' => $investment->power_bonus_credited_at ? $investment->power_bonus_credited_at->toIso8601String() : null,

            'can_withdraw_interest' => $investment->canWithdrawInterest(),
            'can_withdraw_principal' => $investment->canWithdrawPrincipal(),

            'withdrawal' => $withdrawalInfo,
        ];
    }

 

    /**
     * Calculate withdrawal breakdown and losses (Option B: early withdrawal penalties / full maturity).
     */
    public function calculateWithdrawalBreakdown(UsdtEasyearnInvestment $investment): array
    {
        $settings = UsdtEasyearnSetting::getSettings();
        $isMatured = $investment->isMatured();
        $earlyWithdrawalEnabled = (bool) $settings->early_withdrawal_enabled;
        $penaltyPercent = (string) ($settings->early_withdrawal_penalty_percent ?? '5.00');

        $currentAmount = (string) $investment->amount;
        $totalEarned = $investment->getEffectiveTotalInterestEarned();

        if ($investment->status !== 'active') {
            return [
                'can_withdraw' => false,
                'available_amount_now' => '0.00000000',
                'principal_available_date' => $investment->end_date ? $investment->end_date->format('Y-m-d') : null,
                'is_early_withdrawal' => false,
                'forfeited_interest' => '0.00000000',
                'forfeited_power_bonus' => false,
                'penalty_percent' => '0.00',
                'penalty_fee_amount' => '0.00000000',
                'net_payout_amount' => '0.00000000',
                'reason' => "Plan is {$investment->status}",
            ];
        }

        if ($isMatured) {
            $withdrawableAmount = $investment->getTotalWithdrawableAtMaturity();
            return [
                'can_withdraw' => true,
                'available_amount_now' => $withdrawableAmount,
                'principal_available_date' => Carbon::now()->format('Y-m-d'),
                'is_early_withdrawal' => false,
                'forfeited_interest' => '0.00000000',
                'forfeited_power_bonus' => false,
                'penalty_percent' => '0.00',
                'penalty_fee_amount' => '0.00000000',
                'net_payout_amount' => $withdrawableAmount,
                'reason' => null,
            ];
        }

        // Early withdrawal before maturity
        if (!$earlyWithdrawalEnabled) {
            return [
                'can_withdraw' => false,
                'available_amount_now' => '0.00000000',
                'principal_available_date' => $investment->end_date ? $investment->end_date->format('Y-m-d') : null,
                'is_early_withdrawal' => true,
                'forfeited_interest' => $totalEarned,
                'forfeited_power_bonus' => (bool) $investment->auto_compound,
                'penalty_percent' => $penaltyPercent,
                'penalty_fee_amount' => '0.00000000',
                'net_payout_amount' => '0.00000000',
                'reason' => 'Early withdrawal is currently locked by admin settings.',
            ];
        }

        // Option B: Early withdrawal loses interest, loses 2X PowerBonus, and incurs penalty fee
        $penaltyRate = bcdiv($penaltyPercent, '100', 8);
        $penaltyFee = bcmul($currentAmount, $penaltyRate, 8);
        $netPayout = bcsub($currentAmount, $penaltyFee, 8);
        if (bccomp($netPayout, '0', 8) < 0) {
            $netPayout = '0.00000000';
        }

        return [
            'can_withdraw' => true,
            'available_amount_now' => $currentAmount,
            'principal_available_date' => $investment->end_date ? $investment->end_date->format('Y-m-d') : null,
            'is_early_withdrawal' => true,
            'forfeited_interest' => $totalEarned,
            'forfeited_power_bonus' => (bool) $investment->auto_compound,
            'penalty_percent' => $penaltyPercent,
            'penalty_fee_amount' => $penaltyFee,
            'net_payout_amount' => $netPayout,
            'reason' => null,
        ];
    }

    /**
     * Withdraw from an EasyEarn plan (supporting partial & full withdrawal, following Option B).
     */
    public function withdraw(
        UsdtEasyearnInvestment $investment,
        ?float $requestedAmount = null
    ): array {
        if ($investment->status !== 'active') {
            throw new Exception('Investment is not active.');
        }

        $user = $investment->user;
        if (!$user || !$user->quidax_id) {
            throw new Exception('Quidax account not found. Please complete your account setup.');
        }

        $breakdown = $this->calculateWithdrawalBreakdown($investment);
        if (!$breakdown['can_withdraw']) {
            throw new Exception($breakdown['reason'] ?? 'PLAN_LOCKED');
        }

        $currentPrincipal = (string) $investment->amount;
        $isFullWithdrawal = false;

        if ($requestedAmount === null || bccomp((string) $requestedAmount, $currentPrincipal, 8) >= 0) {
            $isFullWithdrawal = true;
            $amountToWithdraw = $currentPrincipal;
        } else {
            if ($requestedAmount <= 0) {
                throw new Exception('Withdrawal amount must be greater than zero.');
            }
            if (bccomp((string) $requestedAmount, $currentPrincipal, 8) > 0) {
                throw new Exception('AMOUNT_EXCEEDS_AVAILABLE');
            }
            $amountToWithdraw = (string) $requestedAmount;
        }

        $isMatured = $investment->isMatured();
        $penaltyPercent = (string) ($breakdown['penalty_percent'] ?? '0.00');
        $penaltyFee = '0.00000000';
        $forfeitedInterest = '0.00000000';

        if (!$isMatured) {
            // Early withdrawal penalty
            $penaltyRate = bcdiv($penaltyPercent, '100', 8);
            $penaltyFee = bcmul($amountToWithdraw, $penaltyRate, 8);
            if ($isFullWithdrawal) {
                $forfeitedInterest = $investment->getEffectiveTotalInterestEarned();
            }
        }

        $netPayout = bcsub($amountToWithdraw, $penaltyFee, 8);
        if (bccomp($netPayout, '0', 8) <= 0) {
            throw new Exception('Net withdrawal amount after penalty must be greater than zero.');
        }

        return DB::transaction(function () use (
            $investment,
            $user,
            $amountToWithdraw,
            $penaltyFee,
            $netPayout,
            $forfeitedInterest,
            $isFullWithdrawal,
            $isMatured
        ) {
            $easyearnWallet = $this->getOrCreateEasyearnWallet($user);
            $quidaxService = new QuidaxService();
            $uniqueRef = 'usdteasyearn:withdraw:' . $investment->id . ':' . Str::uuid();

            // 1. Send net payout USDT to user's Quidax account
            try {
                $fundResponse = $quidaxService->fundSubAccount(
                    $user->quidax_id,
                    $netPayout,
                    'usdt',
                    $uniqueRef,
                    "EasyEarn withdrawal - Plan #{$investment->id}"
                );

                if (
                    !is_array($fundResponse)
                    || ($fundResponse['status'] ?? null) !== 'success'
                    || !is_array($fundResponse['data'] ?? null)
                    || empty($fundResponse['data']['id'])
                ) {
                    Log::channel('easyearn_usdt')->error('USDT EasyEarn Withdrawal Payout Failed', [
                        'user_id' => $user->id,
                        'investment_id' => $investment->id,
                        'net_payout' => $netPayout,
                        'response' => $fundResponse,
                    ]);

                    throw new Exception('Failed to transfer USDT to wallet. Please try again.');
                }
            } catch (Exception $e) {
                Log::channel('easyearn_usdt')->error('USDT EasyEarn Payout Error', [
                    'user_id' => $user->id,
                    'investment_id' => $investment->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception('Failed to credit USDT withdrawal: ' . $e->getMessage());
            }

            // 2. Unlock/reduce locked balance in EasyEarn wallet
            $easyearnWallet->unlockAmount($amountToWithdraw);

            // 3. Record in ledger
            try {
                $trx_id = generateTrxString('transactions', 'trx_id', 'EEW-', 14);
                \App\Models\Transaction::create([
                    'user_type' => 'USER',
                    'user_id' => $user->id,
                    'type' => \App\Constants\PaymentGatewayConst::TYPEMONEYOUT,
                    'trx_id' => $trx_id,
                    'request_amount' => $amountToWithdraw,
                    'payable' => $amountToWithdraw,
                    'available_balance' => $easyearnWallet->balance,
                    'remark' => "EasyEarn withdrawal - Plan #{$investment->id}",
                    'details' => [
                        'investment_id' => $investment->id,
                        'penalty_fee' => $penaltyFee,
                        'net_payout' => $netPayout,
                        'forfeited_interest' => $forfeitedInterest,
                        'quidax_ref' => $uniqueRef,
                        'is_early' => !$isMatured,
                    ],
                    'status' => \App\Constants\PaymentGatewayConst::STATUSSUCCESS,
                    'attribute' => \App\Constants\PaymentGatewayConst::RECEIVED,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('EasyEarn withdrawal transaction log fallback', ['error' => $e->getMessage()]);
            }

            // 4. Update investment record
            if ($isFullWithdrawal) {
                $newStatus = $isMatured ? 'completed' : 'terminated';
                $investment->update([
                    'amount' => '0.00000000',
                    'status' => $newStatus,
                    'total_interest_earned' => $isMatured ? $investment->total_interest_earned : '0.00000000',
                ]);
            } else {
                $remainingAmount = bcsub((string) $investment->amount, $amountToWithdraw, 8);
                $investment->update([
                    'amount' => $remainingAmount,
                ]);
            }

            Log::channel('easyearn_usdt')->info('USDT EasyEarn Withdrawal Processed', [
                'investment_id' => $investment->id,
                'user_id' => $user->id,
                'amount_withdrawn' => $amountToWithdraw,
                'penalty_fee' => $penaltyFee,
                'net_payout' => $netPayout,
                'is_full' => $isFullWithdrawal,
                'status' => $investment->status,
            ]);

            return [
                'investment' => $this->getInvestmentSummary($investment->fresh()),
                'withdrawn_amount' => $amountToWithdraw,
                'penalty_fee' => $penaltyFee,
                'net_payout' => $netPayout,
                'forfeited_interest' => $forfeitedInterest,
                'is_full_withdrawal' => $isFullWithdrawal,
            ];
        });
    }

    public function terminate(
    UsdtEasyearnInvestment $investment
): UsdtEasyearnInvestment {
    if ($investment->status !== 'active') {
        throw new Exception(
            'Only active investments can be terminated.'
        );
    }

    if ($investment->isMatured()) {
        throw new Exception(
            'This investment has reached maturity. Please use the maturity withdrawal process.'
        );
    }

    $currentAmount = (float) $investment->amount;

    $interestEarned = (float) (
        $investment->total_interest_earned ?? 0
    );

    $principalAmount = round(
        $currentAmount - $interestEarned,
        8
    );

    if ($principalAmount <= 0) {
        throw new Exception(
            'Invalid investment principal amount.'
        );
    }

    $forfeitedInterest = round(
        max(0, $currentAmount - $principalAmount),
        8
    );

    $investment = DB::transaction(
        function () use (
            $investment,
            $currentAmount,
            $interestEarned,
            $principalAmount,
            $forfeitedInterest
        ): UsdtEasyearnInvestment {
            $user = $investment->user;

            if (!$user || !$user->quidax_id) {
                throw new Exception(
                    'Quidax account not found. Please contact support.'
                );
            }

            $easyearnWallet = $this->getOrCreateEasyearnWallet($user);

            try {
                $quidaxService = new QuidaxService();

                $reference = $this->reference(
    'break',
    $investment->id
);

$fundResponse = $quidaxService->fundSubAccount(
    $user->quidax_id,
    $principalAmount,
    'usdt',
    $reference,
    'USDT EasyEarn Break'
);
                if (
                    !is_array($fundResponse)
                    || ($fundResponse['status'] ?? null) !== 'success'
                    || !is_array($fundResponse['data'] ?? null)
                    || empty($fundResponse['data']['id'])
                ) {
                    Log::channel('easyearn_usdt')->error(
                        'USDT EasyEarn Termination Payout Failed',
                        [
                            'user_id' => $user->id,
                            'investment_id' => $investment->id,
                            'principal_amount' => $principalAmount,
                            'quidax_id' => $user->quidax_id,
                            'response' => $fundResponse,
                        ]
                    );

                    throw new Exception(
                        $fundResponse['message']
                            ?? 'Failed to initiate USDT return to your Quidax account. Contact support'
                    );
                }

                Log::channel('easyearn_usdt')->info(
                    'USDT EasyEarn Termination Payout Completed',
                    [
                        'user_id' => $user->id,
                        'investment_id' => $investment->id,
                        'current_amount' => $currentAmount,
                        'interest_earned' => $interestEarned,
                        'forfeited_interest' => $forfeitedInterest,
                        'principal_returned' => $principalAmount,
                        'quidax_transaction_id' =>
                            $fundResponse['data']['id'] ?? null,
                    ]
                );
            } catch (Exception $e) {
                Log::channel('easyearn_usdt')->error(
                    'USDT EasyEarn Termination Payout Failed',
                    [
                        'user_id' => $user->id,
                        'investment_id' => $investment->id,
                        'current_amount' => $currentAmount,
                        'principal_amount' => $principalAmount,
                        'interest_earned' => $interestEarned,
                        'error' => $e->getMessage(),
                    ]
                );

                throw new Exception(
                    'Failed to return USDT after termination: ' .
                    $e->getMessage()
                );
            }

            /*
             * Remove the entire investment amount
             * from the user's EasyEarn locked balance.
             */
            $easyearnWallet->unlockAmount($currentAmount);

            /*
             * Mark investment as terminated.
             */
            $investment->update([
                'status' => 'terminated',
            ]);

            Log::channel('easyearn_usdt')->info(
                'USDT EasyEarn Investment Terminated',
                [
                    'user_id' => $user->id,
                    'investment_id' => $investment->id,
                    'principal_returned' => $principalAmount,
                    'interest_earned' => $interestEarned,
                    'forfeited_interest' => $forfeitedInterest,
                    'amount_before_termination' => $currentAmount,
                    'status' => 'terminated',
                ]
            );

            return $investment->fresh();
        }
    );

    /*
     * Send notification only after the transaction succeeds.
     */
    $this->sendEasyEarnNotification(
        $investment->user,
        'EASY_EARN_TERMINATED',
        [
            'investment_id'             => $investment->id,
            'principal_returned'        => $principalAmount,
            'interest_earned'           => $interestEarned,
            'forfeited_interest'        => $forfeitedInterest,
            'amount_before_termination' => $currentAmount,
            'asset'                     => 'USDT',
            'status'                    => $investment->status,
        ]
    );

    return $investment;
}


    /**
     * Send EasyEarn notification.
     *
     * Notification failures are logged but never allowed
     * to break or roll back a successful financial operation.
     */
    private function sendEasyEarnNotification(
        User $user,
        string $templateKey,
        array $data = []
    ): void {
        try {
            // Always inject the user name expected by templates
            $params = array_merge([
                'user' => $user->firstname ?? $user->name ?? 'User',
            ], $data);

            $this->sendNotification(
                $user,
                $templateKey,
                $params,
                ['mail', 'push', 'inapp'],
                [
                    'referenceId'   => $templateKey . '_' . ($data['investment_id'] ?? $user->id),
                    'referenceType' => 'usdt_easyearn_investment',
                    'type'          => 'easyearn',
                    'priority'      => 'high',
                    'action'        => [
                        'link' => '#',
                        'icon' => 'fa fa-chart-line text-white',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::channel('easyearn_usdt')->warning(
                'EasyEarn notification failed',
                [
                    'user_id'      => $user->id,
                    'notification' => $templateKey,
                    'error'        => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Get user's available spendable USDT balance from Quidax.
     *
     * Validates and returns the user's available USDT balance after
     * deducting any active outgoing reservations (pending withdrawals or
     * active off-ramps). Ensures the returned balance is never negative.
     */
    public function getAvailableUsdtBalance(User $user): string
    {
        if (!$user->quidax_id) {
            throw new Exception(
                'Quidax account not found. Please complete your account setup.'
            );
        }

        $quidaxService = app(QuidaxService::class);
        $walletResponse = $quidaxService->fetchUserWallet(
            $user->quidax_id,
            'usdt'
        );

        if (!isset($walletResponse['data']) || !is_array($walletResponse['data'])) {
            throw new Exception(
                'Unable to fetch USDT wallet from Quidax.'
            );
        }

        $walletData = $walletResponse['data'];
        if (isset($walletData[0]) && is_array($walletData[0])) {
            $walletData = $walletData[0];
        }

        /** @var QuidaxSpendableBalanceService $spendableService */
        $spendableService = app(QuidaxSpendableBalanceService::class);
        $augmentedWallet = $spendableService->augmentWalletPayload(
            $user,
            $walletData,
            'usdt'
        );

        $availableBalance = (string) ($augmentedWallet['available_balance'] ?? $augmentedWallet['balance'] ?? '0');

        if (!is_numeric($availableBalance) || bccomp($availableBalance, '0', 8) < 0) {
            $availableBalance = '0.00000000';
        } else {
            $availableBalance = bcadd($availableBalance, '0', 8);
        }

        return $availableBalance;
    }
}