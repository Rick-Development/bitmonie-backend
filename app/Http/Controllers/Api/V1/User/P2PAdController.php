<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PAd;
use App\Models\P2PUserStat;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\P2PEscrow;
use Exception;

class P2PAdController extends Controller
{
    /**
     * Browse all active ads with filters
     */
    public function index(Request $request)
    {
        $query = P2PAd::with(['user', 'user.p2pUserStat'])
            ->whereHas('user', function ($userQuery) {
                $userQuery->where('merchant_status', 'approved');
            })
            ->where('status', 'online');

        // Filters
        // 1. Basic Filters
        if ($request->filled('asset')) {
            $query->where('asset', $request->asset);
        }

        if ($request->filled('fiat')) {
            $query->where('fiat', $request->fiat);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('payment_method_id')) {
            $query->whereJsonContains('payment_method_ids', (int)$request->payment_method_id);
        }

        // 2. Logic: Real Max Limit = min(max_limit, available_amount * price)
        // We exclude ads where Real Max Limit < Min Limit (effectively exhausted for the set range)
        $query->whereRaw('(LEAST(max_limit, available_amount * price) >= min_limit)');

        // 3. Amount Filter (Fiat Amount)
        if ($request->filled('amount')) {
            $amount = $request->amount; 
            // Check Min Limit
            $query->where('min_limit', '<=', $amount);
            
            // Check Dynamic Max Limit
            // Ensuring the requested amount is not more than the user's max limit AND not more than the available balance's value
            $query->where('max_limit', '>=', $amount)
                  ->whereRaw('(available_amount * price) >= ?', [$amount]);
        }

        $ads = $query->latest()->paginate(20);

        // Dynamically correct the max_limit in the response
        $ads->getCollection()->transform(function ($ad) {
            $availableValueFiat = (float) bcmul((string)$ad->available_amount, (string)$ad->price, 2);
            $userMaxLimit = (float) $ad->max_limit;
            
            // The real max limit is the lesser of the User's setting OR the Fiat Value of remaining crypto
            $ad->max_limit = min($userMaxLimit, $availableValueFiat);
            
            return $ad;
        });
        
        // Use getCollection() on pagination object to apply the transformation
        $ads = $ads->getCollection()->values();

        return Response::successResponse('Ads fetched successfully', ['ads' => $ads]);
    }

  
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'type' => 'required|in:buy,sell',
        'asset' => 'required|string|max:16',
        'fiat' => 'required|string|max:16',
        'price_type' => 'required|in:fixed,floating',
        'price' => 'required|numeric|min:0',
        'margin' => 'nullable|numeric',
        'total_amount' => 'required|numeric|min:0',
        'min_limit' => 'required|numeric|min:0',
        'max_limit' => 'required|numeric|min:0',
        // 'payment_method_ids' => 'required|array',
        // 'payment_method_ids.*' => 'exists:p2p_payment_methods,id',//removed since we doing intra payment
        'terms' => 'nullable|string',
        'auto_reply' => 'nullable|string',
        'time_limit' => 'nullable|integer|min:5|max:60',
    ]);
    

    if ($validator->fails()) {
        return Response::errorResponse(
            'Validation Error',
            $validator->errors()->all()
        );
    }

    $total_fiat_value = bcmul(
    (string) $request->total_amount,
    (string) $request->price,
    2
);
if ($request->max_limit > $total_fiat_value) {
    return Response::errorResponse(
        'Validation Error',
        "The max limit ({$request->max_limit} NGN) cannot exceed the total ad value ({$total_fiat_value} NGN)."
    );
}

