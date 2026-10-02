<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PAd;
use App\Models\P2POrder;
use App\Models\P2PChat;
use App\Models\UserWallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\P2PEscrow;
use App\Services\QuidaxService;
use Exception;
use App\Models\User;
use App\Traits\Notify;

class P2POrderController extends Controller
{
  use Notify;

  protected QuidaxService $quidax;

public function __construct(QuidaxService $quidax)
{
    $this->quidax = $quidax;
}
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'ad_id'       => 'required|integer|exists:p2p_ads,id',
        'amount'      => 'nullable|numeric|min:0',
        'fiat_amount' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return Response::errorResponse(
            'Validation Error',
            $validator->errors()->all()
        );
    }

    if (!$request->filled('amount') && !$request->filled('fiat_amount')) {
        return Response::errorResponse(
            'Please provide amount or fiat_amount.'
        );
    }

    $buyer = auth()->user();

    try {

        DB::beginTransaction();

        /**
         * STEP 1
         * Load advertisement
         */
        $ad = P2PAd::where('id', $request->ad_id)
            ->where('status', 'online')
            ->whereHas('user', function ($q) {
                $q->where('merchant_status', 'approved');
            })
            ->lockForUpdate()
            ->firstOrFail();


if ($ad->user_id === $buyer->id) {
    throw new \Exception(
        'You cannot accept your own advertisement.'
    );
}

        /**
         * STEP 2
         * Validate trade
         */
    
        /**
         * STEP 3
         * Calculate amounts
         */
        $amounts = $this->validateOrder($ad, $request);
        

        /**
         * STEP 4
         * Determine participants
         */
        $participants = $this->determineParticipants($ad, $buyer);
        
        
        /**
         * STEP 5
         * Lock buyer fiat
         */
        $wallet = $this->lockFiatBuyerFunds($participants['fiat_buyer_id'],$ad->fiat,$amounts['fiat']);

        /**
         * STEP 6
         * Create fiat escrow
         */
        $fiatEscrow = $this->createFiatEscrow($wallet,$amounts['fiat'], $ad);

        /**
         * STEP 7
         * Reserve crypto on advertisement
         */
        $this->reserveAdAmount(
            $ad,
            $amounts['crypto']
        );

        /**
         * STEP 8
         * Create order
         */
        $order = $this->createOrder($ad,$participants,$fiatEscrow,$amounts);

        DB::commit();

    } catch (\Throwable $e) {

        DB::rollBack();

        Log::error('P2P Order Error', ['user_id' => $buyer->id,'error'   => $e->getMessage() ]);

        return Response::errorResponse($e->getMessage());
    }

    /**
     * STEP 9
     * Lock crypto of the actual crypto seller
     * (merchant for SELL ads, buyer for BUY ads)
     */
    try {

        $cryptoEscrow = $this->lockCryptoSeller(
            $participants['crypto_seller_id'],
            $ad->asset,
            $amounts['crypto']
        );

        $cryptoEscrow->update([
    'order_id'=>$order->id
]);
        $order->update([
            'crypto_escrow_id' => $cryptoEscrow->id
        ]);

    } catch (\Throwable $e) {

        /**
         * External call failed.
         * Roll everything back.
         */
        $this->rollbackOrder(
            $order,
            $fiatEscrow,
            $amounts['crypto'],
            $ad->id
        );

        //realease crypto
        //release fiat

        return Response::errorResponse($e->getMessage());
    }

    $this->sendNotification(
    $ad->user,
    'P2P_ORDER_RECEIVED',
    [
        'order_id' => $order->reference,
        'amount'   => $order->crypto_amount,
        'asset'    => $order->asset,
    ],
    ['mail', 'push', 'inapp']
);

    return Response::successResponse(
        'Order created successfully.',
        [
            'order' => $order->fresh()
        ],
        201
    );
}


