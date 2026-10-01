<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\SavingsTransaction;
use App\Models\ReferralEarning;
use App\Models\GraphTransaction;
use App\Models\BushaTransaction;
use App\Models\User;
use App\Services\QuidaxService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatementService
{
    /**
     * Get Unified Transactions for a User
     */
    public function getUnifiedTransactions($userId, $filters = [])
    {
        $unified = [];

        // 1. Core Wallet Transactions
        $txQuery = Transaction::where('user_id', $userId);
        if (isset($filters['start_date'])) $txQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $txQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $transactions = $txQuery->get();
        foreach ($transactions as $tx) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos($tx->type, $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $tx->created_at->toDateTimeString(),
                'type' => $tx->type,
                'narration' => $tx->remark ?? 'Wallet Transaction',
                'txid' => $tx->trx_id,
                'status' => $this->mapStatus($tx->status),
                'amount' => $tx->request_amount,
                'sort_date' => $tx->created_at->timestamp
            ];
        }

        // 2. Savings Transactions
        $savingsQuery = SavingsTransaction::where('user_id', $userId);
        if (isset($filters['start_date'])) $savingsQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $savingsQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $savingsTxs = $savingsQuery->get();
        foreach ($savingsTxs as $st) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos($st->type, $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $st->created_at->toDateTimeString(),
                'type' => 'savings_' . strtolower($st->type),
                'narration' => $st->narration ?? 'Savings Transaction',
                'txid' => 'ST-' . $st->id,
                'status' => strtolower($st->status),
                'amount' => $st->amount,
                'sort_date' => $st->created_at->timestamp
            ];
        }

        // 3. Referral Earnings
        $refQuery = ReferralEarning::where('user_id', $userId);
        if (isset($filters['start_date'])) $refQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $refQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $refTxs = $refQuery->get();
        foreach ($refTxs as $rt) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos('referral', $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $rt->created_at->toDateTimeString(),
                'type' => 'referral_earning',
                'narration' => $rt->description ?? 'Referral Bonus',
                'txid' => 'REF-' . $rt->id,
                'status' => 'success', // Assuming earned implies success
                'amount' => $rt->amount,
                'sort_date' => $rt->created_at->timestamp
            ];
        }

        // 4. Crypto Graph Transactions
        $graphQuery = GraphTransaction::where('user_id', $userId);
        if (isset($filters['start_date'])) $graphQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $graphQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $graphTxs = $graphQuery->get();
        foreach ($graphTxs as $gt) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos('crypto', $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $gt->created_at->toDateTimeString(),
                'type' => 'crypto_' . strtolower($gt->type),
                'narration' => $gt->description ?? 'Crypto Graph Transaction',
                'txid' => $gt->transaction_id ?? 'GT-' . $gt->id,
                'status' => strtolower($gt->status),
                'amount' => $gt->amount,
                'sort_date' => $gt->created_at->timestamp
            ];
        }

        // 5. Busha Transactions (if applicable)
        $bushaQuery = BushaTransaction::where('user_id', $userId);
        if (isset($filters['start_date'])) $bushaQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $bushaQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $bushaTxs = $bushaQuery->get();
        foreach ($bushaTxs as $bt) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos('crypto', $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $bt->created_at->toDateTimeString(),
                'type' => 'crypto_' . strtolower($bt->type),
                'narration' => 'Crypto Trade - ' . $bt->pair,
                'txid' => $bt->busha_order_id ?? 'BT-' . $bt->id,
                'status' => strtolower($bt->status),
                'amount' => $bt->total, // total cost/return
                'sort_date' => $bt->created_at->timestamp
            ];
        }

        // 6. Ramp Transactions
        $rampQuery = \App\Models\RampTransaction::where('user_id', $userId);
        if (isset($filters['start_date'])) $rampQuery->whereDate('created_at', '>=', $filters['start_date']);
        if (isset($filters['end_date'])) $rampQuery->whereDate('created_at', '<=', $filters['end_date']);
        
        $rampTxs = $rampQuery->get();
        foreach ($rampTxs as $rt) {
            if (isset($filters['type']) && $filters['type'] !== 'all' && stripos('crypto', $filters['type']) === false && stripos('ramp', $filters['type']) === false) {
                continue;
            }
            $unified[] = [
                'date' => $rt->created_at->toDateTimeString(),
                'type' => 'crypto_ramp_' . strtolower($rt->type),
                'narration' => 'Crypto Ramp ' . ucfirst($rt->type) . ' - ' . strtoupper($rt->from_currency) . ' to ' . strtoupper($rt->to_currency),
                'txid' => $rt->reference ?? 'RT-' . $rt->id,
                'status' => strtolower($rt->status),
                'amount' => $rt->from_amount,
                'sort_date' => $rt->created_at->timestamp
            ];
        }

        // 7. Native Quidax Crypto Transactions (Deposits & Withdrawals)
        if (!isset($filters['type']) || $filters['type'] === 'all' || stripos('crypto', $filters['type']) !== false || stripos('deposit', $filters['type']) !== false || stripos('withdrawal', $filters['type']) !== false) {
            try {
                $user = User::find($userId);
                if ($user && $user->quidax_id) {
                    $quidaxService = app(QuidaxService::class);
                    
                    // Fetch Deposits
                    if (!isset($filters['type']) || $filters['type'] === 'all' || stripos('crypto', $filters['type']) !== false || stripos('deposit', $filters['type']) !== false) {
                        $depositsResponse = $quidaxService->fetch_deposits($user->quidax_id, '', '');
                        $deposits = $depositsResponse['data'] ?? [];
                        
                        foreach ($deposits as $dep) {
                            $depDate = Carbon::parse($dep['created_at']);
                            
                            if (isset($filters['start_date']) && $depDate->startOfDay()->lt(Carbon::parse($filters['start_date'])->startOfDay())) continue;
                            if (isset($filters['end_date']) && $depDate->startOfDay()->gt(Carbon::parse($filters['end_date'])->startOfDay())) continue;
                            
                            $unified[] = [
                                'date' => $depDate->toDateTimeString(),
                                'type' => 'crypto_deposit',
                                'narration' => strtoupper($dep['currency'] ?? 'Crypto') . ' Deposit',
                                'txid' => $dep['txid'] ?? $dep['id'],
                                'status' => strtolower($dep['status'] ?? 'pending'),
                                'amount' => $dep['amount'],
                                'sort_date' => $depDate->timestamp
                            ];
                        }
                    }
                    
                    // Fetch Withdrawals
                    if (!isset($filters['type']) || $filters['type'] === 'all' || stripos('crypto', $filters['type']) !== false || stripos('withdrawal', $filters['type']) !== false) {
                        $withdrawsResponse = $quidaxService->fetch_withdraws($user->quidax_id, '', '');
                        $withdraws = $withdrawsResponse['data'] ?? [];
                        
                        foreach ($withdraws as $with) {
                            $withDate = Carbon::parse($with['created_at']);
                            
                            if (isset($filters['start_date']) && $withDate->startOfDay()->lt(Carbon::parse($filters['start_date'])->startOfDay())) continue;
                            if (isset($filters['end_date']) && $withDate->startOfDay()->gt(Carbon::parse($filters['end_date'])->startOfDay())) continue;
                            
                            $unified[] = [
                                'date' => $withDate->toDateTimeString(),
                                'type' => 'crypto_withdrawal',
                                'narration' => strtoupper($with['currency'] ?? 'Crypto') . ' Withdrawal',
                                'txid' => $with['txid'] ?? $with['id'],
                                'status' => strtolower($with['status'] ?? 'pending'),
                                'amount' => $with['amount'],
                                'sort_date' => $withDate->timestamp
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('StatementService failed to fetch Quidax transactions: ' . $e->getMessage());
            }
        }

        // Sort unified transactions by date descending
        usort($unified, function ($a, $b) {
            return $b['sort_date'] <=> $a['sort_date'];
        });

        return $unified;
    }

    private function mapStatus($statusInt)
    {
        // 1=Success, 2=Pending, 3=Hold, 4=Rejected, 5=Waiting (based on Transaction model)
        switch ($statusInt) {
            case 1: return 'success';
            case 2: return 'pending';
            case 3: return 'hold';
            case 4: return 'failed';
            case 5: return 'waiting';
            default: return 'unknown';
        }
    }
}
