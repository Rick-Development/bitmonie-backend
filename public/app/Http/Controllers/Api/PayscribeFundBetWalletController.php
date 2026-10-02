<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\FundBetWalletHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeFundBetWalletController extends Controller
{

    private $billType = 'Bet Wallet';

    public function __construct(private FundBetWalletHelper $fundBetWalletHelper, private PayscribeBalanceHelper $payscribeBalanceHelper){}
    //
    public function bettingServiceProviderList() {
        $response = json_decode( $this->fundBetWalletHelper->bettingServiceProviderList(), true);
        return $response;
    }

    public function validateBetAccount(Request $request) {
        $data = $request->validate([
            'bet_id' => 'required | string',
            'customer_id' => 'required | string',
        ]);

        $response = json_decode( $this->fundBetWalletHelper->validateBetAccount($data['bet_id'], $data['customer_id']), true);
        return $response;
    }

    public function fundWallet(Request $request) {
        $data = $request->validate( [
            "bet_id" => "string",
            "customer_id" =>  'required | string',
            "customer_name" =>  'required | string',
            "amount" =>  'required | integer',
        ]);


        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if(!!$validateBalance){
            return $validateBalance;
        }

        $response = json_decode($this->fundBetWalletHelper->fundWallet($data), true);

        if($response['status'] === true){
            $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
            $this->sendBillPaymentEmail($data['amount'], $this->billType);
        }
        return $response;

    }


}