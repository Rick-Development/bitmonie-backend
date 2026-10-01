<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2POrder;
use App\Models\UserWallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class P2PDisputeController extends Controller
{
    /**
     * List all pending disputes
     */
    public function index(Request $request)
    {
        $page_title = "P2P Disputes";
        $query = P2POrder::with(['maker', 'taker', 'ad', 'chats'])
            ->where('appeal_status', 'pending');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $disputes = $query->latest()->paginate(20);

        return view('admin.sections.p2p.disputes.index', compact('page_title', 'disputes'));
    }

    /**
     * Show dispute details
     */
    public function show($id)
    {
        $page_title = "Dispute Details";
        $dispute = P2POrder::with(['maker', 'taker', 'ad', 'chats'])
            ->where('appeal_status', 'pending')
            ->findOrFail($id);

        return view('admin.sections.p2p.disputes.details', compact('page_title', 'dispute'));
    }

    /**
     * Resolve dispute (force release or refund)
     */
    public function resolve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:release_to_buyer,refund_to_seller',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        return DB::transaction(function () use ($request, $id) {
            $order = P2POrder::where('id', $id)
                ->where('appeal_status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->action === 'release_to_buyer') {
                // Release crypto to buyer (taker)
                // Winner: Buyer, Loser: Seller
                $winnerId = ($order->type === 'sell') ? $order->taker_id : $order->maker_id;
                $loserId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;

                if ($order->type === 'sell') {
                    $fromWallet = UserWallet::where('user_id', $order->maker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                    $toWallet = UserWallet::where('user_id', $order->taker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                } else {
                    $fromWallet = UserWallet::where('user_id', $order->taker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                    $toWallet = UserWallet::where('user_id', $order->maker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                }

                WalletService::releaseReservedTo($fromWallet->id, $toWallet->id, (string)$order->amount, 'p2p:admin_release:' . $order->id, ['order_id' => $order->id]);

                $order->status = 'completed';
            } else {
                // Refund to seller (return reserved to balance)
                // Winner: Seller, Loser: Buyer
                $winnerId = ($order->type === 'sell') ? $order->maker_id : $order->taker_id;
                $loserId = ($order->type === 'sell') ? $order->taker_id : $order->maker_id;

                if ($order->type === 'sell') {
                    $wallet = UserWallet::where('user_id', $order->maker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                } else {
                    $wallet = UserWallet::where('user_id', $order->taker_id)
                        ->where('currency_code', $order->asset)
                        ->firstOrFail();
                }

                $wallet->reserved -= $order->amount;
                $wallet->balance += $order->amount;
                $wallet->save();

                $order->status = 'cancelled';
            }

            $order->appeal_status = 'resolved';
            $meta = $order->meta ?? [];
            $meta['admin_resolution'] = $request->action;
            $meta['admin_notes'] = $request->admin_notes;
            $meta['resolved_by'] = auth()->id();
            $meta['resolved_at'] = now();
            $order->meta = $meta;
            $order->save();

            // Update Stats
            $winnerStats = \App\Models\P2PUserStat::firstOrCreate(['user_id' => $winnerId]);
            $winnerStats->increment('disputes_won');
            
            $loserStats = \App\Models\P2PUserStat::firstOrCreate(['user_id' => $loserId]);
            $loserStats->increment('disputes_lost'); // Make sure this column exists or use increment

            // Recalculate Risk for Loser
            $riskService = new \App\Services\P2PRiskService();
            $riskService->updateRiskScore(\App\Models\User::find($loserId));

            // Broadcast Event
            try {
                event(new \App\Events\P2POrderStatusUpdated($order));
            } catch (\Exception $e) {
                //\Log::error("Pusher Broadcast Error: " . $e->getMessage());
            }

            return redirect()->route('admin.p2p.disputes.index')->with(['success' => ['Dispute resolved successfully']]);
        });
    }
}
