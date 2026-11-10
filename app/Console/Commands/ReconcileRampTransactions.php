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
    protected $signature = 'ramp:reconcile {--type= : Limit to on_ramp or off_ramp}';

    /**
     * Command description.
     */
    protected $description = 'Reconcile ramp transactions by verifying their status against the provider. On-ramp reconciliation only refreshes local status; it does not move money.';

    /**
     * Execute the command.
     */
    public function handle()
    {
        Log::info('Ramp reconciliation command started.', [
            'type' => strtolower(trim((string) $this->option('type'))) ?: 'all',
        ]);

        /**
         * We only pick transactions that are not final.
         * Final states like "completed", "failed", "reversed"
         * should not be reprocessed.
         */
        $type = strtolower(trim((string) $this->option('type')));

        $query = RampTransaction::query();

        if (in_array($type, ['on_ramp', 'off_ramp'], true)) {
            $query->where('type', $type);
        }

        // "confirmed" is the post-payment on-ramp state waiting for crypto payout.
        $statuses = ['pending', 'awaiting_payout', 'processing', 'confirmed'];
        $query->whereIn('status', $statuses);

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