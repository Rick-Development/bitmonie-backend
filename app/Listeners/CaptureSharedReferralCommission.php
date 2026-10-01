<?php

namespace App\Listeners;

use App\Models\OrderTransaction;
use App\Models\GraphTransaction;
use App\Models\RampTransaction;
use App\Models\Transaction;
use App\Services\SharedReferralCommissionService;
use Illuminate\Support\Facades\Log;

class CaptureSharedReferralCommission
{
    public function handle(object $event): void
    {
        try {
            $service = app(SharedReferralCommissionService::class);

            if ($event instanceof OrderTransaction) {
                $service->captureFromOrderTransaction($event);
                return;
            }

            if ($event instanceof Transaction) {
                $service->captureFromTransaction($event);
                return;
            }

            if ($event instanceof RampTransaction) {
                $service->captureFromRampTransaction($event);
                return;
            }

            if ($event instanceof GraphTransaction) {
                $service->captureFromGraphTransaction($event);
            }
        } catch (\Throwable $exception) {
            Log::warning('Shared referral commission capture skipped.', [
                'error' => $exception->getMessage(),
                'event' => get_class($event),
                'id' => $event->id ?? null,
            ]);
        }
    }
}
