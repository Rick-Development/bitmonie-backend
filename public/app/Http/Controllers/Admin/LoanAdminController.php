<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\Loan;
use App\Models\LoanBorrowRequest;
use App\Models\LoanOffer;
use Illuminate\Http\Request;

class LoanAdminController extends Controller
{
    /**
     * Get all loans with filters
     */
    public function index(Request $request)
    {
        $query = Loan::with(['borrower', 'lender']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by asset
        if ($request->has('asset')) {
            $query->where('asset', $request->asset);
        }

        // Search by reference code
        if ($request->has('search')) {
            $query->where('reference_code', 'like', '%' . $request->search . '%');
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(20);

        return Response::successResponse('Loans retrieved successfully', $loans);
    }

    /**
     * Get all lending offers
     */
    public function getLendingOffers(Request $request)
    {
        $query = LoanOffer::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('asset')) {
            $query->where('asset', $request->asset);
        }

        $offers = $query->orderBy('created_at', 'desc')->paginate(20);

        return Response::successResponse('Lending offers retrieved successfully', $offers);
    }

    /**
     * Get all borrow requests
     */
    public function getBorrowRequests(Request $request)
    {
        $query = LoanBorrowRequest::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('asset')) {
            $query->where('asset', $request->asset);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return Response::successResponse('Borrow requests retrieved successfully', $requests);
    }

    /**
     * Get loan statistics
     */
    public function getStatistics()
    {
        $stats = [
            'total_loans' => Loan::count(),
            'active_loans' => Loan::where('status', 'active')->count(),
            'completed_loans' => Loan::where('status', 'completed')->count(),
            'overdue_loans' => Loan::where('status', 'overdue')->count(),
            'liquidated_loans' => Loan::where('status', 'liquidated')->count(),
            
            'total_lending_offers' => LoanOffer::count(),
            'pending_offers' => LoanOffer::where('status', 'pending')->count(),
            'matched_offers' => LoanOffer::where('status', 'matched')->count(),
            
            'total_borrow_requests' => LoanBorrowRequest::count(),
            'pending_requests' => LoanBorrowRequest::where('status', 'pending')->count(),
            'matched_requests' => LoanBorrowRequest::where('status', 'matched')->count(),
            
            'total_volume' => [
                'USDT' => Loan::where('asset', 'USDT')->sum('amount'),
                'USDC' => Loan::where('asset', 'USDC')->sum('amount'),
                'BTC' => Loan::where('asset', 'BTC')->sum('amount'),
            ],
            
            'total_interest_earned' => Loan::where('status', 'completed')->sum('total_interest'),
        ];

        return Response::successResponse('Statistics retrieved successfully', $stats);
    }

    /**
     * Get loan details
     */
    public function show($id)
    {
        $loan = Loan::with(['borrower', 'lender', 'offer', 'request'])->findOrFail($id);

        return Response::successResponse('Loan details retrieved successfully', $loan);
    }

    /**
     * Force liquidate a loan (admin action)
     */
    public function forceLiquidate($id)
    {
        $loan = Loan::where('status', 'active')->findOrFail($id);

        try {
            // TODO: Implement liquidation logic
            // 1. Sell collateral
            // 2. Repay lender
            // 3. Update loan status

            $loan->update([
                'status' => 'liquidated',
                'completed_at' => now(),
            ]);

            return Response::successResponse('Loan liquidated successfully', $loan);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Mark loan as overdue (scheduled job should call this)
     */
    public function markOverdue($id)
    {
        $loan = Loan::where('status', 'active')
            ->where('due_date', '<', now())
            ->findOrFail($id);

        $loan->update(['status' => 'overdue']);

        return Response::successResponse('Loan marked as overdue', $loan);
    }

    /**
     * Get overdue loans (for monitoring)
     */
    public function getOverdueLoans()
    {
        $overdueLoans = Loan::where('status', 'active')
            ->where('due_date', '<', now())
            ->with(['borrower', 'lender'])
            ->get();

        return Response::successResponse('Overdue loans retrieved successfully', $overdueLoans);
    }
}
