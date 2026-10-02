<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BushaTransaction;
use App\Models\UserWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Notifications\User\BushaTradeNotification;
use App\Models\User;

class FailPendingCryptoTrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:fail-pending-trades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending Crypto sell and buy orders as failed after 30 minutes window and refund wallets.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cutoffTime = Carbon::now()->subMinutes(30);

        $pendingTrades = BushaTransaction::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        $count = 0;

        foreach ($pendingTrades as $transaction) {
            DB::beginTransaction();
            try {
                $transaction->status = 'failed';
                $transaction->save();

                // Determine refund currency and amount based on trade direction
                // "pair" is stored as source-target (e.g. NGN-BTC or BTC-NGN)
                $pairParts = explode('-', $transaction->pair);
                $sourceCurrencyCode = $pairParts[0] ?? null;
                
                // For 'buy', the user spent fiat (NGN). The amount deducted was stored in 'total'.
                // For 'sell', the user spent crypto. The amount deducted was stored in 'amount'.
                $refundAmount = 0;
                if ($transaction->type === 'buy') {
                    $refundAmount = $transaction->total;
                } elseif ($transaction->type === 'sell') {
                    $refundAmount = $transaction->amount;
                }

                if ($sourceCurrencyCode && $refundAmount > 0) {
                    $wallet = UserWallet::where('user_id', $transaction->user_id)
                        ->whereHas('currency', function($q) use ($sourceCurrencyCode) {
                            $q->where('code', $sourceCurrencyCode);
                        })->first();

                    if ($wallet) {
                        $wallet->balance += $refundAmount;
                        $wallet->save();
                    } else {
                        Log::error("Wallet not found for user {$transaction->user_id} currency {$sourceCurrencyCode} to refund {$refundAmount} for expired trade.");
                    }
                }

                // Notify User
                $user = User::find($transaction->user_id);
                if ($user) {
                    $user->notify(new BushaTradeNotification($transaction));
                }

                DB::commit();
                $count++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to expire crypto trade {$transaction->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully marked {$count} pending crypto trades as failed.");
        return 0;
    }
}
