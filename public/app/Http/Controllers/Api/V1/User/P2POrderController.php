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

class P2POrderController extends Controller
{
    /**
     * Create order from ad
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad_id' => 'required|exists:p2p_ads,id',
            'amount' => 'nullable|numeric|min:0', // Crypto Amount
            'fiat_amount' => 'nullable|numeric|min:0', // Fiat Amount
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        if (!$request->amount && !$request->fiat_amount) {
            return Response::errorResponse('Please provide either amount (crypto) or fiat_amount');
        }

        return DB::transaction(function () use ($request) {
            $ad = P2PAd::where('id', $request->ad_id)
                ->whereHas('user', function ($query) {
                    $query->where('merchant_status', 'approved');
                })
                ->where('status', 'online')
                ->lockForUpdate()
                ->firstOrFail();

            // Cannot trade with self
            if ($ad->user_id === auth()->id()) {
                return Response::errorResponse('Cannot trade with your own ad');
            }

            // Calculate Amounts
            // PER USER REQUEST: The mobile app sends 'amount' but implies FIAT value (e.g., 22050 NGN).
            // So we treat 'amount' as Fiat Amount by default.
            
            if ($request->filled('fiat_amount')) {
                $fiatAmount = $request->fiat_amount;
            } elseif ($request->filled('amount')) {
                $fiatAmount = $request->amount;
            } else {
                 return Response::errorResponse('Amount is required');
            }

            // Always calculate Crypto from Fiat (since input is always Fiat now)
            // Crypto = Fiat / Price
            $cryptoAmount = bcdiv((string)$fiatAmount, (string)$ad->price, 8); 
            
            // Ensure $fiatAmount is standardized format
            $fiatAmount = number_format((float)$fiatAmount, 2, '.', '');

            // 1. Calculate Effective Limits
            $availableValueFiat = bcmul((string)$ad->available_amount, (string)$ad->price, 2);
            $effectiveMaxLimit = min((float)$ad->max_limit, (float)$availableValueFiat);

            // Cast for safe numeric comparison
            $fiatAmountFloat = (float) $fiatAmount;
            $minLimitFloat = (float) $ad->min_limit;
            $maxLimitFloat = (float) $ad->max_limit;
            $effectiveMaxFloat = (float) $effectiveMaxLimit;

            // 2. Validate Fiat Amount against Effective Limits
            if ($fiatAmountFloat < $minLimitFloat || $fiatAmountFloat > $effectiveMaxFloat) {
                // If the reason is the available balance being low, give a specific message
                if ($fiatAmountFloat > $effectiveMaxFloat && $effectiveMaxFloat < $maxLimitFloat) {
                     return Response::errorResponse("Order amount exceeds the current available limit of {$effectiveMaxFloat} {$ad->fiat} (due to remaining balance).");
                }
                return Response::errorResponse("Order amount must be between {$minLimitFloat} and {$effectiveMaxFloat} {$ad->fiat}");
            }

            // 3. Double Check Crypto Availability (redundant but safe)
            // Ensure we use float comparison here too or BCMath comparision
            if (bccomp((string)$ad->available_amount, (string)$cryptoAmount, 8) === -1) {
                 return Response::errorResponse("Insufficient ad availability.");
            }

            // Lock funds in escrow (for sell ads, buyer pays fiat, seller already locked crypto)
            if ($ad->type === 'buy') {
                // Maker is BUYING (Ad type BUY). Taker (User) is SELLING crypto.
                // We must lock Taker's crypto on Quidax.
                
                $quidaxService = new \App\Services\QuidaxService();
                $taker = auth()->user();
                
                try {
                    // Quidax requires lowercase currency codes
                    $assetCode = strtolower($ad->asset);
                    $response = $quidaxService->fetchUserWallet($taker->quidax_id, $assetCode);
                    if (isset($response['data']) && isset($response['data']['balance'])) {
                        $quidaxBalance = (float) $response['data']['balance'];
                        
                        if ($quidaxBalance < $cryptoAmount) {
                             return Response::errorResponse("Transaction Failed: The selling party does not have sufficient crypto balance to fulfill this order.");
                        }
                        
                        // 1. Create Escrow Record
                         $escrow = \App\Models\P2PEscrow::create([
                             'user_id' => $taker->id,
                             // 'order_id' => ... we don't have it yet. Will update after creation.
                             'type' => 'order_locking',
                             'asset' => $ad->asset,
                             'amount' => $cryptoAmount,
                             'status' => 'held'
                         ]);

                        // 2. Call Quidax to Move Funds (Transfer Taker Quidax -> Main Escrow)
                        $transferResponse = $quidaxService->transferToEscrow($taker->quidax_id, $cryptoAmount, $assetCode);

                         if (isset($transferResponse['status']) && $transferResponse['status'] === 'success') {
                             $txRef = $transferResponse['data']['id'] ?? null;
                             $escrow->update(['transaction_ref' => $txRef, 'status' => 'held']);
                         } else {
                             $escrow->delete();
                             return Response::errorResponse("Failed to lock funds. Please try again later.");
                         }
                        
                    } else {
                        \Illuminate\Support\Facades\Log::error("P2P Order Wallet Fetch Failed", ['response' => $response, 'user_id' => $taker->id, 'asset' => $ad->asset]);
                        return Response::errorResponse('Unable to fetch wallet balance.');
                    }
                } catch (\Exception $e) {
                     return Response::errorResponse('Error connecting to wallet: ' . $e->getMessage());
                }
            }

            // Reduce ad availability
            $ad->available_amount -= $cryptoAmount;
            $ad->save();

            // Create order
            $order = P2POrder::create([
                'ad_id' => $ad->id,
                'maker_id' => $ad->user_id,
                'taker_id' => auth()->id(),
                'type' => $ad->type,
                'asset' => $ad->asset,
                'quote_currency' => $ad->fiat,
                'amount' => $cryptoAmount,     // Store Crypto Amount
                'price' => $ad->price,
                'locked_price' => $ad->price,
                'total' => $fiatAmount,        // Store Fiat Total
                'escrow_enabled' => true,
                'status' => 'accepted',
                'payment_deadline' => Carbon::now()->addMinutes($ad->time_limit),
            ]);

            $order->seller_payment_methods = $ad->paymentMethods();

            // Link Escrow to Order (if applicable)
            if (isset($escrow)) {
                $escrow->update(['order_id' => $order->id]);
            }

            return Response::successResponse('Order created successfully', ['order' => $order], 201);
        });
    }

    /**
     * Buyer marks payment sent
     */
    public function markPaid(Request $request, $uid)
    {
        $validator = Validator::make($request->all(), [
            'payment_proof' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $order = P2POrder::where('id', $uid)
            ->where('taker_id', auth()->id())
            ->firstOrFail();

        if ($order->status !== 'accepted') {
            return Response::errorResponse('Invalid order status');
        }

        $imagePath = null;
        if ($request->hasFile('payment_proof')) {
            $imagePath = $request->file('payment_proof')->store('p2p_proofs', 'public');
        }

        $order->payment_proof = $imagePath;
        $order->status = 'paid';
        $order->save();

        try {
            event(new \App\Events\P2POrderStatusUpdated($order));
        } catch (\Exception $e) {
            // Log error but continue (Pusher might be down or not configured)
            //\Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return Response::successResponse('Payment marked as sent. Waiting for seller confirmation.', ['order' => $order]);
    }

    /**
     * Seller releases crypto
     */
    public function release($uid)
    {
        return DB::transaction(function () use ($uid) {
            $order = P2POrder::where('id', $uid)->lockForUpdate()->firstOrFail();

            // Determine role: Only Seller can release crypto
            $isSeller = ($order->type === 'sell' && $order->maker_id === auth()->id()) ||
                        ($order->type === 'buy' && $order->taker_id === auth()->id());

            if (!$isSeller) {
                return Response::errorResponse('Unauthorized. Only seller can release.', null, 403);
            }

            if ($order->status !== 'paid' && $order->status !== 'funded') {
                return Response::errorResponse('Order not ready for release');
            }

            // Determine wallets based on order type
            if ($order->type === 'sell') {
                // Maker selling crypto, release from maker's reserved to taker
                $fromWallet = UserWallet::where('user_id', $order->maker_id)
                    ->where('currency_code', $order->asset)
                    ->firstOrFail();
                $toWallet = UserWallet::where('user_id', $order->taker_id)
                    ->where('currency_code', $order->asset)
                    ->firstOrFail();
                $amount = (string)$order->amount;
            } else {
                // Maker buying crypto, release from taker's reserved to maker
                $fromWallet = UserWallet::where('user_id', $order->taker_id)
                    ->where('currency_code', $order->asset)
                    ->firstOrFail();
                $toWallet = UserWallet::where('user_id', $order->maker_id)
                    ->where('currency_code', $order->asset)
                    ->firstOrFail();
                $amount = (string)$order->amount;
            }

            WalletService::releaseReservedTo($fromWallet->id, $toWallet->id, $amount, 'p2p:release:' . $order->id, ['order_id' => $order->id]);

            $order->status = 'completed';
            $order->save();

            try {
                event(new \App\Events\P2POrderStatusUpdated($order));
            } catch (\Exception $e) {
                //\Log::error("Pusher Broadcast Error: " . $e->getMessage());
            }

            return Response::successResponse('Crypto released successfully', ['order' => $order]);
        });
    }

    /**
     * Raise dispute/appeal
     */
    public function appeal(Request $request, $uid)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'evidence' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $order = P2POrder::findOrFail($uid);

        // Check if user is part of trade
        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        if (!in_array($order->status, ['paid', 'accepted', 'funded'])) {
            return Response::errorResponse('Order cannot be disputed in current state');
        }

        $order->appeal_status = 'pending';
        $order->appeal_reason = $request->reason;
        $order->evidence = $request->evidence ?? [];
        $order->save();

        try {
            event(new \App\Events\P2POrderStatusUpdated($order));
        } catch (\Exception $e) {
            //\Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

        return Response::successResponse('Dispute raised successfully. Admin will review.', ['order' => $order]);
    }

    /**
     * Get order chat messages
     */
    public function chat($uid)
    {
        $order = P2POrder::findOrFail($uid);

        // Check if user is part of trade
        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        $messages = P2PChat::where('order_id', $order->id)
            ->with('sender:id,firstname,lastname,username')
            ->orderBy('created_at', 'asc')
            ->get();

        return Response::successResponse('Chat messages', ['messages' => $messages]);
    }

    /**
     * Send chat message
     */
    public function sendMessage(Request $request, $uid)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'attachment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation Error', $validator->errors()->all());
        }

        $order = P2POrder::findOrFail($uid);

        // Check if user is part of trade
        if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
            return Response::errorResponse('Unauthorized', null, 403);
        }

        $message = P2PChat::create([
            'order_id' => $order->id,
            'sender_id' => auth()->id(),
            'message' => $request->message,
            'attachment' => $request->attachment,
        ]);

        try {
            event(new \App\Events\P2PMessageSent($message));
        } catch (\Exception $e) {
            //\Log::error("Pusher Broadcast Error: " . $e->getMessage());
        }

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
}
