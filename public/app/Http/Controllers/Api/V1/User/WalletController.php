<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\OrderTransaction;
use Illuminate\Http\Request;
use App\Services\SafeHavenService;

class WalletController extends Controller
{
    public function __construct(
        protected SafeHavenService $safeHavenService
    ) {
    }

    /**
     * Get all user wallets with API mock for USD if needed.
     */
    public function index()
    {
        $user = auth()->user();

        $this->safeHavenService->syncUserWalletBalance($user, 'NGN');
        
        // Fetch all active wallets for the user
        $wallets = UserWallet::with('currency')
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->get();

        $data = $wallets->map(function ($wallet) {
            $currency = $wallet->currency;
            
            // Mock logic: If USD integration is pending, we can optionally override balance or status here.
            // For now, we return the DB balance.
            
            return [
                'id' => $wallet->id,
                'currency' => $currency->code,
                'currency_name' => $currency->name,
                'symbol' => $currency->symbol,
                'balance' => (float) $wallet->balance,
                'flag' => $currency->flag,
                'rate' => (float) $currency->rate,
                'is_default' => $currency->default,
            ];
        });

        return Response::success('Wallets fetched successfully', ['wallets' => $data]);
    }

    /**
     * Get transaction history for a specific wallet/currency.
     */
    public function history(Request $request, $code)
    {
        $user = auth()->user();
        $code = strtoupper($code);

        if ($code === 'NGN') {
            $this->safeHavenService->syncUserWalletBalance($user, 'NGN');
        }

        $wallet = UserWallet::where('user_id', $user->id)
            ->whereHas('currency', function ($q) use ($code) {
                $q->where('code', $code);
            })->first();

        if (!$wallet) {
            return Response::error('Wallet not found for ' . $code, [], 404);
        }

        // Fetch OrderTransaction (Wallet Ledger) instead of Transaction (Payment Gateway Logs)
        $transactions = OrderTransaction::where('user_wallet_id', $wallet->id)
            ->latest()
            ->paginate(20);

        return Response::success('Transaction history fetched', [
            'currency' => $code,
            'transactions' => $transactions
        ]);
    }
}
