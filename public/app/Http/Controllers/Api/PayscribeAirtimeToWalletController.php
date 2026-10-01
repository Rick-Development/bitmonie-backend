<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\AirtimeToWalletHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeAirtimeToWalletController extends Controller
{
    private $modelPath = 'Airtime To Wallet';
    public function __construct(private AirtimeToWalletHelper $airtimeToWalletHelper, private PayscribeBalanceHelper $payscribeBalanceHelper)
    {
        //
    }

    public function airtimeToWalletLookup(Request $request)
    {
        $response = json_decode($this->airtimeToWalletHelper->airtimeToWalletLookup(), true);


        return $response;
    }


    public function airtimeToWallet(Request $request) {
        $data = $request->validate([
            'network' => 'required',
            'phone_number' => 'required',
            'from' => 'required',
            'amount' => 'required | integer | min:1000 | max:20000',
        ]);

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try{
            $response = json_decode($this->airtimeToWalletHelper->airtimeToWallet($data), true);
            if($response['status'] === true){
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
                $this->sendBillPaymentEmail($data['amount'], $this->billType);
            }
            return $response;
        }catch(\Exception $e){
            return $response = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }


    // public function autoDataVending(Request $request) {
    //     $data = $request->validate([
    //         "plan" => 'required | string',
    //         "recipient" => 'required | string',
    //         "network" => 'required | string',
    //         "amount" => 'required | string',
    //     ]);

    //     // Validate user balance before proceeding
    //     $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

    //     if(!!$validateBalance){
    //             return $validateBalance; // form safe heaven balance
    //     }

    //     // Transfer to safe heaven
    //     $response = $this->billPurchaseController->getBillRequest($data, 'data vending');
    //     return $response;
    // }

    // public function payDataVending(array $data) {

    //     $response = json_decode($this->dataBundleHelper->dataVending($data), true);

    //     if($response['status'] === true){
    //         $this->createTransaction($data, $response, $this->modelPath);

    //     }

    //     return $response;
    // }

}