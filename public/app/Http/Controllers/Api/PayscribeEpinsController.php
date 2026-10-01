<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\BillsPayments\EpinsHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Controllers\API\BillPurchaseController;
use App\Http\Helpers\Payscribe\BillsPayments\BillPaymentHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeEpinsController extends Controller
{
    private $billType = 'Epins';
    public function __construct(private EpinsHelper $epinsHelper, private PayscribeBalanceHelper $payscribeBalanceHelper, private BillPaymentHelper $billPaymentHelper){}


    public function avaliableEpin() {
        try {
            $response = json_decode($this->epinsHelper->getAvaliableEpin(), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function purchaseEpin(Request $request) {
        $data = $request->validate([
            'id' => 'required | string',
            'qty' => 'required | string',
            'amount' => 'required | string',
        ]);
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['ref' => $referenceIdString]);
        try {
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

            if(!!$validateBalance){
                return $validateBalance;
            }

            $response = json_decode($this->epinsHelper->purchaseEpins($data), true);
            if($response['status'] === true){
                $this->payscribeBalanceHelper->createTransaction($data, $response, $this->billType);
                $user = auth()->user();
                $params = [
                   'amount' => $data['amount'],
                   'token' =>  $response['message']['details']['epins']['pin'],
               ];
               $this->mail($user, 'TOKEN_PURCHSED', $params);
            }
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }


    public function autoPurchaseEpin(Request $request) {
        $data = $request->validate([
            'id' => 'required | string',
            'qty' => 'required | string',
            'amount' => 'required | string',
        ]);

        // Validate user balance before proceeding
        $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);

        if(!!$validateBalance){
                return $validateBalance; // form safe heaven balance
        }

        // Transfer to safe heaven
        $response = $this->billPaymentHelper->getBillRequest($data, 'purchase_epin');
        return $response;
    }


    public function jambUserLookup(Request $request) {
        $data = $request->validate([
            'id' => 'required | string',
            'account' => 'sometimes | string',
        ]);
        try {
            $response = json_decode($this->epinsHelper->jambUserLookup($data), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function retreiveEpin(Request $request) {
        $data = $request->validate([
            'trans_id' => 'required | string',
        ]);
        try {
            $response = json_decode($this->epinsHelper->retreiveEpins($data), true);
            return $response;
        }
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }



}