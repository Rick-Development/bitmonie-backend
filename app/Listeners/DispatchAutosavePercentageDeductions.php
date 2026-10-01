<?php

namespace App\Listeners;

use App\Jobs\ProcessAutosavePercentageDeduction;
use App\Models\AutosavePlan;
use App\Models\OrderTransaction;

class DispatchAutosavePercentageDeductions
{
    public function handle(OrderTransaction $transaction): void
    {
        if ($transaction->type !== 'debit' || !$transaction->wallet) {
            return;
        }

        if (($transaction->metadata['source'] ?? null) === 'autosave') {
            return;
        }

        AutosavePlan::active()
            ->where('user_id', $transaction->wallet->user_id)
            ->whereIn('mode', [AutosavePlan::MODE_PERCENTAGE, AutosavePlan::MODE_BOTH])
            ->chunkById(50, function ($plans) use ($transaction) {
                foreach ($plans as $plan) {
                    ProcessAutosavePercentageDeduction::dispatch($plan->id, $transaction->id);
                }
            });
    }
}
