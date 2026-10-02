<?php

namespace App\Jobs;

use App\Models\AutosavePlan;
use App\Models\OrderTransaction;
use App\Services\AutosaveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAutosavePercentageDeduction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $planId, public int $orderTransactionId)
    {
    }

    public function handle(AutosaveService $autosaveService): void
    {
        $plan = AutosavePlan::active()->find($this->planId);
        $source = OrderTransaction::with('wallet')->find($this->orderTransactionId);

        if (!$plan || !$source || !$source->wallet) {
            return;
        }

        $autosaveService->processPercentagePlan($plan, $source->wallet, $source);
    }
}
