<?php

namespace App\Http\Controllers\Api\Savings;

use App\Http\Controllers\Api\Savings\Concerns\RespondsToSavings;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\EduSave;
use App\Models\SafeLock;
use App\Models\SavingsAccount;
use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnSetting;
use App\Services\Savings\Money;
use App\Services\Savings\SavingsManager;
use App\Services\UsdtEasyearnService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Controller for retrieving all savings products with balances and allowed actions
 * for Quick Save, Transfer, and Withdraw.
 *
 * @tags Savings - Products
 */
class SavingsProductsController extends Controller
{
    use RespondsToSavings;

    public function __construct(
        private readonly SavingsManager $savings,
        private readonly UsdtEasyearnService $usdtEarnService
    ) {
    }

    /**
     * Get all savings products for the authenticated user with balances and allowed actions.
     *
     * GET /api/v1/savings/products
     * GET /api/v1/savings/summary
     */
    public function index(Request $request)
    {
        return $this->guarded(function () use ($request) {
            $user = $request->user();
            $products = [];

            $minTx = (string) config('savings.limits.min_transaction', '100.00');
            $maxTx = (string) config('savings.limits.max_transaction', '5000000.00');

            // 1. Flexi Save
            $flexi = $this->savings->flexi($user);
            $flexiBalance = Money::norm($flexi->balance);
            $flexiAvailable = Money::norm($flexi->availableBalance());
            $flexiInterest = Money::norm($flexi->interest_earned);
            $hasFlexiFunds = Money::cmp($flexiAvailable, '0.00') > 0;

            $products[] = [
                'id' => $flexi->id,
                'product_key' => 'FLEXI',
                'name' => 'Flexi Save',
                'type' => 'flexible',
                'currency' => config('savings.currency', 'NGN'),
                'balance' => $flexiBalance,
                'available_balance' => $flexiAvailable,
                'interest_earned' => $flexiInterest,
                'status' => $flexi->status,
                'annual_rate' => config('savings.flexi.interest_apr_percent', '60') . '%',
                'actions' => [
                    'save' => [
                        'allowed' => $flexi->status === SavingsAccount::ACTIVE,
                        'min_amount' => $minTx,
                        'max_amount' => $maxTx,
                        'reason' => $flexi->status === SavingsAccount::ACTIVE ? null : "Account is {$flexi->status}",
                        'available_date' => null,
                    ],
                    'withdraw' => [
                        'allowed' => $flexi->status === SavingsAccount::ACTIVE && $hasFlexiFunds,
                        'min_amount' => $minTx,
                        'max_amount' => $flexiAvailable,
                        'reason' => !$hasFlexiFunds ? 'Insufficient available balance' : ($flexi->status === SavingsAccount::ACTIVE ? null : "Account is {$flexi->status}"),
                        'available_date' => null,
                    ],
                    'transfer' => [
                        'allowed' => $flexi->status === SavingsAccount::ACTIVE && $hasFlexiFunds,
                        'min_amount' => $minTx,
                        'max_amount' => $flexiAvailable,
                        'reason' => !$hasFlexiFunds ? 'Insufficient available balance' : ($flexi->status === SavingsAccount::ACTIVE ? null : "Account is {$flexi->status}"),
                        'available_date' => null,
                    ],
                ],
            ];

            // 2. AutoSave Plans (Unified)
            $autoAccounts = SavingsAccount::where('user_id', $user->id)
                ->where('product', SavingsAccount::AUTO)
                ->with('plan')
                ->latest()
                ->get();

            if ($autoAccounts->isEmpty()) {
                $products[] = [
                    'id' => null,
                    'product_key' => 'AUTO',
                    'name' => 'AutoSave',
                    'type' => 'recurring',
                    'currency' => config('savings.currency', 'NGN'),
                    'balance' => '0.00',
                    'available_balance' => '0.00',
                    'interest_earned' => '0.00',
                    'status' => 'INACTIVE',
                    'actions' => [
                        'save' => [
                            'allowed' => true,
                            'min_amount' => $minTx,
                            'max_amount' => $maxTx,
                            'reason' => null,
                            'available_date' => null,
                        ],
                        'withdraw' => [
                            'allowed' => false,
                            'min_amount' => $minTx,
                            'max_amount' => '0.00',
                            'reason' => 'No active AutoSave plan or balance',
                            'available_date' => null,
                        ],
                        'transfer' => [
                            'allowed' => false,
                            'min_amount' => $minTx,
                            'max_amount' => '0.00',
                            'reason' => 'No active AutoSave plan or balance',
                            'available_date' => null,
                        ],
                    ],
                ];
            } else {
                foreach ($autoAccounts as $auto) {
                    $autoBal = Money::norm($auto->balance);
                    $autoAvail = Money::norm($auto->availableBalance());
                    $hasFunds = Money::cmp($autoAvail, '0.00') > 0;
                    $isActive = $auto->status === SavingsAccount::ACTIVE;

                    $products[] = [
                        'id' => $auto->id,
                        'product_key' => 'AUTO',
                        'name' => $auto->title ?: 'AutoSave',
                        'type' => 'recurring',
                        'currency' => $auto->currency ?? config('savings.currency', 'NGN'),
                        'balance' => $autoBal,
                        'available_balance' => $autoAvail,
                        'interest_earned' => Money::norm($auto->interest_earned),
                        'status' => $auto->status,
                        'actions' => [
                            'save' => [
                                'allowed' => $isActive,
                                'min_amount' => $minTx,
                                'max_amount' => $maxTx,
                                'reason' => $isActive ? null : "Plan is {$auto->status}",
                                'available_date' => null,
                            ],
                            'withdraw' => [
                                'allowed' => in_array($auto->status, [SavingsAccount::ACTIVE, SavingsAccount::CANCELLED], true) && $hasFunds,
                                'min_amount' => $minTx,
                                'max_amount' => $autoAvail,
                                'reason' => !$hasFunds ? 'Insufficient available balance' : null,
                                'available_date' => null,
                            ],
                            'transfer' => [
                                'allowed' => in_array($auto->status, [SavingsAccount::ACTIVE, SavingsAccount::CANCELLED], true) && $hasFunds,
                                'min_amount' => $minTx,
                                'max_amount' => $autoAvail,
                                'reason' => !$hasFunds ? 'Insufficient available balance' : null,
                                'available_date' => null,
                            ],
                        ],
                    ];
                }
            }

            // 3. Target Savings Goals (Unified)
            $targetAccounts = SavingsAccount::where('user_id', $user->id)
                ->where('product', SavingsAccount::TARGET)
                ->latest()
                ->get();

            foreach ($targetAccounts as $target) {
                $targetBal = Money::norm($target->balance);
                $isTargetActive = $target->status === SavingsAccount::ACTIVE;
                $isTargetCompleteOrExpired = in_array($target->status, [SavingsAccount::COMPLETED, SavingsAccount::EXPIRED], true);
                $deadline = $target->deadline ? Carbon::parse($target->deadline->toDateString()) : null;
                $deadlineFormatted = $deadline ? $deadline->format('d M Y') : null;

                $remainingToGoal = Money::sub(Money::norm($target->target_amount), $targetBal);
                $maxSave = Money::cmp($remainingToGoal, $maxTx) < 0 ? $remainingToGoal : $maxTx;

                $products[] = [
                    'id' => $target->id,
                    'product_key' => 'TARGET',
                    'name' => $target->title ?: 'Target Savings',
                    'type' => 'target',
                    'currency' => $target->currency ?? config('savings.currency', 'NGN'),
                    'balance' => $targetBal,
                    'available_balance' => $isTargetCompleteOrExpired ? $targetBal : '0.00',
                    'target_amount' => Money::norm($target->target_amount),
                    'deadline' => $target->deadline?->toDateString(),
                    'interest_earned' => Money::norm($target->interest_earned),
                    'status' => $target->status,
                    'actions' => [
                        'save' => [
                            'allowed' => $isTargetActive && Money::cmp($remainingToGoal, '0.00') > 0,
                            'min_amount' => $minTx,
                            'max_amount' => $maxSave,
                            'reason' => !$isTargetActive ? "Target is {$target->status}" : (Money::cmp($remainingToGoal, '0.00') <= 0 ? 'Target goal reached' : null),
                            'available_date' => null,
                        ],
                        'withdraw' => [
                            'allowed' => $isTargetCompleteOrExpired && Money::cmp($targetBal, '0.00') > 0,
                            'min_amount' => $minTx,
                            'max_amount' => $targetBal,
                            'reason' => !$isTargetCompleteOrExpired
                                ? ($deadlineFormatted ? "Locked until {$deadlineFormatted}" : "Funds locked until target completion or closure")
                                : (Money::cmp($targetBal, '0.00') <= 0 ? 'No funds available' : null),
                            'available_date' => $deadline?->toDateString(),
                        ],
                        'transfer' => [
                            'allowed' => $isTargetCompleteOrExpired && Money::cmp($targetBal, '0.00') > 0,
                            'min_amount' => $minTx,
                            'max_amount' => $targetBal,
                            'reason' => !$isTargetCompleteOrExpired
                                ? ($deadlineFormatted ? "Locked until {$deadlineFormatted}" : "Funds locked until target completion")
                                : (Money::cmp($targetBal, '0.00') <= 0 ? 'No funds available' : null),
                            'available_date' => $deadline?->toDateString(),
                        ],
                    ],
                ];
            }

            // 4. SafeLock (Fixed Deposit)
            try {
                $activeSafeLocks = SafeLock::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('is_redeemed', false)
                    ->get();

                foreach ($activeSafeLocks as $lock) {
                    $lockMaturity = $lock->end_date ? Carbon::parse($lock->end_date) : null;
                    $lockMaturityFormatted = $lockMaturity ? $lockMaturity->format('d M Y') : 'maturity';

                    $products[] = [
                        'id' => $lock->id,
                        'product_key' => 'SAFELOCK',
                        'name' => $lock->title ?: 'SafeLock #' . $lock->id,
                        'type' => 'fixed',
                        'currency' => 'NGN',
                        'balance' => number_format((float) $lock->amount, 2, '.', ''),
                        'available_balance' => '0.00',
                        'interest_earned' => number_format((float) ($lock->total_interest_earned ?? 0), 2, '.', ''),
                        'status' => 'ACTIVE',
                        'actions' => [
                            'save' => [
                                'allowed' => false,
                                'reason' => 'SafeLock is a fixed deposit and cannot be topped up',
                                'available_date' => null,
                            ],
                            'withdraw' => [
                                'allowed' => false,
                                'reason' => "Locked until {$lockMaturityFormatted}",
                                'available_date' => $lockMaturity?->toDateString(),
                            ],
                            'transfer' => [
                                'allowed' => false,
                                'reason' => 'Transfers not permitted from active SafeLock',
                                'available_date' => null,
                            ],
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // Table might not exist or empty in testing
            }

            // 5. USDT EasyEarn
            try {
                $usdtSettings = UsdtEasyearnSetting::getSettings();
                $usdtInvestments = UsdtEasyearnInvestment::byUser($user->id)
                    ->where('status', 'active')
                    ->latest()
                    ->get();

                if ($usdtInvestments->isEmpty()) {
                    $products[] = [
                        'id' => null,
                        'product_key' => 'USDT_EASYEARN',
                        'name' => 'USDT EasyEarn',
                        'type' => 'crypto_earn',
                        'currency' => 'USDT',
                        'balance' => '0.00000000',
                        'available_balance' => '0.00000000',
                        'interest_earned' => '0.00000000',
                        'status' => 'INACTIVE',
                        'actions' => [
                            'save' => [
                                'allowed' => (bool) $usdtSettings->is_active,
                                'min_amount' => (string) $usdtSettings->min_investment,
                                'max_amount' => $usdtSettings->max_investment ? (string) $usdtSettings->max_investment : null,
                                'reason' => $usdtSettings->is_active ? null : 'USDT EasyEarn is currently disabled',
                                'available_date' => null,
                            ],
                            'withdraw' => [
                                'allowed' => false,
                                'reason' => 'No active USDT EasyEarn plan',
                                'available_date' => null,
                            ],
                            'transfer' => [
                                'allowed' => false,
                                'reason' => 'Transfers not permitted from crypto earn plans to fiat savings',
                                'available_date' => null,
                            ],
                        ],
                    ];
                } else {
                    foreach ($usdtInvestments as $inv) {
                        $breakdown = $this->usdtEarnService->calculateWithdrawalBreakdown($inv);
                        $summary = $this->usdtEarnService->getInvestmentSummary($inv);
                        $canWithdraw = $breakdown['can_withdraw'];
                        $maturityDateFormatted = $inv->end_date ? $inv->end_date->format('d M Y') : 'maturity';

                        $products[] = [
                            'id' => $inv->id,
                            'product_key' => 'USDT_EASYEARN',
                            'name' => "USDT EasyEarn #{$inv->id}",
                            'type' => 'crypto_earn',
                            'currency' => 'USDT',
                            'balance' => (string) $inv->amount,
                            'available_balance' => (string) $breakdown['available_amount_now'],
                            'interest_earned' => (string) $summary['total_interest_earned'],
                            'status' => $inv->status,
                            'rate_label' => $summary['rate_label'],
                            'actions' => [
                                'save' => [
                                    'allowed' => (bool) $usdtSettings->is_active,
                                    'min_amount' => (string) $usdtSettings->min_investment,
                                    'max_amount' => $usdtSettings->max_investment ? (string) $usdtSettings->max_investment : null,
                                    'reason' => null,
                                    'available_date' => null,
                                ],
                                'withdraw' => [
                                    'allowed' => $canWithdraw,
                                    'min_amount' => '0.00000001',
                                    'max_amount' => (string) $breakdown['available_amount_now'],
                                    'reason' => !$canWithdraw ? "Locked until {$maturityDateFormatted}" : null,
                                    'available_date' => $breakdown['principal_available_date'],
                                    'is_early_withdrawal' => $breakdown['is_early_withdrawal'],
                                    'penalty_percent' => $breakdown['penalty_percent'],
                                    'penalty_fee_amount' => $breakdown['penalty_fee_amount'],
                                    'net_payout_amount' => $breakdown['net_payout_amount'],
                                    'forfeited_interest' => $breakdown['forfeited_interest'],
                                ],
                                'transfer' => [
                                    'allowed' => false,
                                    'reason' => 'Transfers not permitted from crypto earn plans to fiat savings',
                                    'available_date' => null,
                                ],
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if not setup
            }

            return Response::successResponse('Savings products retrieved successfully.', [
                'products' => $products,
            ]);
        });
    }
}
