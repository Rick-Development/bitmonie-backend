<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTradeSubmission;
use App\Services\GiftCardTradeService;
use Illuminate\Http\Request;
use Exception;

class GiftCardTradeController extends Controller
{
    protected $service;

    public function __construct(GiftCardTradeService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $pageTitle = 'All Gift Card Trades';
        $trades = GiftCardTradeSubmission::with(['user', 'category', 'type', 'country'])
            ->latest()
            ->paginate(10);
        return view('admin.gift-card-trade.trades.index', compact('pageTitle', 'trades'));
    }

    public function pending()
    {
        $pageTitle = 'Pending Gift Card Trades';
        $trades = GiftCardTradeSubmission::where('status', 'pending')
            ->with(['user', 'category', 'type', 'country'])
            ->latest()
            ->paginate(10);
        return view('admin.gift-card-trade.trades.index', compact('pageTitle', 'trades'));
    }

    public function approved()
    {
        $pageTitle = 'Approved Gift Card Trades';
        $trades = GiftCardTradeSubmission::where('status', 'approved')
            ->with(['user', 'category', 'type', 'country', 'admin'])
            ->latest()
            ->paginate(10);
        return view('admin.gift-card-trade.trades.index', compact('pageTitle', 'trades'));
    }

    public function rejected()
    {
        $pageTitle = 'Rejected Gift Card Trades';
        $trades = GiftCardTradeSubmission::where('status', 'rejected')
            ->with(['user', 'category', 'type', 'country', 'admin'])
            ->latest()
            ->paginate(10);
        return view('admin.gift-card-trade.trades.index', compact('pageTitle', 'trades'));
    }

    public function details($id)
    {
        $pageTitle = 'Gift Card Trade Details';
        $trade = GiftCardTradeSubmission::with(['user', 'category', 'type', 'country', 'images', 'admin'])
            ->findOrFail($id);
        return view('admin.gift-card-trade.trades.details', compact('pageTitle', 'trade'));
    }

    public function approve($id)
    {
        try {
            // Using ID directly as there is no guard for admin auth in this context or handle differently
            // Assuming simplified auth for now
            $adminId = 1; // Default admin ID for now if auth not set up
            if (auth()->guard('admin')->check()) {
                $adminId = auth()->guard('admin')->id();
            }
            
            $this->service->approveTrade($id, $adminId);
            $notify[] = ['success', 'Trade approved and user wallet credited successfully'];
            return back()->withNotify($notify);
        } catch (Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
             $adminId = 1; 
            if (auth()->guard('admin')->check()) {
                $adminId = auth()->guard('admin')->id();
            }

            $this->service->rejectTrade($id, $adminId, $request->reason);
            $notify[] = ['success', 'Trade rejected successfully'];
            return back()->withNotify($notify);
        } catch (Exception $e) {
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }
}
