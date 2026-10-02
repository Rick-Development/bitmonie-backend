<?php

namespace App\Jobs;

use App\Models\RampTransaction;
use App\Services\QuidaxRampService;
use App\Services\QuidaxService;
use App\Services\RampTransactionSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileRampTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   public int $tries = 3;

public array $backoff = [30, 60, 120];

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(
        QuidaxRampService $rampService,
        QuidaxService $quidaxService,
        RampTransactionSyncService $syncService
    ): void {
        $log = Log::channel('ramp_sell');

        $transaction = RampTransaction::with('user')->find($this->transactionId);

        if (!$transaction) {
            $log->warning('ReconcileRampTransaction: transaction not found', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        $merchantReference = $transaction->merchant_reference;

        $log->info('ReconcileRampTransaction started', [
            'transaction_id'     => $transaction->id,
            'merchant_reference' => $merchantReference,
            'type'               => $transaction->type,
            'current_status'     => $transaction->status,
            'attempt'            => $this->attempts(),
        ]);

        // Terminal states are final — never touch them again
        if (in_array($transaction->status, ['completed', 'failed', 'cancelled'], true)) {
            $log->info('ReconcileRampTransaction: already terminal, skipping', [
                'merchant_reference' => $merchantReference,
                'status'             => $transaction->status,
            ]);
            return;
        }

        try {
            if ($transaction->type === 'off_ramp') {
                $this->reconcileOffRamp($transaction, $rampService, $syncService, $quidaxService, $log);
            } else {
                $this->reconcileOnRamp($transaction, $rampService, $syncService, $log);
            }
        } catch (Exception $e) {
            $log->error('ReconcileRampTransaction exception', [
                'merchant_reference' => $merchantReference,
                'message'            => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // protected function reconcileOffRamp(
    //     RampTransaction $transaction,
    //     QuidaxRampService $rampService,
    //     RampTransactionSyncService $syncService,
    //     QuidaxService $quidaxService,
    //     $log
    // ): void {
    //     $merchantReference = $transaction->merchant_reference;

    //     // 1. Always prefer the Ramp provider as source of truth for final status
    //     $result = $rampService->offRampTransaction($merchantReference);

    //     if ($this->isSuccessfulRampResponse($result)) {
    //         $providerData = is_array($result['data']) ? $result['data'] : [];

    //         // This is the only place that should move us to completed / failed / awaiting_payout
    //         $transaction = $syncService->syncOffRamp($transaction, $providerData);

    //         $log->info('ReconcileRampTransaction: synced from Ramp provider', [
    //             'merchant_reference' => $merchantReference,
    //             'new_status'         => $transaction->status,
    //             'provider_status'    => $providerData['status'] ?? null,
    //         ]);
    //     } else {
    //         $log->warning('ReconcileRampTransaction: Ramp provider status unavailable', [
    //             'merchant_reference' => $merchantReference,
    //             'provider_status'    => $result['status'] ?? null,
    //             'message'            => $result['message'] ?? null,
    //         ]);
    //     }

    //     // Refresh after possible sync
    //     $transaction->refresh();

    //     // 2. If we are still in "processing" and never created the Master→Ramp withdrawal,
    //     //    the original ProcessRampSell either never ran or failed early (e.g. user balance).
    //     //    In that case we can safely re-dispatch it once.
    //     if (
    //         $transaction->status === 'processing'
    //         && empty(data_get($transaction->metadata, 'job.ramp_withdrawal'))
    //         && empty(data_get($transaction->metadata, 'job.main_account_withdrawal'))
    //     ) {
    //         $this->maybeRedispatchProcessRampSell($transaction, $log);
    //         return;
    //     }

    //     // 3. Optional observability only — never decide local status from this
    //     $this->logMasterToRampWithdrawalStatus($transaction, $quidaxService, $log);
    // }
    protected function reconcileOffRamp(
    RampTransaction $transaction,
    QuidaxRampService $rampService,
    RampTransactionSyncService $syncService,
    QuidaxService $quidaxService,
    $log
): void {
    $merchantReference = $transaction->merchant_reference;

    // ------------------------------------------------------------------
    // 1. Prefer the Ramp provider when it is available
    // ------------------------------------------------------------------
    $result = $rampService->offRampTransaction($merchantReference);

    if ($this->isSuccessfulRampResponse($result)) {
        $providerData = is_array($result['data']) ? $result['data'] : [];

        $transaction = $syncService->syncOffRamp($transaction, $providerData);

        $log->info('ReconcileRampTransaction: synced from Ramp provider', [
            'merchant_reference' => $merchantReference,
            'new_status'         => $transaction->status,
            'provider_status'    => $providerData['status'] ?? null,
        ]);

        // If the provider already moved us to a terminal state we are done
        if (in_array($transaction->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }
    } else {
        $log->warning('ReconcileRampTransaction: Ramp provider status unavailable', [
            'merchant_reference' => $merchantReference,
            'provider_status'    => $result['status'] ?? null,
            'message'            => $result['message'] ?? null,
            'http_status'        => $result['http_status'] ?? null,
        ]);
    }

    $transaction->refresh();

    // ------------------------------------------------------------------
    // 2. Early processing → re-dispatch ProcessRampSell (unchanged)
    // ------------------------------------------------------------------
    if (
        $transaction->status === 'processing'
        && empty(data_get($transaction->metadata, 'job.ramp_withdrawal'))
        && empty(data_get($transaction->metadata, 'job.main_account_withdrawal'))
    ) {
        $this->maybeRedispatchProcessRampSell($transaction, $log);
        return;
    }

    // ------------------------------------------------------------------
    // 3. NEW: Handle awaiting_payout using the real Quidax withdrawal
    // ------------------------------------------------------------------
    if ($transaction->status === 'awaiting_payout') {
        $this->reconcileAwaitingPayout($transaction, $quidaxService, $log);
        return;
    }

    // ------------------------------------------------------------------
    // 4. Everything else → just observability
    // ------------------------------------------------------------------
    $this->logMasterToRampWithdrawalStatus($transaction, $quidaxService, $log);
}

    protected function reconcileOnRamp(
        RampTransaction $transaction,
        QuidaxRampService $rampService,
        RampTransactionSyncService $syncService,
        $log
    ): void {
        $result = $rampService->onRampTransaction($transaction->merchant_reference);

        if (!$this->isSuccessfulRampResponse($result)) {
            $log->warning('ReconcileRampTransaction (on-ramp): provider unavailable', [
                'merchant_reference' => $transaction->merchant_reference,
            ]);
            return;
        }

        $providerData = is_array($result['data']) ? $result['data'] : [];
        $transaction  = $syncService->syncOnRamp($transaction, $providerData);

        $log->info('ReconcileRampTransaction (on-ramp) synced', [
            'merchant_reference' => $transaction->merchant_reference,
            'new_status'         => $transaction->status,
        ]);
    }

    /**
     * Only re-dispatch when we are still in the very early "processing" state
     * and neither User→Master nor Master→Ramp has been recorded.
     */
    protected function maybeRedispatchProcessRampSell(RampTransaction $transaction, $log): void
    {
        $user = $transaction->user;

        if (!$user || !$user->quidax_id || empty($transaction->wallet_address)) {
            $log->info('ReconcileRampTransaction: cannot re-dispatch, missing user or ramp address', [
                'merchant_reference' => $transaction->merchant_reference,
            ]);
            return;
        }

        $log->info('ReconcileRampTransaction: re-dispatching ProcessRampSell (early processing state)', [
            'merchant_reference' => $transaction->merchant_reference,
        ]);

        $mainAccountData = data_get($transaction->metadata, 'job.main_account_withdrawal_data') ?? [
            'currency'         => strtolower((string) $transaction->from_currency),
            'network'          => strtolower((string) $transaction->network),
            'amount'           => (string) $transaction->from_amount,
            'fund_uid'         => 'me',
            'transaction_note' => "Off-ramp move to main account: {$transaction->merchant_reference}",
            'narration'        => "Off-ramp move to main account: {$transaction->merchant_reference}",
        ];

        ProcessRampSell::dispatch(
            $user,
            $transaction->merchant_reference,
            $mainAccountData,
            strtolower((string) $transaction->from_currency),
            (string) $transaction->from_amount,
            $transaction->wallet_address,
            strtolower((string) $transaction->network)
        )->delay(now()->addSeconds(20));
    }

    /**
     * Observability only.
     * Never promote local status to "completed" just because the Quidax withdrawal succeeded.
     */
    protected function logMasterToRampWithdrawalStatus(
        RampTransaction $transaction,
        QuidaxService $quidaxService,
        $log
    ): void {
        $withdrawalId = data_get($transaction->metadata, 'job.ramp_withdrawal.data.id');

        if (!$withdrawalId) {
            return;
        }

        try {
            $response = $quidaxService->fetch_a_withdrawal('me', $withdrawalId);
            $status   = strtolower((string) data_get($response, 'data.status', ''));

            $log->info('ReconcileRampTransaction: Master→Ramp Quidax withdrawal status (observability only)', [
                'merchant_reference' => $transaction->merchant_reference,
                'withdrawal_id'      => $withdrawalId,
                'quidax_status'      => $status,
                'local_status'       => $transaction->status,
            ]);
        } catch (Exception $e) {
            $log->warning('ReconcileRampTransaction: could not fetch Quidax withdrawal', [
                'merchant_reference' => $transaction->merchant_reference,
                'withdrawal_id'      => $withdrawalId,
                'error'              => $e->getMessage(),
            ]);
        }
    }

    protected function isSuccessfulRampResponse(array $response): bool
    {
        return ($response['ok'] ?? false) === true
            || in_array($response['status'] ?? '', ['ok', 'success'], true);
    }

    public function failed(?Exception $exception): void
    {
        Log::channel('ramp_sell')->error('ReconcileRampTransaction permanently failed', [
            'transaction_id' => $this->transactionId,
            'message'        => $exception?->getMessage(),
        ]);
    }
    /**
 * Decide final status for transactions that already reached awaiting_payout.
 * Source of truth = the Master→Ramp Quidax withdrawal (reference: {merchant}_ramp).
 */
protected function reconcileAwaitingPayout(
    RampTransaction $transaction,
    QuidaxService $quidaxService,
    $log
): void {
    $merchantReference = $transaction->merchant_reference;
    $rampReference     = $merchantReference . '_ramp';
    $currency          = strtolower(
        $transaction->from_currency
        ?? $transaction->source_currency
        ?? data_get($transaction->metadata, 'job.ramp_withdrawal.data.currency')
        ?? 'usdt'
    );

    $log->info('ReconcileRampTransaction: checking Master→Ramp withdrawal for awaiting_payout', [
        'merchant_reference' => $merchantReference,
        'ramp_reference'     => $rampReference,
        'currency'           => $currency,
    ]);

    try {
        $withdrawal = $quidaxService->findWithdrawalByReference(
            'me',
            $rampReference,
            $currency
        );
    } catch (Exception $e) {
        $log->warning('ReconcileRampTransaction: could not fetch Master→Ramp withdrawal', [
            'merchant_reference' => $merchantReference,
            'error'              => $e->getMessage(),
        ]);
        return; // retry later
    }

    if (!$withdrawal || empty($withdrawal)) {
        $log->warning('ReconcileRampTransaction: no Master→Ramp withdrawal found while in awaiting_payout', [
            'merchant_reference' => $merchantReference,
            'ramp_reference'     => $rampReference,
        ]);
        return;
    }

    $providerStatus = strtolower(trim(
        data_get($withdrawal, 'data.status')
        ?? data_get($withdrawal, 'status')
        ?? ''
    ));

    $log->info('ReconcileRampTransaction: Master→Ramp status resolved', [
        'merchant_reference' => $merchantReference,
        'provider_status'    => $providerStatus,
        'withdrawal_id'      => data_get($withdrawal, 'data.id'),
        'txid'               => data_get($withdrawal, 'data.txid'),
    ]);

    // ---------- SUCCESS ----------
    if (in_array($providerStatus, [
        'done', 'successful', 'success', 'completed', 'confirmed', 'paid',
    ], true)) {
        $this->markOffRampCompleted($transaction, $withdrawal, $log);
        return;
    }

    // ---------- FAILURE ----------
    if (in_array($providerStatus, [
        'failed', 'failure', 'rejected', 'cancelled', 'canceled', 'reversed',
    ], true)) {
        $this->markOffRampFailedAndReverse($transaction, $withdrawal, $quidaxService, $log);
        return;
    }

    // still processing / pending → leave it alone
  // Still processing / pending → schedule another reconciliation.
$log->info('ReconcileRampTransaction: Master→Ramp still pending', [
    'merchant_reference' => $merchantReference,
    'provider_status'    => $providerStatus,
]);

$transaction->refresh();

$this->scheduleNextReconciliation(
    $transaction,
    $log,
    60
);

return;
}
protected function scheduleNextReconciliation(
    RampTransaction $transaction,
    $log,
    int $delay = 60
): void {
    if (in_array($transaction->status, [
        'completed',
        'failed',
        'cancelled',
    ], true)) {
        return;
    }

    $log->info('ReconcileRampTransaction: scheduling next reconciliation', [
        'transaction_id'     => $transaction->id,
        'merchant_reference' => $transaction->merchant_reference,
        'status'             => $transaction->status,
        'delay_seconds'      => $delay,
    ]);

    self::dispatch($transaction->id)
        ->delay(now()->addSeconds($delay));
}
}