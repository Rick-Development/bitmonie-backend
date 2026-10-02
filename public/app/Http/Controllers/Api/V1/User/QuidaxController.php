<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\Withdrawals;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Services\QuidaxService;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;

class QuidaxController extends Controller
{
    public $quidax;

    public function __construct(QuidaxService $quidax)
    {
        $this->quidax = $quidax;
    }


    public function getUser()
    {
        $response = $this->quidax->getUser();

        return Response::success('User  data fetch successfully!', $response['data']);
    }



    public function fetchUserWallets(Request $request)
    {
        $response = $this->quidax->fetchUserWallets(auth()->user()->quidax_id);
        return Response::success('Wallets fetch successfully!', $response['data']);
    }

    //  fetchUserWallet($quidax_id,$currency)
    public function fetchUserWallet(Request $request)
    {
        $response = $this->quidax->fetchUserWallet(auth()->user()->quidax_id, $request->currency);
        return Response::success('Wallet fetch successfully!', $response['data']);
    }


    public function fetchPaymentAddress(Request $request)
    {
       
        $response = $this->quidax->fetchPaymentAddress(auth()->user()->quidax_id, $request->currency);
        
        return Response::success('Fetch successfully!', $response['data']);
    }
    public function fetchAddress(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required',
            'network' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }
 
        $response = $this->quidax->fetchPaymentAddressses(auth()->user()->quidax_id, $request->currency);
        $data = $response['data'];
        $data = array_filter($data, function ($item) use ($request) {
            return $item['network'] === $request->network;
        });

