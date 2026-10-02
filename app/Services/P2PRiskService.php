<?php

namespace App\Services;

use App\Models\P2PUserStat;
use App\Models\User;

class P2PRiskService
{
    /**
     * Calculate and update risk score for a user
     */
    public function updateRiskScore(User $user)
    {
        $stats = P2PUserStat::firstOrCreate(['user_id' => $user->id]);
        
        $score = 0;

        // 1. Dispute Risk (Max 60 points)
        // Each lost dispute adds 20 points
        $score += ($stats->disputes_lost ?? 0) * 20;

        // 2. Cancellation Risk (Max 40 points)
        // If cancellation rate > 30%, add points
        $totalOrders = $stats->total_trades ?? 0;
        if ($totalOrders > 5) {
            $cancelRate = ($stats->cancelled_orders_last_30d / $totalOrders) * 100;
            if ($cancelRate > 50) $score += 40;
            elseif ($cancelRate > 30) $score += 20;
        }

        // 3. Rating Risk (Max 30 points)
        // If rating < 4.0 after 5 trades
        if ($totalOrders > 5 && $stats->rating < 4.0) {
            if ($stats->rating < 3.0) $score += 30;
            else $score += 15;
        }

        // 4. Account Age Risk (Max 10 points)
        if ($user->created_at->diffInDays(now()) < 30) {
            $score += 10;
        }

        // Cap score at 100
        $score = min($score, 100);

        // Determine Level
        $level = 'low';
        if ($score >= 70) $level = 'high';
        elseif ($score >= 30) $level = 'medium';

        $stats->risk_score = $score;
        $stats->risk_level = $level;
        $stats->save();

        return $stats;
    }
}
