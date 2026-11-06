<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use App\Services\ReferralWalletService;
use App\Providers\Admin\BasicSettingsProvider;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Exception;

class ReferralController extends Controller
{
    protected $referralService;
    protected $referralWalletService;

    public function __construct(ReferralService $referralService, ReferralWalletService $referralWalletService)
    {
        $this->referralService = $referralService;
        $this->referralWalletService = $referralWalletService;
    }

    /**
     * Get my referral info and statistics
     * GET /api/user/referral/info
     */
    public function getInfo()
    {
        try {
            $user = auth()->user();
            
            // Generate referral code if user doesn't have one
            if (!$user->referral_code) {
                $this->referralService->generateReferralCode($user);
                $user->refresh();
            }

            $stats = $this->referralService->getStatistics($user);
            $stats['wallet'] = $this->referralWalletService->getBalanceSummary($user);
            $stats['commission_amount'] = (float) (BasicSettingsProvider::get()->referral_bonus ?? 500);

            $commissionRate = 20.0;
            try {
                if (class_exists(\App\Models\ReferralCommissionSetting::class)) {
                    $commissionSetting = \App\Models\ReferralCommissionSetting::current();
                    if ($commissionSetting && isset($commissionSetting->commission_rate_percent)) {
                        $commissionRate = (float) $commissionSetting->commission_rate_percent;
                    }
                }
            } catch (\Throwable $e) {
                // Fallback default
            }

            $stats['commission_rate'] = $commissionRate;
            $stats['commission_rate_percent'] = $commissionRate;
            
            return Response::successResponse('Referral information retrieved successfully', $stats);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get list of my referrals
     * GET /api/user/referral/list
     */
    public function getList(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);
            
            $referrals = $this->referralService->getReferralList($user, $perPage);
            
            return Response::successResponse('Referral list retrieved successfully', $referrals);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get my referral earnings
     * GET /api/user/referral/earnings
     */
    public function getEarnings(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 20);
            
            $earnings = $this->referralService->getEarnings($user, $perPage);
            
            return Response::successResponse('Referral earnings retrieved successfully', $earnings);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Validate a referral code (public endpoint)
     * POST /api/referral/validate
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string|max:20',
        ]);

        try {
            $referrer = $this->referralService->validateReferralCode($request->referral_code);
            
            if (!$referrer) {
                return Response::errorResponse('Invalid referral code');
            }

            return Response::successResponse('Referral code is valid', [
                'valid' => true,
                'referrer_username' => substr($referrer->username, 0, 3) . '***',
            ]);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get referral wallet balance summary
     * GET /api/user/referral/balance
     */
    public function getBalance()
    {
        try {
            $summary = $this->referralWalletService->getBalanceSummary(auth()->user());

            return Response::successResponse('Referral wallet balance retrieved successfully', $summary);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Convert referral earnings to Naira
     * POST /api/user/referral/convert
     */
    public function convertEarnings(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        try {
            $conversion = $this->referralWalletService->processConversion(auth()->user(), (string) $request->amount);

            return Response::successResponse('Referral earnings converted successfully', $conversion);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Create a withdrawal request from referral wallet
     * POST /api/user/referral/withdraw
     *
     * Bank:   { method: "bank", amount, bank_name, account_number, account_name }
     * Crypto: { method: "crypto", amount, crypto_currency, network, wallet_address }
     */
    public function requestWithdrawal(Request $request)
    {
        $method = strtolower((string) $request->input('method', 'bank'));
        $request->merge(['method' => $method]);

        $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
            'method' => 'required|string|in:bank,crypto',
            'bank_name' => 'required_if:method,bank|nullable|string|max:150',
            'account_number' => 'required_if:method,bank|nullable|string|min:10|max:30',
            'account_name' => 'required_if:method,bank|nullable|string|max:150',
            'crypto_currency' => 'required_if:method,crypto|nullable|string|in:USDT,usdt',
            'network' => 'required_if:method,crypto|nullable|string|in:TRC20,ERC20,BEP20,trc20,erc20,bep20',
            'wallet_address' => 'required_if:method,crypto|nullable|string|min:10|max:255',
        ]);

        try {
            $withdrawal = $this->referralWalletService->requestWithdrawal(auth()->user(), $request->only([
                'amount',
                'method',
                'bank_name',
                'account_number',
                'account_name',
                'crypto_currency',
                'network',
                'wallet_address',
            ]));

            return Response::successResponse('Withdrawal request submitted successfully', [
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'reference' => $withdrawal->reference,
                    'amount' => (float) $withdrawal->amount,
                    'currency' => $withdrawal->currency_code,
                    'status' => $withdrawal->status,
                    'method' => $withdrawal->method ?? 'bank',
                    'bank_name' => $withdrawal->bank_name,
                    'account_number' => $withdrawal->account_number,
                    'account_name' => $withdrawal->account_name,
                    'crypto_currency' => $withdrawal->crypto_currency,
                    'network' => $withdrawal->network,
                    'wallet_address' => $withdrawal->wallet_address,
                    'created_at' => optional($withdrawal->created_at)->format('Y-m-d H:i:s'),
                ],
                'wallet' => $this->referralWalletService->getBalanceSummary(auth()->user()),
            ]);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Get referral wallet transaction history
     * GET /api/user/referral/history
     */
    public function getTransactionHistory(Request $request)
    {
        try {
            $perPage = max(1, min((int) $request->input('per_page', 20), 100));
            $history = $this->referralWalletService->getTransactionHistory(auth()->user(), $perPage);

            return Response::successResponse('Referral wallet transaction history retrieved successfully', $history);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }
}
