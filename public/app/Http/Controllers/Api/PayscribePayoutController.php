<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\Payout\PayoutHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\PayscribePayoutHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribePayoutController extends Controller
{
    private $modelPath = 'PayscribePayout';

    public function __construct(private PayscribePayoutHelper $payscribePayoutHelper, private PayscribeBalanceHelper $payscribeBalanceHelper){}

    public function accountLookUp(Request $request) {
        $data = $request->validate([
            'account' => 'required | string',
            'bank' => 'required | string',
        ]);
        
        try {
            $response = json_decode($this->payscribePayoutHelper->validateAccountBeforeInitiatingTransfer($data), 
            true);

            return $response;
        } 
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function payoutFee(Request $request){
        $data = $request->validate([
            'amount' => 'required | string',
        ]);
        try {
            $response = json_decode($this->payscribePayoutHelper->getPayoutsFee($data['amount']), 
            true);
            $fee = $response['message']['details']['fee'] + 10;
            return [
                "status" => true,
                "description" => "Transfer fee lookup successful.",
                "message" => [
                    "details" => [
                        "amount" => $response['message']['details']['amount'],
                        "currency" => $response['message']['details']['currency'],
                        "fee" => $fee,
                    ]
                ],
                "status_code" => $response['status_code'],
            ];
            // return $response;
        } 
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    
    
    public function getpayoutFee($amount){
        try {
            $response = json_decode($this->payscribePayoutHelper->getPayoutsFee($amount), 
            true);
            $fee = $response['message']['details']['fee'] + 10;
            return (double)$fee;
            return [
                "status" => true,
                "description" => "Transfer fee lookup successful.",
                "message" => [
                    "details" => [
                        "amount" => $response['message']['details']['amount'],
                        "currency" => $response['message']['details']['currency'],
                        "fee" => $fee,
                    ]
                ],
                "status_code" => $response['status_code'],
            ];
            // return $response;
        } 
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required | string',
            'bank' => 'required | string',
            'account' => 'required | string',
            'currency' => 'required | string',
            'narration' => 'required | string',
        ]);
        $payoutFee = $this->getpayoutFee($data['amount']);
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try {
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($data['amount']);
            
            if(!!$validateBalance){
                return $validateBalance;
            }

            $response = json_decode($this->payscribePayoutHelper->transfer($data), 
            true);

            if($response['status'] === true){
                
                // $userId = \auth()->id();
                // $user = User::where('id', $userId)->first();
                $this->createTransaction($data, $response, $this->modelPath,$payoutFee); 
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

    public function verifyTransfer(Request $request) {
        $data = $request->validate([
            'trans_id' => 'required | string',
        ]);

        try {
            $response = json_decode($this->payscribePayoutHelper->verifyTransfer($data), 
            true);


            return $response;
        } 
        catch(\Exception $e){
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        
    }

    private function createTransaction($request, $response, $modelPath, $fee = 0) {
        
        
        $amount =  $response['message']['details']['amount'];
        $totalCharge = $amount + $fee;
        $user = auth()->user();
        $balance = $user->account_balance;
        $user->update([
            'account_balance' => $balance - $totalCharge
        ]);
        // $totalCharge = $response['message']['details']['total'];
        $charge = $fee; //$response['message']['details']['fee'];
        
        // $balance = auth()->user()->account_balance - $totalCharge;
        $transId = $response['message']['details']['trans_id'];
        Transaction::create([
            'transactional_type' => $modelPath,
            'user_id' => auth()->user()->id,
            'amount' => $amount,
            'currency' => 'NGN',
            'charge' => $charge,
            'trx_type' => '-',
            'remarks' => $response['description'],
            'trx_id' => $transId,
            'transaction_status' => 'proccessing',
        ]);
    } 
}