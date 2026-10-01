<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\RampTransaction;
use App\Jobs\ReconcileRampTransaction;

class ReconcileRampTransactions extends Command
{
    /**
     * Command signature.
     */
    protected $signature = 'ramp:reconcile';

    /**
     * Command description.
     */
    protected $description = 'Reconcile ramp transactions by verifying their status against Quidax and correcting inconsistencies.';

    /**
     * Execute the command.
     */
    public function handle()
    {
        Log::info('Ramp reconciliation command started.');

        /**
         * We only pick transactions that are not final.
         * Final states like "completed", "failed", "reversed"
         * should not be reprocessed.
         */
        $query = RampTransaction::query()
            ->whereIn('status', [
                'pending',
                'awaiting_payout',
                'processing'
            ]);

        $count = 0;

        /**
         * Use chunking to prevent memory overload
         * when dealing with large datasets.
         */
        $query->chunkById(100, function ($transactions) use (&$count) {

            foreach ($transactions as $transaction) {
                

                // ReconcileRampTransaction::dispatch($transaction->id);
                ReconcileRampTransaction::dispatch($transaction->id)->delay(now()->addSeconds(2));

                $count++;

                Log::info('Reconciliation job dispatched.', [
                    'transaction_id' => $transaction->id,
                    'merchant_reference' => $transaction->merchant_reference,
                ]);
            }
        });

        Log::info('Ramp reconciliation command completed.', [
            'total_dispatched' => $count,
        ]);

        $this->info("Reconciliation jobs dispatched: {$count}");

        return Command::SUCCESS;
    }
}