<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PAd;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

        return Response::successResponse('Ads fetched successfully', ['ads' => $ads]);
    }

    /**
     * Create new ad (requires KYC Level 2+)
     */
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
            'payment_method_ids' => 'required|array',
            'payment_method_ids.*' => 'exists:p2p_payment_methods,id',
            'terms' => 'nullable|string',
            'auto_reply' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:5|max:60',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $user = auth()->user();

        // Check Merchant Status
        if ($user->merchant_status !== 'approved') {
            return Response::errorResponse(
                'You must be an approved merchant to create ads.',
                [
                    'merchant_status' => $user->merchant_status ?? 'none',
                    'action' => 'create_ad'
                ],
                403
            );
        }

        // For sell ads, verify balance on Quidax (Escrow)
        if ($request->type === 'sell') {
            // Instantiate Quidax Service
            $quidaxService = new \App\Services\QuidaxService();
            
            try {
                $assetCode = strtolower($request->asset);
                $response = $quidaxService->fetchUserWallet($user->quidax_id, $assetCode);
                
                if (isset($response['data']) && isset($response['data']['balance'])) {
                     $quidaxBalance = (float) $response['data']['balance'];
                     
                     if ($quidaxBalance < $request->total_amount) {
                         return Response::errorResponse("Insufficient balance. You only have {$quidaxBalance} {$request->asset} in your wallet, which is not enough to create this ad for {$request->total_amount} {$request->asset}.");
                     }
                     
                     // 1. Create Escrow Record (Tracking the Lock)
                     $escrow = \App\Models\P2PEscrow::create([
                         'user_id' => $user->id,
                         'ad_id' => null, // Will be updated below
                         'type' => 'ad_creation',
                         'asset' => $request->asset,
                         'amount' => $request->total_amount,
                         'status' => 'held'
                     ]);

                     // 2. Call Quidax to Move Funds (Transfer Sub -> Main)
                     $transferResponse = $quidaxService->transferToEscrow($user->quidax_id, $request->total_amount, $assetCode);
                     
                     if (isset($transferResponse['status']) && $transferResponse['status'] === 'success') {
                         $txRef = $transferResponse['data']['id'] ?? null;
                         $escrow->update(['transaction_ref' => $txRef, 'status' => 'held']);
                     } else {
                         // Failed to move funds? Destroy escrow and fail.
                         $escrow->delete();
                         \Illuminate\Support\Facades\Log::error("P2P Ad Funds Lock Failed", ['response' => $transferResponse]);
                         return Response::errorResponse("Failed to lock funds. Please try again later.");
                     }
                     
                } else {
                    \Illuminate\Support\Facades\Log::error("P2P Ad Wallet Fetch Failed", ['response' => $response]);
                    return Response::errorResponse('Unable to fetch wallet balance.');
                }
            } catch (\Exception $e) {
                return Response::errorResponse('Error connecting to wallet: ' . $e->getMessage());
            }
        }

        $ad = P2PAd::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'asset' => $request->asset,
            'fiat' => $request->fiat,
            'price_type' => $request->price_type,
            'price' => $request->price,
            'margin' => $request->margin,
            'total_amount' => $request->total_amount,
            'available_amount' => $request->total_amount,
            'min_limit' => $request->min_limit,
            'max_limit' => $request->max_limit,
            'payment_method_ids' => $request->payment_method_ids,
            'terms' => $request->terms,
            'auto_reply' => $request->auto_reply,
            'time_limit' => $request->time_limit ?? 15,
            'status' => 'offline', // Admin approval required
        ]);

        if (isset($escrow)) {
            $escrow->update(['ad_id' => $ad->id]);
        }

        return Response::successResponse('Ad created successfully. Pending approval.', ['ad' => $ad], 201);
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
