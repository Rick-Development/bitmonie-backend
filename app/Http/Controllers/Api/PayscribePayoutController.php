<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\Payout\PayoutHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Http\Helpers\Payscribe\PayscribePayoutHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\Notify;

class PayscribePayoutController extends Controller
{
    use Notify;

    private $modelPath = 'PayscribePayout';

    public function __construct(private PayscribePayoutHelper $payscribePayoutHelper, private PayscribeBalanceHelper $payscribeBalanceHelper){}

    public function accountLookUp(Request $request) {
        $data = $request->validate([
            'account' => 'required | string',
            'bank' => 'required | string',
        ]);
        
        try {
            $response = json_decode($this->payscribePayoutHelper->validateAccountBeforeInitiatingTransfer($data), true);
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
            $response = json_decode($this->payscribePayoutHelper->getPayoutsFee($data['amount']), true);
            
            if (!isset($response['message']['details']['fee'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to fetch payout fee at the moment.'
                ], 422);
            }

            $baseFee = $response['message']['details']['fee'];
            $fee = $baseFee + 10;

            return [
                "status" => true,
                "description" => "Transfer fee lookup successful.",
                "message" => [
                    "details" => [
                        "amount" => $response['message']['details']['amount'] ?? $data['amount'],
                        "currency" => $response['message']['details']['currency'] ?? 'NGN',
                        "fee" => $fee,
                    ]
                ],
                "status_code" => $response['status_code'] ?? 200,
            ];
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
            $response = json_decode($this->payscribePayoutHelper->getPayoutsFee($amount), true);
            
            if (!isset($response['message']['details']['fee'])) {
                return 10; // Fallback fee
            }

            $fee = $response['message']['details']['fee'] + 10;
            return (double) $fee;
        } 
        catch(\Exception $e){
            return 10.00;
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
        $totalAmountNeeded = floatval($data['amount']) + $payoutFee;

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId;
        $data = array_merge($data, ['ref' => $referenceIdString]);

        try {
            // Validate balance against total amount (amount + fee)
            $validateBalance = $this->payscribeBalanceHelper->validateBalance($totalAmountNeeded);
            
            if(!!$validateBalance){
                return $validateBalance;
            }

            $response = json_decode($this->payscribePayoutHelper->transfer($data), true);

            if(isset($response['status']) && $response['status'] === true){
                $this->createTransaction($data, $response, $this->modelPath, $payoutFee); 

                $user = auth()->user();

                // Notify user using the Notify trait
                $this->sendNotification(
                    user: $user,
                    templateKey: 'TRANSFER_SUCCESS',
                    params: [
                        'user' => $user->firstname,
                        'amount' => number_format($data['amount']),
                        'recipient' => $data['account'] . ' (' . $data['bank'] . ')',
                        'reference' => $response['message']['details']['ref'] ?? $referenceIdString,
                        'status' => 'Successful',
                    ],
                    channels: ['mail', 'inapp'],
                    options: [
                        'referenceId' => $response['message']['details']['ref'] ?? $referenceIdString,
                    ]
                );
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
            $response = json_decode($this->payscribePayoutHelper->verifyTransfer($data), true);
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
        $amount = $response['message']['details']['amount'] ?? $request['amount'];
        $totalCharge = floatval($amount) + $fee;
        
        $user = auth()->user();
        $balance = $user->account_balance;
        
        $user->update([
            'account_balance' => $balance - $totalCharge
        ]);

        $charge = $fee;
        $transId = $response['message']['details']['trans_id'] ?? $request['ref'];

        Transaction::create([
            'transactional_type' => $modelPath,
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'NGN',
            'charge' => $charge,
            'trx_type' => '-',
            'remarks' => $response['description'] ?? 'Payout transfer',
            'trx_id' => $transId,
            'transaction_status' => 'processing',
        ]);
    } 
}