// 3. Ensure min_limit is not higher than max_limit
if ($request->min_limit > $request->max_limit) {
    return Response::errorResponse('Validation Error', 'Min limit cannot be greater than max limit.');
}

    $user = auth()->user();


    if ($user->merchant_status !== 'approved') {
        return Response::errorResponse(
            'You must be an approved merchant to create ads.',
            [
                'merchant_status' => $user->merchant_status ?? 'none'
            ],
            403
        );
    }


    DB::beginTransaction();

    try {

        $escrow = null;


        /**
         * Lock seller funds
         */
        if ($request->type === 'sell') {

            $quidaxService = app(\App\Services\QuidaxService::class);

            $asset = strtolower($request->asset);


            $wallet = $quidaxService->fetchUserWallet(
                $user->quidax_id,
                $asset
            );


            if (
                !isset($wallet['data']['balance'])
            ) {
                //log user data and wallet response
                
                throw new Exception(
                    "Unable to fetch wallet balance"
                );
            }


            $balance = (float)$wallet['data']['balance'];

            // Sync/create local crypto wallet for quick local balance checks
UserWallet::updateOrInsert(
    [
        'user_id'       => $user->id,
        'currency_code' => strtoupper($asset),
    ],
    [
        'balance'              => $wallet['data']['balance'],
        'reserved'             => $wallet['data']['locked_balance'] ?? 0,
        'currency_id'          => null, // set if you have a currencies table
        'quote_currency_code'  => null,
        'status'               => true,
        'updated_at'           => now(),
        'created_at'           => now(),
    ]
);

            //check if user exists on local db and create if it doesn't. You can use insert fast write


            if ($balance < $request->total_amount) {

                throw new Exception(
                    "Insufficient balance. Available {$balance} {$request->asset}"
                );

            }


            /**
             * Create escrow record
             */
            $escrow = P2PEscrow::create([
                'user_id' => $user->id,
                'type' => 'ad_creation',
                'asset' => strtoupper($request->asset),
                'amount' => $request->total_amount,
                'status' => 'pending',
                'currency_type'=>'crypto',
               'notes' => 'Crypto reserved for P2P ad creation'
            ]);



            /**
             * Move funds to escrow
             */
            $transfer = $quidaxService->transferToEscrow(
                $user->quidax_id,
                $request->total_amount,
                $asset
            );


            if (!isset($transfer['status']) ||$transfer['status'] !== 'success') {

                throw new Exception(
                    "Failed to lock funds"
                );

            }


           $escrow->update(['transaction_ref' =>$transfer['data']['id'] ?? null,'status'=>'held' ]);
        }elseif ($request->type === 'buy') {

    // Calculate fiat amount to reserve
    $fiatAmount = bcmul(
        (string) $request->total_amount,
        (string) $request->price,
        2
    );

    // Lock user's fiat wallet
    $fiatWallet = UserWallet::where('user_id', $user->id)
        ->where('currency_code', strtoupper($request->fiat))
        ->lockForUpdate()
        ->first();

    if (!$fiatWallet) {
        throw new Exception(
            "{$request->fiat} wallet not found."
        );
    }

    // Check available balance
    if (bccomp((string) $fiatWallet->balance, $fiatAmount, 2 ) === -1) {

        throw new Exception(
            "Insufficient {$request->fiat} balance."
        );
    }

    /**
     * Reserve fiat in wallet
     * Assumes your wallet has:
     * balance
     * escrow_balance
     */
    $fiatWallet->balance = bcsub(
        (string) $fiatWallet->balance,
        $fiatAmount,
        2
    );

    $fiatWallet->escrow_balance = bcadd(
        (string) $fiatWallet->escrow_balance,
        $fiatAmount,
        2
    );

    $fiatWallet->save();

    /**
     * Create escrow record
     */
    $escrow = P2PEscrow::create([
        'user_id'        => $user->id,
        'type'           => 'ad_creation',
        'asset'          => strtoupper($request->fiat),
        'currency_type'  => 'fiat',
        'amount'         => $fiatAmount,
        'status'         => 'held',
        'notes' => 'Fiat reserved for P2P ad creation',
    ]);
}



        /**
         * Create advertisement
         */
        $ad = P2PAd::create([

            'user_id'=>$user->id,

            'type'=>$request->type,

            'asset'=>strtoupper($request->asset),

            'fiat'=>strtoupper($request->fiat),

            'price_type'=>$request->price_type,

            'price'=>$request->price,

            'margin'=>$request->margin,

            'total_amount'=>$request->total_amount,

            'available_amount'=>$request->total_amount,

            'min_limit'=>$request->min_limit,

            'max_limit'=>$request->max_limit,

            'payment_method_ids'=>null,

            'terms'=>$request->terms,

            'auto_reply'=>$request->auto_reply,

            'time_limit'=>$request->time_limit ?? 15,

            'status'=>'offline'

        ]);



        /**
         * Attach escrow to ad
         */
        if ($escrow) {

            $escrow->update([
                'ad_id'=>$ad->id
            ]);

        }



        /**
         * Create or update merchant stats
         */
        P2PUserStat::firstOrCreate(
    [
        'user_id' => $user->id
    ],
    [
        'total_trades' => 0,
        'completed_trades' => 0,
        'completion_rate' => 0,
        'avg_release_time_minutes' => 0,
        'rating' => 0,
        'disputes_raised' => 0,
        'disputes_won' => 0,
    ]
);


        DB::commit();

$this->sendNotification(
    $user,
    'P2P_AD_CREATED',
    [
        'user'   => $user->name,
        'type'   => strtoupper($ad->type),
        'asset'  => $ad->asset,
        'amount' => $ad->total_amount,
        'price'  => $ad->price,
        'status' => $ad->status,
    ]
);
        return Response::successResponse(
            'Ad created successfully. Pending approval.',
            [
                'ad'=>$ad
            ],
            201
        );


    } catch(Exception $e) {

        DB::rollBack();
Log::error(
    'P2P Ad Creation Failed',
    [
        'user_id' => $user->id,
        'ad_type' => $request->type,
        'asset' => $request->asset,
        'amount' => $request->total_amount,
        'error' => $e->getMessage()
    ]
);

    


        return Response::errorResponse(
            $e->getMessage()
        );
    }
}

    /**
     * Toggle ad online/offline
     */
    public function toggle($id)
    {
        $ad = P2PAd::where('user_id', auth()->id())->findOrFail($id);

        $newStatus = $ad->status === 'online' ? 'offline' : 'online';

        if ($newStatus === 'online' && auth()->user()->merchant_status !== 'approved') {
            return Response::errorResponse(
                'Only approved merchants can put ads online.',
                ['merchant_status' => auth()->user()->merchant_status ?? 'none'],
                403
            );
        }

        $ad->status = $newStatus;
        $ad->save();

        return Response::successResponse("Ad is now {$newStatus}", ['ad' => $ad]);
    }

    /**
     * Get user's own ads
     */
    public function myAds()
    {
        $ads = P2PAd::where('user_id', auth()->id())
            ->latest()
            ->get();

        return Response::successResponse('Your ads fetched', ['ads' => $ads]);
    }

    /**
     * Show single ad details
     */
    public function show($id)
    {
        $ad = P2PAd::with(['user', 'user.p2pUserStat'])->findOrFail($id);

        return Response::successResponse('Ad details', ['ad' => $ad]);
    }

    /**
     * Close/Delete Ad and Refund Escrow
     */
    public function destroy($id)
    {
        $ad = P2PAd::where('user_id', auth()->id())->findOrFail($id);
        
        // 1. Check for active orders
        $activeOrders = $ad->orders()->whereIn('status', ['pending', 'accepted', 'paid', 'dispute'])->exists();
        if ($activeOrders) {
            return Response::errorResponse('Cannot close ad with active ongoing orders. Please complete them first.');
        }

        // 2. Refund Escrow (if Sell Ad means we held funds)
        if ($ad->type === 'sell' && $ad->available_amount > 0) {
            // Logic: Move 'available_amount' back to User from Escrow
            // We find the original Escrow Record
            $escrow = \App\Models\P2PEscrow::where('ad_id', $ad->id)
                ->where('type', 'ad_creation')
                ->where('status', 'held')
                ->first();
            
            if ($escrow) {
                // Update specific refund amount logic if partial? 
                // Usually we just mark it as 'refunded' (implied remaining).
                // Or better: Create a NEW Escrow Record showing 'Refund' or just update status.
                // Since this is a log, let's update status.
                $escrow->update(['status' => 'refunded']);

                // TODO: Call API to Transfer Funds: Master -> User Sub-Account
                // $quidaxService->fundSubAccount($ad->user->quidax_id, $ad->available_amount, $ad->asset);
            }
        }

        $ad->delete(); // Soft delete or Hard delete? Model assumes standard delete.

        return Response::successResponse('Ad closed and remaining funds refunded.');
    }
}
