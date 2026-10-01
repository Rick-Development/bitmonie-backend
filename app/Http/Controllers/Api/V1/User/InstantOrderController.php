<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Services\QuidaxService;
use App\Services\SafeHavenService; // Import SafeHavenService
use App\Http\Helpers\Response;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\VirtualAccounts; // Import VirtualAccounts
use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class InstantOrderController extends Controller
{
    protected $quidax;

    public function __construct(QuidaxService $quidax)
    {
        $this->quidax = $quidax;
    }

    /**
     * Initiate Instant Buy Order
     */
    public function buy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string', // e.g. 'ngn'
            'to_currency'   => 'required|string', // e.g. 'btc'
            'amount'        => 'required|numeric|min:0',
            'type'          => 'required|in:buy',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            if (!$user->quidax_id) {
                return Response::errorResponse('User does not have a Quidax account linked.');
            }

            // Payload
            $bid = strtolower($request->from_currency);
            $ask = strtolower($request->to_currency);
            
            $payload = [
                'bid' => $bid,
                'ask' => $ask,
                'type' => 'buy',
                'total' => $request->amount, // Amount in From Currency (Fiat)
                'unit' => $bid, // Explicitly specify unit matches the amount currency
            ];
            
            Log::info("Instant Buy Payload: " . json_encode($payload));

            $response = $this->quidax->createInstantOrder($user->quidax_id, $payload);
            
            // Check for explicit error status or data code
            if(!$response || (isset($response['status']) && $response['status'] == 'error')) {
                 $msg = $response['message'] ?? 'Unknown error';
                 if(isset($response['data']['message'])) {
                     $msg .= ' - ' . $response['data']['message'];
                 }
                 return Response::errorResponse('Failed to create instant order quote: ' . $msg);
            }

            if(!isset($response['data'])) {
                 return Response::errorResponse('Failed to create instant order quote: Invalid response structure.');
            }

            return Response::successResponse('Instant order created successfully. Please confirm.', [
                'order' => $response['data']
            ]);

        } catch (\Exception $e) {
            return Response::errorResponse('Failed to initiate buy order: ' . $e->getMessage());
        }
    }

    /**
     * Initiate Instant Sell Order
     */
    public function sell(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string',
            'to_currency'   => 'required|string',
            'amount'        => 'required|numeric|min:0',
            'type'          => 'required|in:sell',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            if (!$user->quidax_id) {
                return Response::errorResponse('User does not have a Quidax account linked.');
            }

            // Payload
            $bid = strtolower($request->to_currency);
            $ask = strtolower($request->from_currency);

            $payload = [
                'bid' => $bid, 
                'ask' => $ask,
                'type' => 'sell',
                'volume' => $request->amount, // Amount in Crypto
                'unit' => $ask, // Unit matches the crypto being sold
            ];
            
            Log::info("Instant Sell Payload: " . json_encode($payload));

            $response = $this->quidax->createInstantOrder($user->quidax_id, $payload);

            if(!$response || (isset($response['status']) && $response['status'] == 'error')) {
                 $msg = $response['message'] ?? 'Unknown error';
                 if(isset($response['data']['message'])) {
                     $msg .= ' - ' . $response['data']['message'];
                 }
                 return Response::errorResponse('Failed to create instant order quote: ' . $msg);
            }

            if(!isset($response['data'])) {
                 return Response::errorResponse('Failed to create instant order quote: Invalid response structure.');
            }

            return Response::successResponse('Instant sell order created successfully. Please confirm.', [
                'order' => $response['data']
            ]);

        } catch (\Exception $e) {
            return Response::errorResponse('Failed to initiate sell order: ' . $e->getMessage());
        }
    }

    /**
     * Confirm Instant Order
     */
    public function confirm(Request $request, SafeHavenService $safeHaven)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $orderId = $request->order_id;
            
            // 1. Fetch Order Details to Verify Amount
            $orderCheck = $this->quidax->getInstantOrder($user->quidax_id, $orderId);
            
            Log::info("Confirm Order Check Response: " . json_encode($orderCheck));

            if(!isset($orderCheck['data'])) {
                return Response::errorResponse('Invalid Order ID or Order Expired.');
            }
            
            $orderData = $orderCheck['data'];
            $state = $orderData['state'] ?? $orderData['status'] ?? 'unknown';
            
            // Verify state if needed...

            $type = $orderData['side']; // 'buy' or 'sell'
            $totalAmount = $orderData['total']; // Cost in NGN (for buy) or Receive in NGN (for sell)

            return DB::transaction(function() use ($user, $orderId, $type, $totalAmount, $orderData, $safeHaven) {
                
                // BUY FLOW: Debit User (Real Money + DB) -> Fund Quidax -> Confirm
                if ($type == 'buy') {
                    
                    // A. Check Local DB Balance first (Fast Fail)
                    $wallet = UserWallet::where('user_id', $user->id)->where('currency', 'NGN')->first();
                    if (!$wallet || $wallet->balance < $totalAmount) {
                        throw new Exception('Insufficient Local Balance for Purchase.');
                    }
                    
                    // B. Real Money Move (SafeHaven Sub -> SafeHaven App Main)
                    $userVa = $safeHaven->getUserSubAccount($user);
                    if(!$userVa) {
                        throw new Exception("User Virtual Account not found. Cannot debit funds.");
                    }
                    
                    try {
                        // This throws exception if fails
                        $safeHaven->debitSubAccountToApp($userVa->account_number, $totalAmount);
                    } catch(\Exception $e) {
                        throw new Exception("Fund Transfer Failed: " . $e->getMessage());
                    }

                    // C. Local DB Debit
                    $wallet->balance -= $totalAmount;
                    $wallet->save();

                    // D. Fund Quidax Sub-Account (Bridge: App Main -> User Sub)
                    // We assume App Main SafeHaven money matches App Main Quidax money availability.
                    $fundResponse = $this->quidax->fundSubAccount($user->quidax_id, $totalAmount, 'ngn');
                    
                    if (!isset($fundResponse['status']) || $fundResponse['status'] != 'success') {
                         Log::critical("Quidax Funding Failed after SafeHaven Debit! User: {$user->id}, Amt: {$totalAmount}");
                         // Suggestion: Trigger Auto-Reversal here?
                         throw new Exception('Bridge Funding Failed. Please contact support. ' . ($fundResponse['message'] ?? ''));
                    }

                    // E. Log Transaction
                    $trx = Transaction::create([
                        'user_id' => $user->id,
                        'user_wallet_id' => $wallet->id,
                        'payment_gateway_currency_id' => null,
                        'type' => 'INSTANT_BUY',
                        'trx_id' => Str::uuid(),
                        'request_amount' => $totalAmount,
                        'payable' => $totalAmount,
                        'available_balance' => $wallet->balance,
                        'remark' => 'Instant Crypto Buy',
                        'details' => json_encode(['order_id' => $orderId]),
                        'status' => 1,
                        'created_at' => now(),
                    ]);
                }

                // 2. Confirm Order on Quidax
                $confirmResponse = $this->quidax->confirmInstantOrder($user->quidax_id, $orderId);
                
                Log::info("Confirm Order Quidax Response: " . json_encode($confirmResponse));

                if (!isset($confirmResponse['data']) || ($confirmResponse['status'] ?? '') != 'success') {
                    // If Buy failed, Refund logic would be complex (Real money refund).
                    // For now, throw.
                    throw new Exception('Quidax Confirmation Failed: ' . ($confirmResponse['message'] ?? 'Unknown'));
                }

                // SELL FLOW: Handle Credit
                if ($type == 'sell') {
                     $receiveAmount = $orderData['total']; // NGN Received
                     // For Sell, we should probably Withdraw from Quidax -> SafeHaven?
                     // Leaving as DB Credit for now as requested.
                     
                     $wallet = UserWallet::updateOrCreate(
                        ['user_id' => $user->id, 'currency' => 'NGN'],
                        ['currency' => 'NGN', 'c_code' => 'NGN']
                     );
                     
                     $wallet->balance += $receiveAmount;
                     $wallet->save();
                     
                     $trx = Transaction::create([
                        'user_id' => $user->id,
                        'user_wallet_id' => $wallet->id,
                        'payment_gateway_currency_id' => null,
                        'type' => 'INSTANT_SELL',
                        'trx_id' => Str::uuid(),
                        'request_amount' => $orderData['volume'], // Crypto Sold
                        'payable' => $receiveAmount, // NGN Received
                        'available_balance' => $wallet->balance,
                        'remark' => 'Instant Crypto Sell',
                        'details' => json_encode(['order_id' => $orderId]),
                        'status' => 1,
                        'created_at' => now(),
                    ]);
                }

                return Response::successResponse('Order confirmed successfully.', ['transaction' => $orderData]);
            });

        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }
}