private function refundFiatEscrow(P2PEscrow $escrow)
{
    $wallet = UserWallet::where('user_id',$escrow->user_id)
        ->where('currency_code',$escrow->asset)
        ->lockForUpdate()
        ->firstOrFail();


    $wallet->balance = bcadd(
        $wallet->balance,
        $escrow->amount,
        2
    );


    $wallet->escrow_balance = bcsub(
        $wallet->escrow_balance,
        $escrow->amount,
        2
    );


    $wallet->save();


    $escrow->update([
        'status'=>'refunded'
    ]);
}
private function createOrder(P2PAd $ad,array $participants,P2PEscrow $fiatEscrow,array $amounts): P2POrder {

    return P2POrder::create([

        /*
        |--------------------------------------------------------------------------
        | Advertisement
        |--------------------------------------------------------------------------
        */
        'ad_id' => $ad->id,

        /*
        |--------------------------------------------------------------------------
        | Participants
        |--------------------------------------------------------------------------
        */
        'maker_id' => $participants['maker_id'],
        'taker_id' => $participants['taker_id'],

      /*
|--------------------------------------------------------------------------
| Crypto Participants
|--------------------------------------------------------------------------
*/
'crypto_seller_id' => $participants['crypto_seller_id'],
'crypto_buyer_id'  => $participants['crypto_buyer_id'],

/*
|--------------------------------------------------------------------------
| Fiat Participants
|--------------------------------------------------------------------------
*/
'fiat_buyer_id'  => $participants['fiat_buyer_id'],
'fiat_seller_id' => $participants['fiat_seller_id'],

        /*
        |--------------------------------------------------------------------------
        | Trade
        |--------------------------------------------------------------------------
        */
        'type' => $ad->type,
        'asset' => strtoupper($ad->asset),
        'quote_currency' => strtoupper($ad->fiat),

        'amount' => $amounts['crypto'],
        'price' => $ad->price,
        'locked_price' => $ad->price,
        'total' => $amounts['fiat'],

        /*
        |--------------------------------------------------------------------------
        | Escrow
        |--------------------------------------------------------------------------
        */
        'fiat_escrow_id' => $fiatEscrow->id,
        'crypto_escrow_id' => null,
        'escrow_enabled' => true,

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */
        'status' => 'accepted',

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */
        'payment_deadline' => now()->addMinutes($ad->time_limit),
    ]);
}

private function reserveAdAmount(P2PAd $ad,string $cryptoAmount): void {

    if (bccomp((string) $ad->available_amount,$cryptoAmount,8) === -1
    ) {
        throw new \Exception('Insufficient crypto available.');
    }

    $ad->available_amount = bcsub((string) $ad->available_amount,$cryptoAmount,8);

    $ad->save();
}



