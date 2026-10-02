<?php

namespace App\Http\Helpers\SafeHeaven;

use App\Models\Transaction;
use App\Models\UserWallet;

class SafeHeavenBalanceHelper{



    private function createTransaction($request, $response, $modelPath) {
        $balance = auth()->user()->account_balance - $request['amount'];
        $transId = $response['message']['details']['trans_id'] ?? null;
        Transaction::create([
            'transactional_type' => $modelPath,
            'user_id' => auth()->user()->id,
            'amount' => $response['message']['details']['amount'],
            'currency' => 'NGN',
            'balance' => $balance,
            'charge' => $response['message']['details']['total_charged'],
            'trx_type' => '-',
            'remarks' => $response['description'],
            'trx_id' => $transId,
        ]);
    } 

    public function updateUserBalance( $amount, string $trx_type){
        // UserWallet::where('user_id', auth()->id())->update(['account_balance' => $balance]);
        $userWallet = UserWallet::where('user_id', auth()->id())->where('currency_code', 'NGN')->first();
        if ($trx_type === '-') {
            $newBalance = bcsub($userWallet->balance, $amount, 2);
        } else {
            $newBalance = bcadd($userWallet->balance, $amount, 2);
        }
    
        $userWallet->update(['balance' => $newBalance]);

    }

    public function validateBalance($amount){
        $userWallet = UserWallet::where('user_id', auth()->id())->where('currency_code', 'NGN')->first();
        $amount = (int) $amount;
        if($userWallet->balance < $amount){
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance'
            ], 404);
        }
    }
    
}