        if (empty($data)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No address found',
                'data' => []
            ], 422);
        }
        
        // Get the first (and only) matching address
        $data = reset($data);
        
        return Response::success('Fetch successfully!', $data);
    }
    public function fetchPaymentAddressses(Request $request)
    {
        $response = $this->quidax->fetchPaymentAddressses(auth()->user()->quidax_id, $request->currency);
        return Response::success($response['message'], $response['data']);
    }

    public function CryptoPaymentAddress(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required',
            'network' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user()->quidax_id;
        if (!$user) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Unknown quidax id',
                'data' => $user,
            ]);
        }

        $response = $this->quidax->createCryptoPaymentAddress($user, $request->currency, $request->network);
        return Response::success($response['message'], $response['data']);
    }
    public function createSwapQuotation(Request $request)
    {
        $data = [
            'from_currency' => strtolower($request->from_currency),
            'to_currency' => strtolower($request->to_currency),
            'from_amount' => "{$request->from_amount}",
            // 'to_amount' => '11'
        ];
        $response = $this->quidax->createSwapQuotation(auth()->user()->quidax_id, $data);
        return Response::success($response['message'], $response['data']);
    }
    public function swap(Request $request)
    {
        $response = $this->quidax->swap(auth()->user()->quidax_id, $request->quotation_id);
       if($response['status'] == 'error'){
        return Response::error( $response['message'], $response['data']);
       }
       if($response['status'] == 'Error'){
            return Response::error('Swap failed', $response['error']);
        }else{
            return Response::success($response['message'], $response['data']);
        }
    }

    public function fetch_withdraws(Request $request)
    {
        if (!$request->status || !$request->currency) {
            return response()->json([
                'message' => 'status or currency param required',
            ]);
        }

        $response = $this->quidax->fetch_withdraws(auth()->user()->quidax_id, $request->currency, $request->status);
        return Response::success($response['message'], $response['data']);
    }

    public function cancel_withdrawal(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'withdrawal_id' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $response = $this->quidax->cancel_withdrawal(auth()->user()->quidax_id, $request->withdrawal_id);
        return Response::success($response['message'], $response['data']);
    }

    public function getWithdrawalFee(Request $request) {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required|string',
            'network' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
           return Response::error('Validation failed',$validator->errors()->all());
        }

        try {
            $feeResponse = $this->quidax->getWithdrawalFee(strtolower($request->currency), $request->amount, strtolower($request->network));
            
            $feeAmount = $feeResponse['data']['fee'] ?? 0;
            $feeType = $feeResponse['data']['type'] ?? 'flat'; 

            // If fee is percentage (not likely checking docs but handled), usually crypto fees are flat
            // But if logic required:
            // if($feeType != 'flat'){ ... } 
            
            // Apply 2x Markup
            $markupFee = $feeAmount * 2;

            $data = [
                'currency' => $request->currency,
                'network' => $request->network,
                'original_fee' => $feeAmount,
                'total_fee' => $markupFee,
                'type' => $feeType
            ];

            return Response::success('Withdrawal fee fetched', $data);

        } catch (\Exception $e) {
            return Response::error('Failed to fetch fee: ' . $e->getMessage(),[]);
        }
    }

    public function create_withdrawal(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required|string',
            'network' => 'required|string',
            'amount' => 'required|string',
            'fund_uid' => 'required|string',
            // 'transaction_note' => 'required|string',
            // 'narration' => 'required|string',
        ]);



        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $data = $request->only([
            'currency',
            'network',
            'amount',
            'fund_uid',
            // 'transaction_note',
            // 'narration',
        ]);

        $referenceId = Str::uuid();
        $refIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['reference' => $refIdString]);

        $quidax_id = auth()->user()->quidax_id;
        if (is_null($quidax_id) || empty($quidax_id)) {
            return Response::error([
                'status' => 'failed',
                'message' => 'Invalid quidax id',
                'data' => [
                    'Quidax id' => $quidax_id
                ],
            ]);
        }

        // 1. Fee Calculation & Balance Check
        $user = auth()->user();
        $sourceCurrency = $request->currency;
        $network = $request->network;
        $sourceAmount = $request->amount;
        
        $feeResponse = $this->quidax->getWithdrawalFee(strtolower($sourceCurrency),$sourceAmount, strtolower($network));
        
       $feeAmount = $feeResponse['data']['fee'] ?? 0;
       $feeType = $feeResponse['data']['type'] ?? 'flat';

    if($feeType != 'flat'){
        $feeAmount = $sourceAmount * ($feeAmount / 100);
    }

    $feeAmount = $feeAmount * 2;

    $totalAmount = $sourceAmount + $feeAmount;
        
        // Check Local Wallet Balance
        // $wallet = UserWallet::where('user_id', $user->id)->whereHas('currency', function($q) use ($sourceCurrency) {
        //     $q->where('code', $sourceCurrency);
        // })->first();

        // if (!$wallet || $wallet->balance < $totalAmount) {
        //      return Response::errorResponse("Insufficient balance. Required: $totalAmount $sourceCurrency");
        // }
        
        // // Debit Local Wallet
        // $wallet->balance -= $totalAmount;
        // $wallet->save();
        
        // Main Account Withdrawal Data (Move total amount to main)
        $mainAccountData = [
            'currency' => $request->currency,
            'network' => $request->network,
            'amount' => $totalAmount,
            'fund_uid' => 'me',
            'transaction_note' => 'Withdrawal to main account',
            'narration' => 'Withdrawal to main account',
        ];

        // Dispatch Job
        // Pass fee and total for recording
        // We inject them into destinationData or pass separate args. 
        // Let's pass them as separate constructor args to the Job.
        $destinationData = $data; // Original dest data
        
        \App\Jobs\ProcessQuidaxWithdrawal::dispatchAfterResponse($user, $mainAccountData, $destinationData, $feeAmount, $totalAmount);

        return Response::success('Withdrawal initiated successfully. You will be notified once complete.',[]);
    }

    public function initiate_ramp_transaction(Request $request)
    {
        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount,
            'merchant_reference' => $request->merchant_reference,

            'customer' => [
                'email' => $request->customer_email,
                'first_name' => $request->customer_first_name,
                'last_name' => $request->customer_last_name,
            ],

            'wallet_address' => [
                'address' => $request->wallet_address,
                'network' => $request->wallet_network,
            ],
        ];
        $response = $this->quidax->initiate_ramp_transaction($data);
        // dd($response);
        return Response::success(
            $response['message'],
            [
                'api_data' => $response['data'],
                'user_data' => $data,
            ]
        );
    }

    public function refresh_instant_swap_quotation(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'quotation_id' => 'required | string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount
        ];

        $response = $this->quidax->refresh_instant_swap_quotation(auth()->user()->quidax_id, $request->quotation_id, $data);
        return Response::success($response['message'], $response['data']);
    }

    public function fetch_swap_transaction(Request $request)
    {
        if (!$request->transaction_id) {
            return response()->json([
                'message' => 'transaction_id param required',
            ]);
        }

        $response = $this->quidax->fetch_swap_transaction(auth()->user()->quidax_id, $request->transaction_id);
        // dd($response);
        return Response::success($response['message'], $response['data']);
    }

    public function get_swap_transaction()
    {
        $response = $this->quidax->get_swap_transacdtion(auth()->user()->quidax_id);
        return Response::success($response['message'], $response['data']);
    }

    public function temporary_swap_quotation(Request $request)
    {
        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount
        ];

        $response = $this->quidax->temporary_swap_quotation(auth()->user()->quidax_id, $data);
        return Response::success($response['message'], $response['data']);
    }
    // createCryptoPaymentAddress($quidax_id,$currency,$data)

    // fetchPaymentAddress($quidax_id,$currency){
    public function fetch_deposits(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required | string',
            'state' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->fetch_deposits(auth()->user()->quidax_id, $request->currency, $request->state);
        return Response::success($response['message'], $response['data']);
    }

    public function fetch_a_deposit(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'deposit_id' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->fetch_a_deposit(auth()->user()->quidax_id, $request->deposit_id);
        return Response::success($response['message'], $response['data']);
    }

    public function get_all_public_adverts(Request $request)
    {
        $data = $request->only('side');
        $response = $this->quidax->get_all_public_adverts($data);
        return Response::success($response['message'], $response['data']);
    }

    public function get_single_public_advert(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'advert_id' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->get_single_public_advert($request->advert_id);
        return Response::success($response['message'], $response['data']);
    }

}