private function determineParticipants(P2PAd $ad, User $taker): array
{
    $merchant = $ad->user;

    if ($merchant->id === $taker->id) {
        throw new \Exception(
            'You cannot trade on your own advertisement.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SELL AD
    |--------------------------------------------------------------------------
    |
    | Merchant sells crypto
    | Taker buys crypto
    |
    */

    if ($ad->type === 'sell') {

        return [

            'maker_id' => $merchant->id,
            'taker_id' => $taker->id,

            // Crypto movement
            'crypto_seller_id' => $merchant->id,
            'crypto_buyer_id'  => $taker->id,

            // Fiat movement
            'fiat_buyer_id'  => $taker->id,
            'fiat_seller_id' => $merchant->id,

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | BUY AD
    |--------------------------------------------------------------------------
    |
    | Merchant buys crypto
    | Taker sells crypto
    |
    */


    return [

        'maker_id' => $merchant->id,
        'taker_id' => $taker->id,


        // Crypto movement
        'crypto_seller_id' => $taker->id,
        'crypto_buyer_id'  => $merchant->id,


        // Fiat movement
        'fiat_buyer_id'  => $merchant->id,
        'fiat_seller_id' => $taker->id,

    ];
}
// private function validateOrder(P2PAd $ad, Request $request): array
// {
//     /*
//     |--------------------------------------------------------------------------
//     | Determine Fiat Amount
//     |--------------------------------------------------------------------------
//     |
//     | The user may send either:
//     | - fiat_amount (recommended)
//     | - amount (treated as fiat for compatibility)
//     |
//     */

//     if ($request->filled('fiat_amount')) {

//         $fiatAmount = number_format(
//             (float) $request->fiat_amount, 2,'.', '' );

//     } else {

//         $fiatAmount = number_format((float) $request->amount,2,'.','');
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | Calculate Crypto Amount
//     |--------------------------------------------------------------------------
//     */

//     $cryptoAmount = bcdiv( $fiatAmount, (string) $ad->price,8);

//     /*
//     |--------------------------------------------------------------------------
//     | Maximum fiat value still available on this ad
//     |--------------------------------------------------------------------------
//     */

//     $availableFiat = bcmul( (string) $ad->available_amount,(string) $ad->price, 2);

//     /*
//     |--------------------------------------------------------------------------
//     | Effective Maximum
//     |--------------------------------------------------------------------------
//     |
//     | If merchant has less crypto than his configured max,
//     | use the remaining crypto value.
//     |
//     */

//     $effectiveMax = min((float) $ad->max_limit,(float) $availableFiat);

//     /*
//     |--------------------------------------------------------------------------
//     | Validate Min / Max
//     |--------------------------------------------------------------------------
//     */

//     if ( (float) $fiatAmount < (float) $ad->min_limit ||(float) $fiatAmount > $effectiveMax) {

//         if (
//             (float) $fiatAmount > $effectiveMax &&
//             $effectiveMax < (float) $ad->max_limit
//         ) {

//             throw new \Exception(
//                 "Maximum available order is {$effectiveMax} {$ad->fiat}."
//             );
//         }

//         throw new \Exception(
//             "Order must be between {$ad->min_limit} and {$effectiveMax} {$ad->fiat}."
//         );
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | Ensure Merchant Has Enough Crypto Remaining
//     |--------------------------------------------------------------------------
//     */

//     if (
//         bccomp((string) $ad->available_amount,$cryptoAmount, 8) === -1
//     ) {

//         throw new \Exception(
//             "Not enough crypto remaining in this advertisement."
//         );
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | Return validated values
//     |--------------------------------------------------------------------------
//     */

//    return ['fiat' => $fiatAmount,'crypto' => $cryptoAmount,'effective_max' => $effectiveMax];
// }
private function validateOrder(P2PAd $ad, Request $request): array
{
    if ($request->filled('amount') && !$request->filled('fiat_amount')) {
        // User provided crypto amount, calculate fiat
        $cryptoAmount = number_format((float) $request->amount, 8, '.', '');
        $fiatAmount = bcmul($cryptoAmount, (string) $ad->price, 2);
    } elseif ($request->filled('fiat_amount')) {
        // User provided fiat amount, calculate crypto
        $fiatAmount = number_format((float) $request->fiat_amount, 2, '.', '');
        $cryptoAmount = bcdiv($fiatAmount, (string) $ad->price, 8);
    } else {
        throw new \Exception('Please provide amount or fiat_amount.');
    }

    $availableFiat = bcmul((string) $ad->available_amount, (string) $ad->price, 2);
    $effectiveMax = min((float) $ad->max_limit, (float) $availableFiat);

    if ((float) $fiatAmount < (float) $ad->min_limit || (float) $fiatAmount > $effectiveMax) {
        throw new \Exception(
            "Order must be between {$ad->min_limit} and {$effectiveMax} {$ad->fiat}."
        );
    }

    if (bccomp((string) $ad->available_amount, $cryptoAmount, 8) === -1) {
        throw new \Exception("Not enough crypto remaining in this advertisement.");
    }

    return ['fiat' => $fiatAmount, 'crypto' => $cryptoAmount, 'effective_max' => $effectiveMax];
}

private function lockFiatBuyerFunds( int $buyerId,string $currency,string $amount):UserWallet 
{

    /*
    |--------------------------------------------------------------------------
    | Lock buyer wallet row
    |--------------------------------------------------------------------------
    */

    $wallet = UserWallet::where('user_id', $buyerId) ->where('currency_code', strtoupper($currency))
        ->lockForUpdate()->first();

    if (!$wallet) {
        throw new \Exception(
            "{$currency} wallet not found."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure buyer has enough available balance
    |--------------------------------------------------------------------------
    */

    if (bccomp( (string) $wallet->balance, $amount, 2 ) === -1) {

        throw new \Exception(
            "Insufficient {$currency} balance."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Move money into escrow
    |--------------------------------------------------------------------------
    */


    $wallet->balance = bcsub((string) $wallet->balance, $amount, 2 );

    

    $wallet->save();
//$wallet->escrow_balance = bcadd((string) $wallet->escrow_balance,$amount,2);

return $wallet;
}
/**--------------------------------------------------------------------------
| Create Fiat Escrow Record
|--------------------------------------------------------------------------
|
| Records the buyer's fiat that has been locked for this order.
| The actual debit from the wallet has already been done in
| lockBuyerFiat().
|
*/
private function createFiatEscrow(UserWallet $wallet,string $amount,P2PAd $ad):P2PEscrow  {

    return P2PEscrow::create([

        /*
        |--------------------------------------------------------------------------
        | Owner of the escrow
        |--------------------------------------------------------------------------
        */
        'user_id' => $wallet->user_id,

        /*
        |--------------------------------------------------------------------------
        | Escrow Type
        |--------------------------------------------------------------------------
        */
        'type' => 'order_locking',

        /*
        |--------------------------------------------------------------------------
        | Fiat Currency
        |--------------------------------------------------------------------------
        */
        'asset' => strtoupper($ad->fiat),

        /*
        |--------------------------------------------------------------------------
        | Amount Locked
        |--------------------------------------------------------------------------
        */
        'amount' => $amount,

        /*
        |--------------------------------------------------------------------------
        | Currency Type
        |--------------------------------------------------------------------------
        */
        'currency_type' => 'fiat',

        /*
        |--------------------------------------------------------------------------
        | Escrow Status
        |--------------------------------------------------------------------------
        */
        'status' => 'held',

        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */
        'notes' => sprintf(
            'Buyer fiat escrow for P2P %s order.',
            strtoupper($ad->asset)
        ),
    ]);
}
private function lockCryptoSeller(int $cryptoSellerId,string $asset, string $cryptoAmount): P2PEscrow
{
    /*
    |--------------------------------------------------------------------------
    | Fetch Crypto Seller
    |--------------------------------------------------------------------------
    */

    $seller = User::findOrFail($cryptoSellerId);

    if (empty($seller->quidax_id)) {
        throw new \Exception(
            'Crypto seller has no Quidax account.'
        );
    }

    $quidax = app(QuidaxService::class);

    $assetCode = strtolower($asset);

    /*
    |--------------------------------------------------------------------------
    | Fetch Wallet
    |--------------------------------------------------------------------------
    */

    $wallet = $quidax->fetchUserWallet($seller->quidax_id,$assetCode);

    if (!isset($wallet['data']['balance'])) {
        throw new \Exception(
            'Unable to fetch seller crypto wallet.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verify balance
    |--------------------------------------------------------------------------
    */

    if (bccomp((string) $wallet['data']['balance'],$cryptoAmount,8) === -1
    ) {
        throw new \Exception(
            "Seller has insufficient {$asset}."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lock crypto in Quidax escrow
    |--------------------------------------------------------------------------
    */
   $escrow = P2PEscrow::create([
        'user_id'         => $cryptoSellerId,
        'type'            => 'order_locking',
        'asset'           => strtoupper($asset),
        'amount'          => $cryptoAmount,
        'currency_type'   => 'crypto',
        'status'          => 'pending',
        'transaction_ref' => null,//available at the point of creation
        'notes'           => 'Crypto locked in Quidax escrow'
    ]);




    $transfer = $quidax->transferToEscrow(
        $seller->quidax_id,
        $cryptoAmount,
        $assetCode
    );

    if (($transfer['status'] ?? '') !== 'success') {
        throw new \Exception(
            'Unable to lock crypto in escrow.'
        );
    
    }

    /*
    |--------------------------------------------------------------------------
    | Record escrow locally
    |--------------------------------------------------------------------------
    */
$escrow->update([
'status'=>'held',
'transaction_ref' => $transfer['data']['id'] ?? null,
]);
return $escrow;
}
/**
     * Rollback order and escrow when external Quidax call fails
     * 
     */
    private function rollbackOrder(P2POrder $order, ?P2PEscrow $escrow, string $cryptoAmount, int $adId)
    {
        DB::beginTransaction();
        try {
            // Restore ad available amount
            $ad = P2PAd::where('id', $adId)->lockForUpdate()->first();
            if ($ad) {
                $ad->available_amount = bcadd((string)$ad->available_amount, $cryptoAmount, 8);
                $ad->save();
            }
if($escrow){
    $this->refundFiatEscrow($escrow);
}
            // Delete the order
            $order->delete();

            // Delete the pending escrow
            if ($escrow) {
                $escrow->delete();
            }

            DB::commit();

            Log::info('P2P Order rolled back successfully', [
                'order_id' => $order->id,
                'ad_id' => $adId
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('P2P rollback failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id ?? null,
            ]);
        }
    }
    /**
     * Buyer marks payment sent
     */
 

    /**
     * Seller releases crypto
     */
    public function markPaid(Request $request, int $uid)
{
    $validator = Validator::make($request->all(), [
        'payment_proof' => 'required|image|mimes:jpeg,png,jpg|max:5120',
    ]);

    if ($validator->fails()) {
        return Response::errorResponse(
            'Validation Error',
            $validator->errors()->all()
        );
    }

    $order = P2POrder::where('id', $uid)
        ->where('fiat_buyer_id', auth()->id())
        ->firstOrFail();


    if ($order->status !== 'accepted') {
        return Response::errorResponse(
            'Invalid order status'
        );
    }


    $imagePath = $request->file('payment_proof')
        ->store('p2p_proofs', 'public');


    if (!$imagePath) {
        return Response::errorResponse(
            'Failed to store payment proof. Please try again.'
        );
    }


    $order->update([
        'payment_proof' => $imagePath,
        'status'        => 'paid',
    ]);


    $this->broadcastEvent(
        new \App\Events\P2POrderStatusUpdated($order)
    );


    return Response::successResponse(
        'Payment marked as sent. Waiting for crypto seller confirmation.',
        [
            'order' => $order->fresh()
        ]
    );
}

/**|--------------------------------------------------------------------------
| Release P2P Trade
|--------------------------------------------------------------------------
|
| Flow
|--------------------------------------------------------------------------
|
| 1. Validate order and permissions.
| 2. Release buyer's fiat escrow to the fiat seller.
| 3. Credit buyer's INTERNAL crypto wallet.
| 4. Mark crypto escrow as released.
| 5. Complete the order.
| 6. Broadcast the update.
|
| NOTE
|--------------------------------------------------------------------------
|
| We DO NOT transfer crypto from Quidax escrow here.
|
| Crypto remains in the Platform Quidax Escrow until the owner later
| requests a withdrawal.
|
| Order Release
|
|      Buyer Fiat Escrow
|              │
|              ▼
|      Seller Fiat Wallet
|
|
|      Platform Quidax Escrow
|              │
|              ▼
|      Buyer's Platform Wallet
|
|
| Withdrawal (Later)
|
|      Platform Quidax Escrow
|              │
|              ▼
|      Buyer's Quidax Wallet
|
*/
public function release($uid)
{
    return DB::transaction(function () use ($uid) {

        /*
        |--------------------------------------------------------------------------
        | Lock Order
        |--------------------------------------------------------------------------
        */

        $order = P2POrder::where('id', $uid)
            ->lockForUpdate()
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Only Crypto Seller Can Release
        |--------------------------------------------------------------------------
        */

        if ($order->crypto_seller_id !== auth()->id()) {
            return Response::errorResponse(
                'Only the crypto seller can release this trade.',
                null,
                403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Status
        |--------------------------------------------------------------------------
        */

        if (!in_array($order->status, ['paid', 'funded', 'accepted'], true)) {
            return Response::errorResponse(
                'Order is not ready for release.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Release Fiat Escrow
        |--------------------------------------------------------------------------
        |
        | Debit buyer escrow.
        | Credit fiat seller.
        |
        */

        $this->releaseFiatEscrow($order);

        /*
        |--------------------------------------------------------------------------
        | Credit Buyer's Platform Crypto Wallet
        |--------------------------------------------------------------------------
        |
        | Crypto is NOT sent to Quidax wallet.
        |
        | Ownership changes internally.
        | Physical crypto remains inside Platform Quidax Escrow.
        |
        */

        $buyerWallet = UserWallet::where('user_id', $order->crypto_buyer_id)
            ->where('currency_code', strtoupper($order->asset))
            ->lockForUpdate()
            ->firstOrFail();

        $buyerWallet->balance = bcadd(
            $buyerWallet->balance,
            $order->amount,
            8
        );

        $buyerWallet->save();

        /*
        |--------------------------------------------------------------------------
        | Mark Crypto Escrow Released
        |--------------------------------------------------------------------------
        */

        $cryptoEscrow = P2PEscrow::where('id', $order->crypto_escrow_id)
            ->lockForUpdate()
            ->firstOrFail();

        $cryptoEscrow->update([
            'status'      => 'released',
            'released_at' => now(),
            'notes'       => 'Ownership transferred to buyer platform wallet.'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Complete Order
        |--------------------------------------------------------------------------
        */

        $order->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
        $this->releaseCryptoEscrow($order);
        /*
        |--------------------------------------------------------------------------
        | Broadcast Event
        |--------------------------------------------------------------------------
        */

        $this->broadcastEvent(
            new \App\Events\P2POrderStatusUpdated($order->fresh())
        );

        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        return Response::successResponse(
            'Trade completed successfully.',
            [
                'order' => $order->fresh()
            ]
        );
    });
}

private function releaseFiatEscrow(P2POrder $order)
{
    $escrow = P2PEscrow::where('id', $order->fiat_escrow_id)
        ->where('status', 'held')
        ->lockForUpdate()
        ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | Escrow owner (fiat buyer)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Fiat receiver (fiat seller)
    |--------------------------------------------------------------------------
    */

    $sellerWallet = UserWallet::where('user_id', $order->fiat_seller_id)
        ->where('currency_code', $escrow->asset)
        ->lockForUpdate()
        ->firstOrFail();



    /*
    |--------------------------------------------------------------------------
    | Remove from buyer escrow
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Credit seller
    |--------------------------------------------------------------------------
    */

    $sellerWallet->balance =
        bcadd(
            $sellerWallet->balance,
            $escrow->amount,
            2
        );

    $sellerWallet->save();



    /*
    |--------------------------------------------------------------------------
    | Mark escrow released
    |--------------------------------------------------------------------------
    */

    $escrow->update([
        'status'=>'released'
    ]);
}

    private function releaseCryptoEscrow(P2POrder $order): void
{
    $escrow = P2PEscrow::where('id', $order->crypto_escrow_id)
        ->where('currency_type', 'crypto')
        ->where('status', 'held')
        ->lockForUpdate()
        ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | Crypto Buyer Quidax Account
    |--------------------------------------------------------------------------
    */


    $buyer = User::findOrFail($order->crypto_buyer_id);

$buyerWallet = $this->quidax->syncUserWallet(
    $buyer,
    $order->asset
);

    if (!$buyer->quidax_id) {
        throw new Exception(
            'Crypto buyer does not have Quidax account.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Release from Platform Escrow
    |--------------------------------------------------------------------------
    */

    $response = $this->quidax->releaseFromEscrow(
        $buyer->quidax_id,
        $escrow->amount,
        strtolower($escrow->asset),
        'P2P_ORDER_'.$order->id
    );



    if (($response['status'] ?? null) !== 'success') {

        Log::error(
            'Crypto escrow release failed',
            [
                'order_id'=>$order->id,
                'response'=>$response
            ]
        );

        throw new Exception(
            'Unable to release crypto escrow.'
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Update local escrow
    |--------------------------------------------------------------------------
    */
    $buyerWallet = UserWallet::where('user_id', $order->crypto_buyer_id)
            ->where('currency_code', strtoupper($order->asset))
            ->lockForUpdate()
            ->firstOrFail();

        // Increment buyer's balance using precise math
        $buyerWallet->balance = bcadd($buyerWallet->balance, $order->amount, 8);
        $buyerWallet->save();

    $escrow->update([
        'status'=>'released',
        'transaction_ref'=>$response['data']['id'] ?? null
    ]);
}

    

    /**
     * Raise dispute/appeal
     */
     public function appeal(Request $request, int $uid)
    {
        $validator = Validator::make($request->all(), [
            'reason'   => 'required|string|max:500',
            'evidence' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $order = P2POrder::findOrFail($uid);

        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        if (!in_array($order->status, ['paid', 'accepted', 'funded'], true)) {
            return Response::errorResponse('Order cannot be disputed in current state');
        }

        $order->update([
            'appeal_status' => 'pending',
            'appeal_reason' => $request->reason,
            'evidence'      => json_encode($request->input('evidence', [])),
        ]);

        $this->broadcastEvent(new \App\Events\P2POrderStatusUpdated($order));

        return Response::successResponse(
            'Dispute raised successfully. Admin will review.',
            ['order' => $order]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | chat()
    |--------------------------------------------------------------------------
    | Fix: uid cast to int.
    */
    public function chat(int $uid)
    {
        $order = P2POrder::findOrFail($uid);

        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        $messages = P2PChat::where('order_id', $order->id)
            ->with('sender:id,firstname,lastname,username')
            ->orderBy('created_at', 'asc')
            ->get();

        return Response::successResponse('Chat messages', ['messages' => $messages]);
    }

    /*
    |--------------------------------------------------------------------------
    | sendMessage()
    |--------------------------------------------------------------------------
    | Fix: uid cast to int.
    | Fix: block messages on closed orders.
    | Note: add ->middleware('throttle:60,1') on the route to rate-limit.
    */
    public function sendMessage(Request $request, int $uid)
    {
        $validator = Validator::make($request->all(), [
            'message'    => 'required|string|max:1000',
            'attachment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $order = P2POrder::findOrFail($uid);

        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        if (!in_array($order->status, ['accepted', 'paid', 'funded'], true)) {
            return Response::errorResponse('Cannot send messages on a closed order');
        }

        $message = P2PChat::create([
            'order_id'   => $order->id,
            'sender_id'  => auth()->id(),
            'message'    => $request->message,
            'attachment' => $request->attachment,
        ]);

        $this->broadcastEvent(new \App\Events\P2PMessageSent($message));

        return Response::successResponse('Message sent', ['message' => $message], 201);
    }

    /**
     * Get single order details
     */
    public function show($uid)
    {
        $order = P2POrder::where('id', $uid)
            ->with(['ad', 'maker:id,firstname,lastname,username', 'taker:id,firstname,lastname,username'])
            ->firstOrFail();

        // Check permission
        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        // Attach payment methods
        // If I am observing as Taker (Buyer), I need Maker's (Seller's) payment methods.
        // If I am observing as Maker (Seller), I see my own methods (or maybe I want to see which one Taker chose? P2P usually shows One).
        // Since we didn't store specific method, we show all valid ones from Ad.
        $order->seller_payment_methods = $order->ad->paymentMethods();

        return Response::successResponse('Order details', ['order' => $order]);
    }

    /**
     * Get user's orders
     */
    public function myOrders()
    {
        $orders = P2POrder::where(function ($q) {
                $q->where('maker_id', auth()->id())
                  ->orWhere('taker_id', auth()->id());
            })
            ->with(['ad', 'maker:id,firstname,lastname,username', 'taker:id,firstname,lastname,username'])
            ->latest()
            ->get();

        return Response::successResponse('Your orders', ['orders' => $orders]);
    }

     private function broadcastEvent(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('P2P broadcast failed', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

 
}
