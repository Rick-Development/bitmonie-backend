<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnSetting;
use App\Services\UsdtEasyearnService;
use App\Models\EasyEarnPlan;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UsdtEasyearnController extends Controller
{
    protected UsdtEasyearnService $usdtearnService;

    public function __construct(UsdtEasyearnService $usdtearnService)
    {
        $this->usdtearnService = $usdtearnService;
    }

    /**
     * Get product information
     * GET /api/v1/usdt-easyearn/info
     */
    public function info(Request $request)
    {
        try {
            $settings = UsdtEasyearnSetting::getSettings();
            $user = auth('api')->user() ?? $request->user();

            $availableBalance = '0.00000000';
            if ($user && $user->quidax_id) {
                try {
                    $availableBalance = $this->usdtearnService->getAvailableUsdtBalance($user);
                } catch (\Throwable $e) {
                    $availableBalance = '0.00000000';
                }
            }

            $baseApy = (string) ($settings->base_apy ?? 15.00);
            $powerBonusMultiplier = (string) ($settings->power_bonus_multiplier ?? 2.00);

            return response()->json([
                'success' => true,
                'message' => 'Product information retrieved successfully',
                'data' => [
                    'current_monthly_rate' => $settings->current_monthly_rate,
                    'base_apy' => $baseApy,
                    'power_bonus_multiplier' => $powerBonusMultiplier,
                    'min_investment' => $settings->min_investment,
                    'max_investment' => $settings->max_investment,
                    'available_balance' => $availableBalance,
                    'is_active' => $settings->is_active,
                    'payout_day' => $settings->payout_day,
                    'duration_months' => 12,
                    'early_withdrawal_enabled' => (bool) $settings->early_withdrawal_enabled,
                    'early_withdrawal_penalty_percent' => (string) ($settings->early_withdrawal_penalty_percent ?? '5.00'),
                    'features' => [
                        'auto_compound' => true,
                        'monthly_withdrawal' => true,
                        'fixed_duration' => '12 months',
                        'standard_monthly_rate' => $settings->current_monthly_rate . '%',
                        'base_vault_apy' => $baseApy . '%',
                        'power_bonus' => "{$powerBonusMultiplier}X at maturity",
                        'compounding_annual_rate' => "{$baseApy}% APY + {$powerBonusMultiplier}X PowerBonus",
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's investments
     * GET /api/v1/usdt-easyearn/my-investments
     */
    public function myInvestments(Request $request)
    {
        try {
            $user = $request->user();
            $status = $request->query('status');

            $query = UsdtEasyearnInvestment::byUser($user->id)
                ->with('interestCredits')
                ->latest();

            if ($status) {
                $query->where('status', $status);
            }

            $investments = $query->paginate(20);

            $data = $investments->map(function ($investment) {
                return $this->usdtearnService->getInvestmentSummary($investment);
            });

            return response()->json([
                'success' => true,
                'message' => 'Savings retrieved successfully',
                'data' => $data,
                'pagination' => [
                    'current_page' => $investments->currentPage(),
                    'last_page' => $investments->lastPage(),
                    'per_page' => $investments->perPage(),
                    'total' => $investments->total(),
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new investment
     * POST /api/v1/usdt-easyearn/invest
     */
    public function invest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'auto_compound' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $amount = (float) $request->amount;
            $autoCompound = $request->auto_compound ?? false;

            $investment = $this->usdtearnService->createInvestment(
                $user,
                $amount,
                $autoCompound
            );

            return response()->json([
                'success' => true,
                'message' => 'Savings created successfully',
                'data' => $this->usdtearnService->getInvestmentSummary($investment)
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get investment details
     * GET /api/v1/usdt-easyearn/investment/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            $investment = UsdtEasyearnInvestment::byUser($user->id)
                ->with([
                    'interestCredits' => function ($query) {
                        $query->latest();
                    }
                ])
                ->findOrFail($id);

            $summary = $this->usdtearnService->getInvestmentSummary($investment);

            $summary['credit_history'] = $investment->interestCredits->map(function ($credit) {
                return [
                    'amount' => $credit->amount,
                    'credit_date' => $credit->credit_date->format('Y-m-d'),
                    'created_at' => $credit->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Savings details retrieved successfully',
                'data' => $summary
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Withdraw accumulated interest
     * POST /api/v1/usdt-easyearn/withdraw-interest/{id}
     */
    public function withdrawInterest(Request $request, $id)
    {
        try {
            $user = $request->user();

            $investment = UsdtEasyearnInvestment::byUser($user->id)
                ->findOrFail($id);

            $amount = $this->usdtearnService->withdrawInterest($investment);

            return response()->json([
                'success' => true,
                'message' => 'Interest withdrawn successfully',
                'data' => [
                    'withdrawn_amount' => $amount,
                    'investment' => $this->usdtearnService
                        ->getInvestmentSummary($investment->fresh())
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get withdrawal details (available amount, penalty losses, maturity date)
     * GET /api/v1/usdt-easyearn/withdrawal-info/{id}
     */
    public function withdrawalInfo(Request $request, $id)
    {
        try {
            $user = $request->user();
            $investment = UsdtEasyearnInvestment::byUser($user->id)->findOrFail($id);
            $breakdown = $this->usdtearnService->calculateWithdrawalBreakdown($investment);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal information retrieved successfully',
                'data' => array_merge([
                    'investment_id' => $investment->id,
                    'principal_amount' => $investment->amount,
                    'status' => $investment->status,
                ], $breakdown)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'Savings plan not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'SERVER_ERROR',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Withdraw from plan (Option B: early withdrawal with penalty or full withdrawal at maturity)
     * POST /api/v1/usdt-easyearn/withdraw/{id}
     */
    public function withdraw(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $investment = UsdtEasyearnInvestment::byUser($user->id)->findOrFail($id);

            $requestedAmount = $request->has('amount') && $request->amount !== null
                ? (float) $request->amount
                : null;

            $result = $this->usdtearnService->withdraw($investment, $requestedAmount);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal completed successfully. USDT has been credited to your wallet.',
                'data' => $result
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'Savings plan not found'
            ], 404);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $code = 'WITHDRAWAL_FAILED';

            if (str_contains($msg, 'PLAN_LOCKED') || str_contains($msg, 'Early withdrawal is currently locked')) {
                $code = 'PLAN_LOCKED';
                $msg = 'Early withdrawal is locked for this plan until maturity.';
            } elseif (str_contains($msg, 'AMOUNT_EXCEEDS_AVAILABLE') || str_contains($msg, 'exceeds available')) {
                $code = 'AMOUNT_EXCEEDS_AVAILABLE';
                $msg = 'The withdrawal amount exceeds your available balance.';
            }

            return response()->json([
                'success' => false,
                'error_code' => $code,
                'message' => $msg
            ], 400);
        }
    }

    /**
     * Withdraw principal at maturity
     * POST /api/v1/usdt-easyearn/withdraw-principal/{id}
     */
    public function withdrawPrincipal(Request $request, $id)
    {
        try {
            $user = $request->user();

            $investment = UsdtEasyearnInvestment::byUser($user->id)
                ->findOrFail($id);

            $amount = $this->usdtearnService->withdrawPrincipal($investment);

            return response()->json([
                'success' => true,
                'message' => 'Principal withdrawn successfully. Savings completed.',
                'data' => [
                    'withdrawn_amount' => $amount,
                    'investment' => $this->usdtearnService
                        ->getInvestmentSummary($investment->fresh())
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'PLAN_LOCKED',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Top up an existing investment
     * POST /api/v1/usdt-easyearn/top-up/{id}
     */
    public function topUp(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            $investment = UsdtEasyearnInvestment::byUser($user->id)
                ->findOrFail($id);

            $amount = (float) $request->amount;

            $investment = $this->usdtearnService->topUp(
                $user,
                $investment,
                $amount
            );

            return response()->json([
                'success' => true,
                'message' => 'Savings topped up successfully',
                'data' => $this->usdtearnService->getInvestmentSummary($investment)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Terminate an investment
     */
    public function terminate(Request $request, int $id)
    {
        $user = $request->user();

        try {
            $investment = UsdtEasyearnInvestment::byUser($user->id)
                ->findOrFail($id);

            $investment = $this->usdtearnService->terminate($investment);

            return Response::successResponse(
                'EasyEarn investment terminated successfully',
                $this->usdtearnService->getInvestmentSummary($investment)
            );

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'EasyEarn investment not found.'
            ], 404);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
