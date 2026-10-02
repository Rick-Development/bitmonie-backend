<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ReceivedInterest;
use App\Http\Controllers\Controller;

class CryptomartInterestController extends Controller
{
    public function interests(Request $request)
    {
        $user = auth()->user();

        // Filters
        $month = $request->get('month');
        $year = $request->get('year');
        $range = $request->get('range', 'month'); // month | year | all

        // Base query
        $query = ReceivedInterest::where('user_id', $user->id);

        // Debug correctly
        // dd($query->first());

        // Apply filters dynamically
        if ($range === 'month' && $month && $year) {
            $query->whereMonth('created_at', $month)
                ->whereYear('created_at', $year);
        }

        if ($range === 'year' && $year) {
            $query->whereYear('created_at', $year);
        }

        // Fetch paginated interests (IMPORTANT)
        $interests = $query->orderBy('created_at', 'desc')->get();

        // Summary data
        $totalInterest = ReceivedInterest::where('user_id', $user->id)
            ->sum('accrued_interest');

        $monthlyEarnings = ReceivedInterest::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('accrued_interest');

        $yearlyEarnings = ReceivedInterest::where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->sum('accrued_interest');

        return response()->json([
            'summary' => [
                'total_interest' => round($totalInterest, 2),
                'monthly_earnings' => round($monthlyEarnings, 2),
                'yearly_earnings' => round($yearlyEarnings, 2),
            ],
            'interests' => $interests,
        ]);
    }

}
