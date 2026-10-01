<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\UsdtEasyearnInvestment;
use App\Models\UsdtEasyearnSetting;
use App\Services\UsdtEasyearnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class UsdtEasyearnController extends Controller
{
    protected $easyearnService;

    public function __construct(UsdtEasyearnService $easyearnService)
    {
        $this->easyearnService = $easyearnService;
    }

    /**
     * Get product information
     * GET /api/v1/usdt-easyearn/info
     */
    public function info()
    {
        try {
            $settings = UsdtEasyearnSetting::getSettings();

            return response()->json([
                'success' => true,
                'message' => 'Product information retrieved successfully',
                'data' => [
                    'current_monthly_rate' => $settings->current_monthly_rate,
                    'min_investment' => $settings->min_investment,
                    'max_investment' => $settings->max_investment,
                    'is_active' => $settings->is_active,
                    'payout_day' => $settings->payout_day,
                    'duration_months' => 12,
                    'features' => [
                        'auto_compound' => true,
                        'monthly_withdrawal' => true,
                        'fixed_duration' => '12 months',
                        'standard_monthly_rate' => $settings->current_monthly_rate . '%',
                        'compounding_annual_rate' => '200%',
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
            $status = $request->query('status'); // active, completed, cancelled

            $query = UsdtEasyearnInvestment::byUser($user->id)
                ->with('interestCredits')
                ->latest();

            if ($status) {
                $query->where('status', $status);
            }

            $investments = $query->paginate(20);

            $data = $investments->map(function ($investment) {
                return $this->easyearnService->getInvestmentSummary($investment);
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
            $amount = (float)$request->amount;
            $autoCompound = $request->auto_compound ?? false;

            $investment = $this->easyearnService->createInvestment($user, $amount, $autoCompound);

            return response()->json([
                'success' => true,
                'message' => 'Savings created successfully',
                'data' => $this->easyearnService->getInvestmentSummary($investment)
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
                ->with(['interestCredits' => function($query) {
                    $query->latest();
                }])
                ->findOrFail($id);

            $summary = $this->easyearnService->getInvestmentSummary($investment);
            
            // Add credit history
            $summary['credit_history'] = $investment->interestCredits->map(function($credit) {
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
            $investment = UsdtEasyearnInvestment::byUser($user->id)->findOrFail($id);

            $amount = $this->easyearnService->withdrawInterest($investment);

            return response()->json([
                'success' => true,
                'message' => 'Interest withdrawn successfully',
                'data' => [
                    'withdrawn_amount' => $amount,
                    'investment' => $this->easyearnService->getInvestmentSummary($investment->fresh())
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
     * Withdraw principal at maturity
     * POST /api/v1/usdt-easyearn/withdraw-principal/{id}
     */
    public function withdrawPrincipal(Request $request, $id)
    {
        try {
            $user = $request->user();
            $investment = UsdtEasyearnInvestment::byUser($user->id)->findOrFail($id);

            $amount = $this->easyearnService->withdrawPrincipal($investment);

            return response()->json([
                'success' => true,
                'message' => 'Principal withdrawn successfully. Savings completed.',
                'data' => [
                    'withdrawn_amount' => $amount,
                    'investment' => $this->easyearnService->getInvestmentSummary($investment->fresh())
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
            $investment = UsdtEasyearnInvestment::byUser($user->id)->findOrFail($id);
            $amount = (float)$request->amount;

            $investment = $this->easyearnService->topUp($user, $investment, $amount);

            return response()->json([
                'success' => true,
                'message' => 'Savings topped up successfully',
                'data' => $this->easyearnService->getInvestmentSummary($investment)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
