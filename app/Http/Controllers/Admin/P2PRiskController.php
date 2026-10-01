<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\P2PUserStat;
use App\Models\User;
use App\Services\P2PRiskService;
use Illuminate\Http\Request;

class P2PRiskController extends Controller
{
    protected $riskService;

    public function __construct(P2PRiskService $riskService)
    {
        $this->riskService = $riskService;
    }

    /**
     * List users by risk level
     */
    public function index(Request $request)
    {
        $page_title = "P2P Risk Management";
        $query = P2PUserStat::with('user:id,firstname,lastname,email,kyc_tier');

        if ($request->has('level')) {
            $query->where('risk_level', $request->level);
        }

        if ($request->has('min_score')) {
            $query->where('risk_score', '>=', $request->min_score);
        }

        $users = $query->orderBy('risk_score', 'desc')->paginate(20);

        return view('admin.sections.p2p.risk.index', compact('page_title', 'users'));
    }

    /**
     * Manually recalculate risk for a user
     */
    public function recalculate($userId)
    {
        $user = User::findOrFail($userId);
        $stats = $this->riskService->updateRiskScore($user);

        return back()->with(['success' => ['Risk score updated successfully']]);
    }

    /**
     * Flag user (Restrict P2P access manually)
     */
    public function flagUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        $stats = P2PUserStat::firstOrCreate(['user_id' => $userId]);
        $stats->risk_level = 'high';
        $stats->risk_score = 100; // Force max
        $stats->save();

        return back()->with(['success' => ['User flagged as high risk']]);
    }
}
