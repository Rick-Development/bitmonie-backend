<?php

namespace App\Services;

use App\Models\UserWallet;
use App\Models\P2POrder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // create order and return model
    public static function createOrder(array $data)
    {
        // $data['asset'] = strtoupper($data['asset']);
        $data['quote_currency'] = strtoupper($data['quote_currency']);
        $data['amount'] = (string) $data['amount'];
        $data['price'] = (string) $data['price'];
        $data['total'] = bcmul($data['amount'], $data['price'], 2);
        $data['status'] = 'open';
        $data['expires_at'] = $data['expires_at'] ?? now()->addHours(24);

        // update the quote_currency_code
        $user_wallet = UserWallet::where('user_id', auth()->user()->id)->first();
        $user_wallet->quote_currency_code = $data['quote_currency'];
        $user_wallet->save();
        return P2POrder::create($data);
    }

    // determine payer wallet for funding depending on order type
    public static function resolvePayerWalletForFunding(P2POrder $order, int $payerUserId): UserWallet
    {
        // For sell order: maker is seller, buyer (payer) must pay in quote_currency (total)
        // For buy order: maker is buyer, seller (payer) must deposit asset (amount)
        if ($order->type === 'sell') {
            return UserWallet::where('user_id', $payerUserId)->where('quote_currency_code', $order->quote_currency)->firstOrFail();
        }
        return UserWallet::where('user_id', $payerUserId)->where('quote_currency_code', $order->asset)->firstOrFail();
    }
}