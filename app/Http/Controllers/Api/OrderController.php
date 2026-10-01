<?php

namespace App\Http\Controllers\Api;

use App\Http\Helpers\Response;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\P2POrder;
use App\Models\P2PAd;
use App\Models\OrderTransaction;
use App\Models\P2PTraders;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $q = P2POrder::with('maker');

        if ($r->filled('type')) {
            $q->where('type', $r->type);
        }

        if ($r->filled('quote_currency')) {
            $q->where('quote_currency', strtoupper($r->quote_currency));
        }

        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        $orders = $q->orderByDesc('created_at')->get();

        $orders->transform(function ($order) {
            return [
                'id' => $order->id,
                'type' => $order->type,
                'asset' => $order->asset,
                'quote_currency' => $order->quote_currency,
                'amount' => $order->amount,
                'price' => $order->price,
                'total' => $order->total,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'maker' => [
                    'id' => $order->maker->id,
                    'firstname' => $order->maker->firstname,
                    'lastname' => $order->maker->lastname,
                    'username' => $order->maker->username
                ],
            ];
        });

        return Response::success('Orders fetched successfully', $orders, 200);
    }

    public function fetch_traders(Request $request)
    {
        return Response::success('Traders fetched', P2PTraders::get(), 200);
    }

    public function create_trader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trader_name' => 'required',
            'trader_email' => 'required|email',
            'type' => 'required|in:buy,sell',
            'supported_currencies' => 'required|array',
            'amount' => 'required|numeric|min:0.00000001',
            'price' => 'required|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['trader_name', 'trader_email', 'supported_currencies', 'type', 'amount', 'price']);
        $get_trader = P2PTraders::where('trader_email', $data['trader_email'])->first();

        if ($get_trader) {
            return Response::error('Trader with this email already exists', $get_trader, 400);
        }

        $new_trader = P2PTraders::create([
            'trader_name' => $data['trader_name'],
            'trader_email' => $data['trader_email'],
            'supported_currencies' => $data['supported_currencies'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'price' => $data['price'],
            'total' => bcmul($data['amount'], $data['price'], 18)
        ]);

        return Response::success('Trader created successfully', $new_trader, 201);
    }

    public function store(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'type' => 'required|in:buy,sell',
            'quote_currency' => 'required|string|max:16',
            'amount' => 'required|numeric|min:0.00000001',
            'price' => 'required|numeric|min:0.00000001',
            'escrow_enabled' => 'boolean',
            'p2p_ad_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $payload = array_merge($r->only(['type', 'quote_currency', 'amount', 'price', 'escrow_enabled', 'p2p_ad_id']), ['maker_id' => $user->id]);
        $order = OrderService::createOrder($payload);

        return Response::success('Order created successfully', [
            'order' => $order,
            'user' => [
                'user_id' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'username' => $user->username
            ]
        ], 201);
    }

    public function show($id)
    {
        return Response::success('Order details fetched', P2POrder::findOrFail($id), 200);
    }

    //accept will be automatic
    // public function accept(Request $r, $id)
    // {
      
    //     try {
    //         return DB::transaction(function () use ($id) {
    //             $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
    //             $userId = auth()->id();

    //             if ($order->status === 'accepted' && $order->taker_id === $userId) {
    //                 return Response::success('Order already accepted by you.', $order, 200);
    //             }

    //             if ($order->status !== 'open') {
    //                 return Response::error('This order is no longer open for execution.', null, 400);
    //             }

    //             if ($order->maker_id === $userId) {
    //                 return Response::error('You cannot accept an order listing created by yourself.', null, 400);
    //             }

    //             // SECURE FIXED: Check parent ad validation & inventory adjustments
    //             if ($order->p2p_ad_id) {
    //                 $ad = P2PAd::where('id', $order->p2p_ad_id)->lockForUpdate()->firstOrFail();
                    
    //                 if (bccomp((string)$ad->available_amount, (string)$order->amount, 18) < 0) {
    //                     $order->update(['status' => 'cancelled', 'meta' => ['cancel_reason' => 'Ad inventory exhausted']]);
    //                     return Response::error('The merchant no longer has enough available liquidity.', null, 400);
    //                 }
                    
    //                 $ad->available_amount = bcsub((string)$ad->available_amount, (string)$order->amount, 18);
    //                 $ad->save();
    //             }

    //             $order->taker_id = $userId;
    //             $order->status = 'accepted';
    //             $order->payment_expires_at = now()->addMinutes(15); 
    //             $order->save();

    //             return Response::success('Order accepted successfully. Please proceed to fulfillment.', $order, 200);
    //         });
    //     } catch (\Exception $e) {
    //         Log::error('P2P Order Acceptance Crash', ['order_id' => $id, 'user_id' => auth()->id(), 'error' => $e->getMessage()]);
    //         return Response::error('An internal infrastructure failure occurred.', null, 500);
    //     }
    // }
