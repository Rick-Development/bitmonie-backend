<?php

namespace App\Http\Helpers\Payscribe;

use App\Http\Helpers\ConnectionHelper;
use App\Models\PayscribeAirtimeTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;

class PayscribeBalanceHelper extends ConnectionHelper{

    public function __construct(){
        parent::__construct();
    }

    public function createTransaction($request, $response, $billType) {
        $transId = $response['message']['details']['trans_id'];
        $ref = $response['message']['details']['ref'];

        PayscribeAirtimeTransaction::create([
            'user_id' => auth()->id(),
            'transaction_id' => $response['message']['details']['trans_id'],
            'transaction_type' => $billType,
            'transaction_status' => $response['message']['details']['transaction_status'],
            'amount' => $response['message']['details']['amount'] ?? $request['amount'],
            'network' => $response['message']['details']['product'] ?? $response['message']['details']['service'],
            'ref' => $ref,
            'discount' => $response['message']['details']['discount'] ?? null,
        ]);

        // $this->payscribeBalanceHelper->updateUserBalance($balance);
    }

    public function updateUserBalance($balance){
        User::where('id', auth()->id())->update(['account_balance' => $balance]);

    }

    // public function validateBalance($amount){
    //     $user = auth()->user();
    //     $user_balance = UserWallet::where('user_id', $user->id)
    //         ->where('currency_code', 'NGN')
    //         ->value('balance');
    //     if($user_balance < $amount){
    //         return true;
    //     }else {
    //         return false; // Balance is sufficient
    //     }
    // }
    public function validateBalance($amount){
        $user = auth()->user();
        $user_balance = UserWallet::where('user_id', $user->id)
            ->where('currency_code', 'NGN')
            ->value('balance');

        $amount = (int) $amount;
        if($user_balance< $amount){
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance'
            ], 500);
        }
    }

}
