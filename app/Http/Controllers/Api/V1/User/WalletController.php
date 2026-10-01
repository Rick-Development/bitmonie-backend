<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\OrderTransaction;
use Illuminate\Http\Request;
use App\Services\SafeHavenService;
use App\Services\TransactionHistoryService;

class WalletController extends Controller
{
    public function __construct(
        protected SafeHavenService $safeHavenService,
        protected TransactionHistoryService $transactionHistoryService
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

        $filters = array_merge($request->query(), ['currency' => $code]);
        $rows = $this->transactionHistoryService->forUser($user, $filters);
        $transactions = $this->transactionHistoryService->paginateRows($rows, $filters, (int) $request->query('per_page', 20));

        return Response::success('Transaction history fetched', [
            'currency' => $code,
            'transactions' => $transactions,
            'summary' => $this->transactionHistoryService->summary($rows),
        ]);
    }

    /**
     * Get complete transaction history across all wallets.
     */
    public function allHistory(Request $request)
    {
        $user = auth()->user();

        $rows = $this->transactionHistoryService->forUser($user, $request->query());
        $transactions = $this->transactionHistoryService->paginateRows($rows, $request->query(), (int) $request->query('per_page', 20));

        return Response::success('Transaction history fetched', [
            'transactions' => $transactions,
            'summary' => $this->transactionHistoryService->summary($rows),
        ]);
    }

    protected function presentTransaction(OrderTransaction $transaction): array
    {
        $metadata = $transaction->metadata ?? [];

        return [
            'id' => $transaction->id,
            'wallet_id' => $transaction->user_wallet_id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'reference' => $transaction->reference,
            'status' => data_get($metadata, 'status', 'successful'),
            'category' => data_get($metadata, 'type') ?? data_get($metadata, 'source'),
            'bill_type' => data_get($metadata, 'bill_type'),
            'token' => data_get($metadata, 'token') ?? data_get($metadata, 'token_code') ?? data_get($metadata, 'bill_purchase.token'),
            'token_code' => data_get($metadata, 'token_code') ?? data_get($metadata, 'bill_purchase.token'),
            'provider_reference' => data_get($metadata, 'provider_reference') ?? data_get($metadata, 'bill_purchase.provider_reference'),
            'details' => data_get($metadata, 'bill_purchase') ?? data_get($metadata, 'details') ?? $metadata,
            'metadata' => $metadata,
            'created_at' => optional($transaction->created_at)->toDateTimeString(),
            'updated_at' => optional($transaction->updated_at)->toDateTimeString(),
        ];
    }
}