public function fund(Request $r, $id)
    {
        $idKey = $r->header('Idempotency-Key');
        $idempotencyRef = $idKey ? 'p2p:fund:key:' . $idKey : 'p2p:fund:ord:' . $id;

        return DB::transaction(function () use ($id, $idempotencyRef) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            $userId = auth()->id();

            // Ledger Lookback
            if (OrderTransaction::where('reference', $idempotencyRef)->exists()) {
                return response()->json($order, 200);
            }

            if ($order->status !== 'accepted') {
                return response()->json(['error' => 'Order is not in an acceptable state for funding.'], 400);
            }

            $expectedPayerId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;
            if ($userId !== $expectedPayerId) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $payerWallet = OrderService::resolvePayerWalletForFunding($order, $userId);
            $debitAmount = ($order->type === 'sell') ? (string)$order->total : (string)$order->amount;

            WalletService::debitToReserve($payerWallet->id, $debitAmount, $idempotencyRef, ['order_id' => $order->id]);

            $order->status = 'funded';
            $order->save();

            return response()->json($order, 200);
        });
    }
public function release(Request $r, $id)
{
    // 1. Validate Input
    $idKey = $r->header('Idempotency-Key');
    if ($idKey && strlen($idKey) > 64) {
        return response()->json(['error' => 'Idempotency key too long'], 422);
    }

    $idempotencyRef = $idKey ? 'p2p:release:key:' . $idKey : 'p2p:release:ord:' . $id;

    return DB::transaction(function () use ($id, $idempotencyRef) {
        // 2. Pessimistic Locking
        $order = P2POrder::where('id', $id)->lockForUpdate()->first();
        
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // 3. Idempotency Check (Must be inside transaction)
        if (OrderTransaction::where('reference', $idempotencyRef)->exists()) {
            return response()->json($order, 200);
        }

        // 4. Business Logic Guard Clauses
        if ($order->status !== 'funded') {
            return response()->json(['error' => 'Invalid order state for release.'], 409); // 409 Conflict
        }

        // 5. Explicit Authorization
        $userId = auth()->id();
        $authorizedId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;
        
        if ((int)$userId !== (int)$authorizedId) {
            Log::warning("Unauthorized release attempt", ['user_id' => $userId, 'order_id' => $id]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // 6. Safe Wallet Resolution
        try {
            if ($order->type === 'sell') {
                $fromWallet = UserWallet::where('user_id', $order->taker_id)->where('currency', $order->quote_currency)->firstOrFail();
                $toWallet = UserWallet::where('user_id', $order->maker_id)->where('currency', $order->quote_currency)->firstOrFail();
                $amount = $order->total;
            } else {
                $fromWallet = UserWallet::where('user_id', $order->maker_id)->where('currency', $order->asset)->firstOrFail();
                $toWallet = UserWallet::where('user_id', $order->taker_id)->where('currency', $order->asset)->firstOrFail();
                $amount = $order->amount;
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Required wallet not found'], 422);
        }

        // 7. Atomic Service Execution
        // Ensure WalletService throws an Exception on failure to trigger DB rollback
        WalletService::releaseReservedTo($fromWallet->id, $toWallet->id, (string)$amount, $idempotencyRef, ['order_id' => $order->id]);

        $order->status = 'released';
        $order->released_at = now(); // Always track timestamps
        $order->save();

        return response()->json($order, 200);
    });
}
    // public function release(Request $r, $id)
    // {
    //     $idKey = $r->header('Idempotency-Key');
    //     $idempotencyRef = $idKey ? 'p2p:release:key:' . $idKey : 'p2p:release:ord:' . $id;

    //     return DB::transaction(function () use ($id, $idempotencyRef) {
    //         $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
    //         $userId = auth()->id();

    //         // Ledger Lookback
    //         if (OrderTransaction::where('reference', $idempotencyRef)->exists()) {
    //             return response()->json($order, 200);
    //         }

    //         if ($order->status !== 'funded') {
    //             return response()->json(['error' => 'Funds can only be released from a funded trade state.'], 400);
    //         }

    //         $authorizedSellerId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;
    //         if ($userId !== $authorizedSellerId) {
    //             return response()->json(['error' => 'Access Denied.'], 403);
    //         }

    //         // ... wallet resolution logic ...
    //         WalletService::releaseReservedTo($fromWallet->id, $toWallet->id, $amount, $idempotencyRef, ['order_id' => $order->id]);

    //         $order->status = 'released';
    //         $order->save();

    //         return response()->json($order, 200);
    //     });
    // }
    

    public function cancel(Request $r, $id)
    {
        $idKey = $r->header('Idempotency-Key');
        $idempotencyRef = $idKey ? 'p2p:cancel:key:' . $idKey : 'p2p:cancel:ord:' . $id;

        return DB::transaction(function () use ($id, $idempotencyRef) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            $userId = auth()->id();

            // 1. Check if already cancelled via state OR ledger
            if ($order->status === 'cancelled' || OrderTransaction::where('reference', $idempotencyRef)->exists()) {
                return response()->json($order, 200);
            }

            if (!in_array($order->status, ['draft', 'open', 'accepted', 'funded'])) {
                return response()->json(['error' => 'This trade cannot be cancelled.'], 400);
            }

            if ($order->maker_id !== $userId && $order->taker_id !== $userId) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }

            // 2. Safe Escrow Unlock Reversal
            if ($order->status === 'funded') {
                $sellerId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;
                $currency = ($order->type === 'sell') ? $order->quote_currency : $order->asset;
                $amount   = ($order->type === 'sell') ? (string)$order->total : (string)$order->amount;

                $sellerWallet = UserWallet::where('user_id', $sellerId)->where('currency', $currency)->firstOrFail();
                WalletService::releaseReservedToBalance($sellerWallet->id, $amount, $idempotencyRef, ['order_id' => $order->id]);
            }

            // 3. Inventory Return
            if (in_array($order->status, ['accepted', 'funded']) && $order->p2p_ad_id) {
                $ad = P2PAd::where('id', $order->p2p_ad_id)->lockForUpdate()->first();
                if ($ad) {
                    $ad->available_amount = bcadd((string)$ad->available_amount, (string)$order->amount, 18);
                    $ad->save();
                }
            }

            $order->status = 'cancelled';
            $order->save();

            return response()->json($order, 200);
        });
    }

    public function dispute(Request $r, $uid)
    {
        $validator = Validator::make($r->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first(), null, 400);
        }

        return DB::transaction(function () use ($uid, $r) {
            $order = P2POrder::where('id', $uid)->lockForUpdate()->firstOrFail();
            
            if ($order->status === 'disputed') {
                return Response::success('Trade already disputed.', $order, 200);
            }
            
            if (!in_array($order->status, ['funded', 'accepted', 'paid'])) {
                 return Response::error('Order cannot be disputed in current state.', null, 400);
            }
            
            if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
                return Response::error('Unauthorized to dispute this trade.', null, 403);
            }

            $order->status = 'disputed';
            $meta = $order->meta ?? [];
            $meta['dispute_reason'] = $r->reason;
            $meta['disputed_by'] = auth()->id();
            $order->meta = $meta;
            $order->save();

            return Response::success('Trade disputed successfully.', $order, 200);
        });
    }
}