<?php

namespace App\Traits;

use App\Models\CountryCurrency;
use App\Models\UserWallet;

trait ManageWallet
{
    public function updateWallet($user_id, $wallet_id, $amount, $action = 0)
    {
        $wallet = UserWallet::where('user_id', $user_id)
            ->where('id', $wallet_id)
            ->first();
        $balance = 0;
        if ($action == 1) {
            $balance = $wallet->balance + $amount;
            $wallet->balance = $balance;
        } elseif ($action == 0) {
            $balance = $wallet->balance - $amount;
            $wallet->balance = $balance;
        }

        $wallet->save();
        return $balance;
    }

    public function getCurrencyRate($walletCurrency, $gateway_currency)
    {
        try {
            $walletCurrencyRate = CountryCurrency::where('code', $walletCurrency)->value('rate');
            $gatewayCurrencyRate = CountryCurrency::where('code', $gateway_currency)->value('rate');
            if (!$walletCurrencyRate || !$gatewayCurrencyRate) {
                throw new \Exception("Currency Rate not found.");
            }
            if ($gatewayCurrencyRate == 0) {
                throw new \Exception("Currency rate is zero.");
            }
            $rate = $walletCurrencyRate / $gatewayCurrencyRate;
            return $rate;
        } catch (\Exception $e) {
            return null;
        }
    }
}
