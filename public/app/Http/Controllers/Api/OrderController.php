<?php

namespace App\Http\Controllers\Api;

use App\Http\Helpers\Response;
use App\Models\P2Pbuyers;
use App\Models\P2PTraders;
use App\Models\User;
use App\Models\Wallet;
use App\Models\P2POrder;
use App\Models\UserWallet;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        $q = P2POrder::with('maker'); // eager load the trader (user)

        $user = auth()->user();

        if ($r->filled('type')) {
            $q->where('type', $r->type);
        }

        if ($r->filled('quote_currency')) {
            $q->where('quote_currency', strtoupper($r->quote_currency));
        }

        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        // get all orders (no paginator)
        $orders = $q->orderByDesc('created_at')->get();

        // optionally, select only needed user fields to reduce payload
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
        $traders = P2PTraders::get();

        return Response::success('Traders fetched', $traders, 200);
    }

    public function create_trader(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'trader_name' => 'required',
            'trader_email' => 'required',
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
            ]);
        }

        $data = $request->only([
            'trader_name',
            'trader_email',
            'supported_currencies',
            'type',
            'amount',
            'price',
        ]);

        $get_trader = P2PTraders::where('trader_email', $data['trader_email'])->first();

        if ($get_trader) {
            return Response::error('Trader with this email already exisgs', $get_trader, 500);
        }
        $new_trader = P2PTraders::updateOrCreate([
            'trader_name' => $data['trader_name'],
            'trader_email' => $data['trader_email'],
            'supported_currencies' => $data['supported_currencies'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'price' => $data['price'],
            'total' => bcmul($data['amount'], $data['price'], 2)
        ]);

        return Response::success('Trader created successfully', $new_trader, 201);
    }

    // POST /api/orders
    public function store(Request $r)
    {
        $validator = \Validator::make($r->all(), [
            'type' => 'required|in:buy,sell',
            // 'asset' => 'required|string|max:16',
            'quote_currency' => 'required|string|max:16',
            'amount' => 'required|numeric|min:0.00000001',
            'price' => 'required|numeric|min:0.00000001',
            'escrow_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ]);
        }
        $user = auth()->user();

        $payload = array_merge($r->only(['type', 'quote_currency', 'amount', 'price', 'escrow_enabled']), ['maker_id' => auth()->user()->id]);
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

    // GET /api/orders/{id}
    public function show($id)
    {
        return Response::success('Order details fetched', P2POrder::findOrFail($id), 200);
    }

    // POST /api/orders/{id}/accept -- buyer accepts
    public function accept(Request $r, $id)
    {
        return DB::transaction(function () use ($id) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'open')
                return response()->json(['error' => 'order not open'], 400);
            if ($order->maker_id === auth()->id())
                // return response()->json(['error' => 'trader cannot accept own order'], 400);
                return Response::error(false, 'trader cannot accept own order', 400);
            $order->taker_id = auth()->id();
            $order->status = 'accepted';
            $order->save();
            return response()->json($order);
        });
    }

    // POST /api/orders/{id}/fund -- payer funds escrow
    public function fund(Request $r, $id)
    {
        $idKey = $r->header('Idempotency-Key');

        return DB::transaction(function () use ($id, $idKey) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'accepted')
                return response()->json(['error' => 'invalid state'], 400);
            if ($order->taker_id !== auth()->id())
                return response()->json(['error' => 'only buyer can fund'], 403);

            // resolve payer wallet
            $payerWallet = OrderService::resolvePayerWalletForFunding($order, auth()->id());

            // amount to debit depends on order type
            $debitAmount = $order->type === 'sell' ? (string) $order->total : (string) $order->amount;

            WalletService::debitToReserve($payerWallet->id, $debitAmount, 'p2p:fund:' . $order->id, ['order_id' => $order->id]);

            $order->status = 'funded';
            $order->save();
            return response()->json($order);
        });
    }

    // POST /api/orders/{id}/release -- counterpart releases escrow to recipient
    public function release(Request $r, $id)
    {
        // only trader or buyer can release depending on flow; we allow trader or buyer to call release, but check roles
        return DB::transaction(function () use ($id) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'funded')
                return response()->json(['error' => 'invalid state'], 400);

            // Determine from-wallet (where reserved exists) and to-wallet
            if ($order->type === 'sell') {
                // buyer funded quote_currency in payerWallet.reserved -> release to trader (seller) in quote_currency
                $fromWallet = UserWallet::where('user_id', $order->taker_id)->where('currency', $order->quote_currency)->firstOrFail();
                $toWallet = UserWallet::findOrFail(UserWallet::where('user_id', $order->maker_id)->where('currency', $order->quote_currency)->first()->id);
                $amount = (string) $order->total;
            } else {
                // buy order: seller deposited asset into reserved -> release asset to buyer (trader)
                $fromWallet = UserWallet::where('user_id', $order->taker_id)->where('currency', $order->asset)->firstOrFail();
                $toWallet = UserWallet::where('user_id', $order->maker_id)->where('currency', $order->asset)->firstOrFail();
                $amount = (string) $order->amount;
            }

            WalletService::releaseReservedTo($fromWallet->id, $toWallet->id, $amount, 'p2p:release:' . $order->id, ['order_id' => $order->id]);

            $order->status = 'released';
            $order->save();
            return response()->json($order);
        });
    }

    // POST /api/orders/{id}/cancel
    public function cancel(Request $r, $id)
    {
        return DB::transaction(function () use ($id) {
            $order = P2POrder::where('id', $id)->lockForUpdate()->firstOrFail();
            if (!in_array($order->status, ['draft', 'open']))
                return response()->json(['error' => 'cannot cancel'], 400);
            if ($order->maker_id !== auth()->id())
                return response()->json(['error' => 'forbidden'], 403);
            $order->status = 'cancelled';
            $order->save();
            return response()->json($order);
        });
    }

    // POST /api/p2p/trade/{uid}/dispute
    public function dispute(Request $r, $uid)
    {
        $validator = \Validator::make($r->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->first(), null, 400);
        }

        return DB::transaction(function () use ($uid, $r) {
            $order = P2POrder::where('id', $uid)->lockForUpdate()->firstOrFail();
            
            // Allow dispute if order is part of an active trade
            if (!in_array($order->status, ['funded', 'accepted', 'paid'])) {
                 return Response::error('Order cannot be disputed in current state', null, 400);
            }
            
            // Check if user is part of the trade
             if ($order->maker_id !== auth()->id() && $order->taker_id !== auth()->id()) {
                return Response::error('Unauthorized to dispute this trade', null, 403);
            }

            $order->status = 'disputed';
            $meta = $order->meta ?? [];
            $meta['dispute_reason'] = $r->reason;
            $meta['disputed_by'] = auth()->id();
            $order->meta = $meta;
            $order->save();

            return Response::success('Trade disputed successfully', $order, 200);
        });
    }
}