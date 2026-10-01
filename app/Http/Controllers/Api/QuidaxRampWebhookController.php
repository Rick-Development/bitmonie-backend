<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RampTransaction;
use App\Services\QuidaxRampService;
use App\Services\RampTransactionSyncService;
use App\Services\WebhookAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessQuidaxOfframpForCardFunding;
use App\Models\CryptoCardTransactions;
use Illuminate\Support\Str;

class QuidaxRampWebhookController extends Controller
{
    public function handle(
        Request $request,
        QuidaxRampService $rampService,
        RampTransactionSyncService $syncService,
        WebhookAuditService $audit
    ) {
        $eventLog = $audit->recordReceived($request, 'quidax_ramp');
        $signature = $request->header('x-ramp-signature');
        $rawPayload = $request->getContent();
        $payload = json_decode($rawPayload, true);
        

        if (!is_array($payload)) {
            $payload = $request->all();
        }

        if (!is_array($payload) || $payload === []) {
            $audit->markFailed($eventLog, 'Malformed or empty Quidax Ramp webhook payload.');

            return response()->json(['status' => 'error_logged'], 200);
        }

      if (!$rampService->verifyWebhookSignature($rawPayload, $signature)) {
    $audit->markFailed(
        $eventLog,
        'Quidax Ramp webhook signature verification failed.'
    );

    Log::warning('Quidax Ramp webhook signature verification failed.', [
        'event' => $payload['event'] ?? $payload['type'] ?? null,
        'ip' => $request->ip(),
    ]);

    return response()->json([
        'status' => 'invalid_signature',
    ], 200);
}

       $eventName = $this->normalizeStatus(
    $payload['event'] ?? $payload['type'] ?? ''
);

$data = is_array($payload['data'] ?? null)
    ? $payload['data']
    : [];

$merchantReference = trim(
    (string) ($data['merchant_reference'] ?? '')
);

$mode = strtolower(
    trim((string) ($data['mode'] ?? ''))
);

/*
|--------------------------------------------------------------------------
| Card funding must be handled before normal Ramp reconciliation.
|--------------------------------------------------------------------------
*/
if ($this->isCardFundingOfframp($data, $merchantReference)) {
    return $this->handleCardFundingOfframp(
        $payload,
        $data,
        $eventName,
        $eventLog,
        $audit
    );
}

        if ($this->isCardFundingOfframp($data, $merchantReference)) {
    return $this->handleCardFundingOfframp(
        $payload,
        $data,
        $eventName,
        $eventLog,
        $audit
    );
}
        $transactionType = str_contains($eventName, 'sell_transaction') || $mode === 'sell'
            ? 'off_ramp'
            : 'on_ramp';
        $providerStatus = (string) ($data['status'] ?? '');
        $internalStatus = $transactionType === 'off_ramp'
            ? $this->mapOffRampStatus($eventName, $data)
            : $this->mapOnRampStatus($eventName, $data);
        $dedupeKey = $audit->dedupeKey('quidax_ramp', $merchantReference, $internalStatus, $eventName);

        $audit->enrich($eventLog, [
            'event_name' => $eventName,
            'event_id' => $payload['id'] ?? $payload['event_id'] ?? $data['id'] ?? $data['public_id'] ?? null,
            'dedupe_key' => $dedupeKey,
            'transaction_reference' => $merchantReference,
            'transaction_type' => $transactionType,
            'provider_status' => $providerStatus,
            'internal_status' => $internalStatus,
        ]);

        if ($merchantReference === '') {
            $audit->markFailed($eventLog, 'Quidax Ramp webhook missing merchant_reference.');

            return response()->json(['status' => 'error_logged'], 200);
        }

        if ($duplicate = $audit->findProcessedDuplicate($dedupeKey, $eventLog->id)) {
            $audit->markDuplicate($eventLog, $duplicate);

            return response()->json(['status' => 'duplicate'], 200);
        }
        

        $existingTransaction = RampTransaction::where('merchant_reference', $merchantReference)
            ->where('type', $transactionType)
            ->first();
        $statusBefore = $existingTransaction?->status;

        try {
            $transaction = $syncService->handleWebhook($payload);
        } catch (\Throwable $exception) {
            $audit->markFailed($eventLog, $exception->getMessage());

            Log::error('Quidax Ramp webhook processing failed after receipt.', [
                'merchant_reference' => $merchantReference,
                'event' => $eventName,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['status' => 'error_logged'], 200);
        }

        if (!$transaction) {
            $audit->markIgnored($eventLog, 'No matching local ramp transaction found.');

            return response()->json(['status' => 'ignored'], 200);
        }

        $audit->enrich($eventLog, ['user_id' => $transaction->user_id]);
        $audit->recordReconciliation($eventLog, [
            'action' => 'quidax_ramp_status_reconciled',
            'transaction_type' => $transaction->type,
            'transaction_id' => $transaction->id,
            'transaction_reference' => $transaction->merchant_reference,
            'user_id' => $transaction->user_id,
            'currency' => strtolower((string) ($transaction->from_currency ?: $transaction->to_currency)),
            'amount' => is_numeric($transaction->from_amount) ? $transaction->from_amount : null,
            'status_before' => $statusBefore,
            'status_after' => $transaction->status,
            'changes' => [
                'provider_status' => $providerStatus,
                'event_name' => $eventName,
                'wallet_update' => is_array($transaction->metadata) ? ($transaction->metadata['wallet_update'] ?? null) : null,
            ],
        ]);
        $audit->markProcessed($eventLog, 'reconciled');

        return response()->json([
            'status' => $transaction ? 'success' : 'ignored',
        ], 200);
    }

    protected function mapOnRampStatus(string $eventName, array $data): string
    {
        $status = $this->normalizeStatus($data['status'] ?? null);
        $fiatDepositStatus = $this->normalizeStatus(data_get($data, 'fiat_deposit.status'));
        $cryptoPayoutStatus = $this->normalizeStatus(data_get($data, 'crypto_payout.status'));

        if (in_array($eventName, ['buy_transaction_successful'], true) || in_array($status, ['completed', 'success', 'successful'], true) || in_array($cryptoPayoutStatus, ['completed', 'success', 'successful'], true)) {
            return 'successful';
        }

        if (in_array($eventName, ['buy_transaction_failed'], true) || in_array($status, ['failed', 'error', 'cancelled', 'rejected'], true) || in_array($cryptoPayoutStatus, ['failed', 'error', 'cancelled', 'rejected'], true)) {
            return 'failed';
        }

        if (
            in_array($eventName, ['buy_transaction_processing'], true)
            || in_array($status, ['processing', 'pending', 'confirmed'], true)
            || in_array($fiatDepositStatus, ['success', 'processing', 'pending'], true)
            || in_array($cryptoPayoutStatus, ['processing', 'pending'], true)
        ) {
            return 'pending';
        }

        return $status ?: 'unknown';
    }

    protected function mapOffRampStatus(string $eventName, array $data): string
    {
        $status = $this->normalizeStatus($data['status'] ?? null);
        $fiatPayoutStatus = $this->normalizeStatus(data_get($data, 'fiat_payout.status'));
        $cryptoDepositStatus = $this->normalizeStatus(data_get($data, 'crypto_deposit.status'));

        if (in_array($eventName, ['sell_transaction_successful'], true) || in_array($status, ['completed', 'success', 'successful'], true) || in_array($fiatPayoutStatus, ['completed', 'success', 'successful'], true)) {
            return 'successful';
        }

        if (
            in_array($eventName, ['sell_transaction_failed'], true)
            || in_array($status, ['failed', 'error', 'needs_attention', 'cancelled', 'rejected'], true)
            || in_array($fiatPayoutStatus, ['failed', 'error', 'cancelled', 'rejected'], true)
        ) {
            return 'failed';
        }

        if (
            in_array($eventName, ['sell_transaction_processing'], true)
            || in_array($status, ['processing', 'pending', 'confirmed'], true)
            || in_array($fiatPayoutStatus, ['processing', 'pending', 'queued', 'initiated'], true)
            || in_array($cryptoDepositStatus, ['accepted', 'processing', 'pending'], true)
        ) {
            return 'pending';
        }

        return $status ?: 'unknown';
    }

    protected function normalizeStatus($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return strtolower(str_replace([' ', '-', '.'], '_', trim($value)));
    }

/**
 * Determine whether this Quidax Ramp transaction is a
 * crypto-card funding offramp.
 *
 * Card-funding merchant references are generated as:
 *
 *     CARD-FUND-xxxxxxxx
 */
protected function isCardFundingOfframp(
    array $data,
    ?string $reference = null
): bool {
    $merchantReference = $reference
        ?: $this->firstDataValue($data, [
            'merchant_reference',
            'reference',
            'transaction_reference',
        ]);

    if (!$merchantReference) {
        return false;
    }

    return Str::startsWith(
        strtoupper(trim($merchantReference)),
        'CARD-FUND-'
    );
}

/**
 * Handle a Quidax Ramp offramp used to fund a crypto card.
 *
 * The actual financial/card reconciliation is delegated to the
 * existing ProcessQuidaxOfframpForCardFunding job.
 */
protected function handleCardFundingOfframp(
    array $payload,
    array $data,
    string $eventName,
    $eventLog,
    WebhookAuditService $audit
): \Illuminate\Http\JsonResponse {
    $merchantReference = $this->firstDataValue($data, [
        'merchant_reference',
        'reference',
        'transaction_reference',
    ]);

    $providerStatus = $this->normalizeStatus(
        $data['status'] ?? ''
    );

    Log::info('Quidax Ramp card funding webhook received.', [
        'merchant_reference' => $merchantReference,
        'event' => $eventName,
        'provider_status' => $providerStatus,
    ]);

    if (!$merchantReference) {
        $audit->markIgnored(
            $eventLog,
            'Card funding webhook is missing merchant_reference.'
        );

        return response()->json([
            'status' => 'ignored',
        ], 200);
    }

    /*
     * Find the card funding transaction using the Quidax
     * merchant reference stored in transaction metadata.
     */
    $transaction = CryptoCardTransactions::query()
        ->where(
            'metadata->quidax_merchant_reference',
            $merchantReference
        )
        ->first();

    if (!$transaction) {
        Log::warning(
            'Quidax Ramp card funding transaction not found.',
            [
                'merchant_reference' => $merchantReference,
                'event' => $eventName,
            ]
        );

        $audit->markIgnored(
            $eventLog,
            'Card funding transaction not found.'
        );

        return response()->json([
            'status' => 'ignored',
        ], 200);
    }

    /*
     * Map provider status to our internal card-funding status.
     */
    $internalStatus = $this->isSuccessfulCardFundingStatus(
        $providerStatus
    )
        ? 'successful'
        : (
            $this->isFailedCardFundingStatus($providerStatus)
                ? 'failed'
                : 'processing'
        );

    $eventId =
        $payload['id']
        ?? $payload['event_id']
        ?? $data['id']
        ?? $data['public_id']
        ?? null;

    $audit->enrich($eventLog, [
        'event_name' => $eventName,
        'event_id' => $eventId,
        'transaction_reference' => $merchantReference,
        'transaction_type' => 'card_funding',
        'provider_status' => $providerStatus,
        'internal_status' => $internalStatus,
        'user_id' => $transaction->user_id ?? null,
    ]);

    /*
     * Use the same event/status/reference combination for
     * webhook-level idempotency.
     */
    $dedupeKey = $audit->dedupeKey(
        'quidax_ramp',
        $merchantReference,
        $internalStatus,
        $eventName
    );

    $audit->enrich($eventLog, [
        'dedupe_key' => $dedupeKey,
    ]);

    if ($duplicate = $audit->findProcessedDuplicate(
        $dedupeKey,
        $eventLog->id
    )) {
        $audit->markDuplicate(
            $eventLog,
            $duplicate
        );

        return response()->json([
            'status' => 'duplicate',
        ], 200);
    }

    /*
     * Do not financially process intermediate statuses.
     */
    if (
        !$this->isSuccessfulCardFundingStatus($providerStatus)
        && !$this->isFailedCardFundingStatus($providerStatus)
    ) {
        $audit->markProcessed(
            $eventLog,
            'processing'
        );

        return response()->json([
            'status' => 'processing',
        ], 200);
    }

    /*
     * The existing job is responsible for the atomic financial
     * reconciliation and card credit.
     */
    ProcessQuidaxOfframpForCardFunding::dispatch(
        $transaction->id,
        $payload,
        $data,
        $eventName,
        $merchantReference,
        $eventLog->id
    );

    $audit->recordReconciliation($eventLog, [
        'action' => 'quidax_ramp_card_funding_job_dispatched',
        'transaction_type' => 'card_funding',
        'transaction_id' => $transaction->id,
        'transaction_reference' => $merchantReference,
        'status_after' => $internalStatus === 'successful'
            ? 'queued_success'
            : 'queued_failure',
        'notes' =>
            'Quidax Ramp card-funding webhook queued for atomic reconciliation.',
    ]);

    $audit->markProcessed(
        $eventLog,
        'queued'
    );

    return response()->json([
        'status' => 'accepted',
    ], 200);
}

/**
 * Determine whether the Quidax status represents a successful
 * card-funding transaction.
 */
protected function isSuccessfulCardFundingStatus(
    ?string $status
): bool {
    return in_array(
        $this->normalizeStatus($status),
        [
            'completed',
            'successful',
            'success',
            'done',
            'confirmed',
        ],
        true
    );
}

/**
 * Determine whether the Quidax status represents a failed
 * card-funding transaction.
 */
protected function isFailedCardFundingStatus(
    ?string $status
): bool {
    return in_array(
        $this->normalizeStatus($status),
        [
            'failed',
            'failure',
            'cancelled',
            'canceled',
            'expired',
            'rejected',
            'error',
        ],
        true
    );
}

}
