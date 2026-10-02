<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\CommissionWallet;
use App\Models\ReferralCommission;
use App\Services\SharedReferralCommissionService;
use App\Support\UserScopedCache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReferralCommissionController extends Controller
{
    public function index(Request $request, SharedReferralCommissionService $service)
    {
        $user = $request->user();
        $payload = UserScopedCache::remember(['shared-referral-commission', "user:{$user->id}"], 'summary', 300, function () use ($user, $service) {
            $wallets = CommissionWallet::where('user_id', $user->id)->get();

            return [
                'wallets' => $wallets,
                'totals' => [
                    'available' => $wallets->sum('available_balance'),
                    'pending' => $wallets->sum('pending_balance'),
                    'withdrawn' => $wallets->sum('withdrawn_balance'),
                ],
                'commission_rate_percent' => \App\Models\ReferralCommissionSetting::current()->commission_rate_percent,
                'referrals_count' => ReferralCommission::where('referrer_user_id', $user->id)->distinct('referred_user_id')->count('referred_user_id'),
            ];
        });

        return Response::successResponse('Referral commission summary fetched successfully', $payload);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $cacheKey = 'history:' . md5(json_encode($request->query()));
        $payload = UserScopedCache::remember(['shared-referral-commission', "user:{$user->id}"], $cacheKey, 300, function () use ($user, $request) {
            return ReferralCommission::with('referred:id,username,email')
                ->where('referrer_user_id', $user->id)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('transaction_type'), fn ($query, $type) => $query->where('transaction_type', $type))
                ->orderByDesc('id')
                ->paginate(30);
        });

        return Response::successResponse('Referral commission history fetched successfully', $payload);
    }

    public function referredUsers(Request $request)
    {
        $user = $request->user();
        $payload = UserScopedCache::remember(['shared-referral-commission', "user:{$user->id}"], 'referred-users:' . md5(json_encode($request->query())), 300, function () use ($user) {
            return ReferralCommission::query()
                ->selectRaw('referred_user_id, currency_code, COUNT(*) as transactions_count, SUM(platform_fee_amount) as total_platform_fees, SUM(commission_amount) as total_commission')
                ->where('referrer_user_id', $user->id)
                ->with('referred:id,username,email,created_at')
                ->groupBy('referred_user_id', 'currency_code')
                ->orderByDesc('total_commission')
                ->paginate(30);
        });

        return Response::successResponse('Referral commission referred users fetched successfully', $payload);
    }

    public function withdraw(Request $request, SharedReferralCommissionService $service)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'currency_code' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $transaction = $service->withdraw($request->user(), (string) $validated['amount'], $validated['currency_code'] ?? 'NGN');
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return Response::successResponse('Referral commission withdrawn successfully', $transaction);
    }
}
