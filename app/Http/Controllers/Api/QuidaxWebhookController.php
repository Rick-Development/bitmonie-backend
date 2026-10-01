<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessQuidaxOfframpForCardFunding;
use App\Mail\CryptoCardFundingNotificationMail;
use App\Mail\CryptoDepositNotificationMail;
use App\Mail\CryptoWithdrawalFailedAdminMail;
use App\Mail\CryptoWithdrawalNotificationMail;
use App\Models\Admin\Admin;
use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardTransactions;
use App\Models\CryptoNotificationLog;
use App\Models\RampTransaction;
use App\Models\User;
use App\Models\WebhookEventLog;
use App\Models\Withdrawals;
use App\Services\WebhookAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class QuidaxWebhookController extends Controller
{
    /**
     * Handle incoming Quidax webhook.
     *
     * IMPORTANT:
     * - Always return HTTP 200 for a validly received webhook that has
     *   been safely recorded/reconciled.
     * - Invalid signatures are rejected when a webhook secret is configured.
     * - Financial mutations are protected by database transactions and locks.
     * - Notification delivery is idempotent through CryptoNotificationLog.
     */
    public function handle(
        Request $request,
        WebhookAuditService $audit
    ): JsonResponse {
        $eventLog = $audit->recordReceived($request, 'quidax');

        $rawPayload = $request->getContent();

        /*
         * Never process an invalid signature.
         *
         * The previous implementation only logged the failure and continued,
         * which is unsafe for a financial webhook.
         */
        if (!$this->verifySignatureIfConfigured($request, $rawPayload)) {
            Log::warning('Quidax webhook rejected: invalid signature.', [
                'event' => null,
                'event_id' => null,
                'ip' => $request->ip(),
            ]);

            $audit->markFailed(
                $eventLog,
                'Invalid Quidax webhook signature.'
            );

            return response()->json([
                'status' => 'invalid_signature',
            ], 401);
        }

        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            $payload = $request->all();
        }

        if (!is_array($payload) || $payload === []) {
            $audit->markFailed(
                $eventLog,
                'Malformed or empty Quidax webhook payload.'
            );

            return response()->json([
                'status' => 'error_logged',
            ], 200);
        }

        $eventName = $this->extractEventName($payload);

        $data = is_array($payload['data'] ?? null)
            ? $payload['data']
            : $payload;

        $reference = $this->extractTransactionReference($data);
        $eventId = $this->extractEventId($payload, $data);

        /*
         * Card Funding must be detected before the normal withdrawal logic.
         *
         * CARD-FUND-* references belong to the Crypto Card offramp flow.
         */
        if ($this->isCardFundingOfframp($data, $reference)) {
            return $this->handleCardFundingOfframp(
                $payload,
                $data,
                $eventName,
                $eventLog,
                $audit
            );
        }

        $providerStatus = $this->extractStatus($data);

        $transactionType = $this->isDepositEvent($eventName, $data)
            ? 'deposit'
            : (
                $this->isWithdrawalEvent($eventName, $data)
                    ? 'withdrawal'
                    : null
            );

        $internalStatus = match ($transactionType) {
            'deposit' => $this->isSuccessfulDeposit($eventName, $data)
                ? 'successful'
                : (
                    $this->isFailedDeposit($eventName, $data)
                        ? 'failed'
                        : 'pending'
                ),

            'withdrawal' => $this->normalizeWithdrawalStatus(
                $eventName,
                $data
            ),

            default => null,
        };

        $dedupeKey = $audit->dedupeKey(
            'quidax',
            $reference,
            $internalStatus ?: $providerStatus,
            $eventName
        );

        $audit->enrich($eventLog, [
            'event_name' => $eventName,
            'event_id' => $eventId,
            'dedupe_key' => $dedupeKey,
            'transaction_reference' => $reference,
            'transaction_type' => $transactionType,
            'provider_status' => $providerStatus,
            'internal_status' => $internalStatus,
        ]);

        /*
         * Webhook-level idempotency.
         */
        if ($duplicate = $audit->findProcessedDuplicate(
            $dedupeKey,
            $eventLog->id
        )) {
            $audit->markDuplicate($eventLog, $duplicate);

            return response()->json([
                'status' => 'duplicate',
            ], 200);
        }

        Log::info('Quidax webhook received.', [
            'event' => $eventName,
            'event_id' => $eventId,
            'reference' => $reference,
            'transaction_type' => $transactionType,
            'provider_status' => $providerStatus,
        ]);

        try {
            if ($this->isDepositEvent($eventName, $data)) {
                return $this->handleDeposit(
                    $payload,
                    $data,
                    $eventName,
                    $eventLog,
                    $audit
                );
            }

            if ($this->isWithdrawalEvent($eventName, $data)) {
                return $this->handleWithdrawal(
                    $payload,
                    $data,
                    $eventName,
                    $eventLog,
                    $audit
                );
            }
        } catch (Throwable $exception) {
            Log::error(
                'Quidax webhook processing failed after receipt.',
                [
                    'event' => $eventName,
                    'event_id' => $eventId,
                    'reference' => $reference,
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'trace' => config('app.debug')
                        ? $exception->getTraceAsString()
                        : null,
                ]
            );

            $audit->markFailed(
                $eventLog,
                $exception->getMessage()
            );

            /*
             * We intentionally return 200 because the event has been
             * persisted in the webhook audit log. A queue/reconciliation
             * mechanism can retry it internally without depending on
             * provider retries.
             */
            return response()->json([
                'status' => 'error_logged',
            ], 200);
        }

        $audit->markIgnored(
            $eventLog,
            'Unrecognised Quidax webhook event.'
        );

        return response()->json([
            'status' => 'ignored',
        ], 200);
    }

    /**
     * -------------------------------------------------------------
     * DEPOSITS
     * -------------------------------------------------------------
     */
    protected function handleDeposit(
        array $payload,
        array $data,
        string $eventName,
        WebhookEventLog $eventLog,
        WebhookAuditService $audit
    ): JsonResponse {
        if (!$this->isSuccessfulDeposit($eventName, $data)) {
            if ($this->isFailedDeposit($eventName, $data)) {
                return $this->handleFailedDeposit(
                    $payload,
                    $data,
                    $eventName,
                    $eventLog,
                    $audit
                );
            }

            $audit->markIgnored(
                $eventLog,
                'Deposit is not confirmed/successful yet.'
            );

            return response()->json([
                'status' => 'ignored',
            ], 200);
        }

        $user = $this->resolveUser($data);
        $details = $this->buildDepositDetails($data);

        $dedupeKey = $this->dedupeKey(
            'deposit',
            $eventName,
            $payload,
            $data
        );

        [$log, $duplicate] = $this->reserveNotificationLog(
            $payload,
            $data,
            $eventName,
            $dedupeKey,
            'crypto_deposit_success',
            $user
        );

        if ($duplicate) {
            $audit->markProcessed(
                $eventLog,
                'notification_already_processed'
            );

            return response()->json([
                'status' => 'duplicate',
            ], 200);
        }

        if (!$user || empty($user->email)) {
            $log->update([
                'status' => 'ignored',
                'error_message' => 'No matching local user/email for deposit webhook.',
            ]);

            $audit->recordReconciliation($eventLog, [
                'action' => 'deposit_user_not_found',
                'transaction_reference' => $details['transaction_reference'] ?? null,
                'currency' => strtolower(
                    (string) ($details['coin'] ?? '')
                ),
                'amount' => is_numeric($details['amount'] ?? null)
                    ? $details['amount']
                    : null,
                'status_after' => 'successful',
                'notes' => 'Deposit confirmed by provider but no matching local user was found.',
            ]);

            $audit->markIgnored(
                $eventLog,
                'No matching local user/email for deposit webhook.'
            );

            return response()->json([
                'status' => 'ignored',
            ], 200);
        }

        $this->queueMailSafely(
            $log,
            'crypto_deposit_success',
            $details['transaction_reference'] ?? null,
            fn () => Mail::to($user->email)
                ->queue(
                    new CryptoDepositNotificationMail(
                        $user,
                        $details
                    )
                )
        );

        $audit->enrich($eventLog, [
            'user_id' => $user->id,
        ]);

        $audit->recordReconciliation($eventLog, [
            'action' => 'quidax_deposit_confirmed',
            'user_id' => $user->id,
            'transaction_reference' => $details['transaction_reference'] ?? null,
            'currency' => strtolower(
                (string) ($details['coin'] ?? '')
            ),
            'amount' => is_numeric($details['amount'] ?? null)
                ? $details['amount']
                : null,
            'status_after' => 'successful',
            'notes' => 'Deposit confirmed by Quidax and notification queued.',
            'changes' => [
                'notification_log_id' => $log->id,
                'txid' => $details['txid'] ?? null,
            ],
        ]);

        $audit->markProcessed(
            $eventLog,
            'reconciled'
        );

        return response()->json([
            'status' => 'success',
        ], 200);
    }

    /**
     * Failed deposit.
     */
    protected function handleFailedDeposit(
        array $payload,
        array $data,
        string $eventName,
        WebhookEventLog $eventLog,
        WebhookAuditService $audit
    ): JsonResponse {
        $user = $this->resolveUser($data);

        $details = $this->buildDepositDetails($data);

        $details['status'] = 'failed';

        $details['failure_reason'] = $this->firstDataValue(
            $data,
            [
                'reason',
                'failure_reason',
                'reject_reason',
                'message',
            ]
        );

        $dedupeKey = $this->dedupeKey(
            'deposit_failed',
            $eventName,
            $payload,
            $data
        );

        [$log, $duplicate] = $this->reserveNotificationLog(
            $payload,
            $data,
            $eventName,
            $dedupeKey,
            'crypto_deposit_failed',
            $user
        );

        if ($duplicate) {
            $audit->markProcessed(
                $eventLog,
                'notification_already_processed'
            );

            return response()->json([
                'status' => 'duplicate',
            ], 200);
        }

        if (!$user || empty($user->email)) {
            $log->update([
                'status' => 'ignored',
                'error_message' => 'No matching local user/email for failed deposit webhook.',
            ]);

            $audit->recordReconciliation($eventLog, [
                'action' => 'deposit_failed_user_not_found',
                'transaction_reference' => $details['transaction_reference'] ?? null,
                'currency' => strtolower(
                    (string) ($details['coin'] ?? '')
                ),
                'amount' => is_numeric($details['amount'] ?? null)
                    ? $details['amount']
                    : null,
                'status_after' => 'failed',
                'notes' => 'Deposit failed by provider but no matching local user was found.',
            ]);

            $audit->markIgnored(
                $eventLog,
                'No matching local user/email for failed deposit webhook.'
            );

            return response()->json([
                'status' => 'ignored',
            ], 200);
        }

        $this->queueMailSafely(
            $log,
            'crypto_deposit_failed',
            $details['transaction_reference'] ?? null,
            fn () => Mail::to($user->email)
                ->queue(
                    new CryptoDepositNotificationMail(
                        $user,
                        $details
                    )
                )
        );

        $audit->enrich($eventLog, [
            'user_id' => $user->id,
        ]);

        $audit->recordReconciliation($eventLog, [
            'action' => 'quidax_deposit_failed',
            'user_id' => $user->id,
            'transaction_reference' => $details['transaction_reference'] ?? null,
            'currency' => strtolower(
                (string) ($details['coin'] ?? '')
            ),
            'amount' => is_numeric($details['amount'] ?? null)
                ? $details['amount']
                : null,
            'status_after' => 'failed',
            'notes' => 'Failed crypto deposit notification queued.',
            'changes' => [
                'notification_log_id' => $log->id,
                'txid' => $details['txid'] ?? null,
            ],
        ]);

        $audit->markProcessed(
            $eventLog,
            'reconciled'
        );

        return response()->json([
            'status' => 'success',
        ], 200);
    }

    /**
     * -------------------------------------------------------------
     * WITHDRAWALS
     * -------------------------------------------------------------
     */
    protected function handleWithdrawal(
        array $payload,
        array $data,
        string $eventName,
        WebhookEventLog $eventLog,
        WebhookAuditService $audit
    ): JsonResponse {
        $status = $this->normalizeWithdrawalStatus(
            $eventName,
            $data
        );

        if ($status === null) {
            $audit->markIgnored(
                $eventLog,
                'Unrecognised Quidax withdrawal status.'
            );

            return response()->json([
                'status' => 'ignored',
            ], 200);
        }

        $withdrawal = $this->resolveWithdrawal($data);

        $user = $withdrawal?->user_id
            ? User::find($withdrawal->user_id)
            : $this->resolveUser($data);

        $details = $this->buildWithdrawalDetails(
            $data,
            $status,
            $user,
            $withdrawal
        );

        $statusBefore = $withdrawal
            ? data_get($withdrawal->wallet, 'status')
            : null;

        /*
         * Local withdrawal reconciliation.
         */
        if ($withdrawal) {
            DB::transaction(function () use (
                $withdrawal,
                $status,
                $data,
                $details
            ) {
                $lockedWithdrawal = Withdrawals::query()
                    ->whereKey($withdrawal->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedWithdrawal) {
                    $this->updateLocalWithdrawalStatus(
                        $lockedWithdrawal,
                        $status,
                        $data,
                        $details
                    );
                }
            });

            $withdrawal->refresh();
        }

        /*
         * Ramp transaction reconciliation.
         */
        $rampTransaction =
            $this->reconcileRampTransactionFromWithdrawalWebhook(
                $data,
                $status,
                $details
            );

        if (!$user && $rampTransaction?->user_id) {
            $user = User::find($rampTransaction->user_id);

            $details['user_id'] = $user?->id;
        }

        $audit->enrich($eventLog, [
            'user_id' => $user?->id,
        ]);

        $audit->recordReconciliation($eventLog, [
            'action' => 'quidax_withdrawal_status_reconciled',
            'transaction_type' => 'withdrawal',
            'transaction_id' => $withdrawal?->id
                ?? $rampTransaction?->id,
            'transaction_reference' =>
                $details['transaction_reference'] ?? null,
            'user_id' => $user?->id,
            'currency' => strtolower(
                (string) ($details['coin'] ?? '')
            ),
            'amount' => is_numeric($details['amount'] ?? null)
                ? $details['amount']
                : null,
            'status_before' => $statusBefore,
            'status_after' => $status,
            'notes' => $withdrawal
                ? 'Local withdrawal status reconciled from Quidax webhook.'
                : (
                    $rampTransaction
                        ? 'Local ramp sell transaction reconciled from Quidax withdrawal webhook.'
                        : 'Quidax withdrawal webhook received but no matching local withdrawal was found.'
                ),
        ]);

        /*
         * User notification.
         */
        if ($this->shouldSendUserWithdrawalNotification(
            $status,
            $details
        )) {
            $dedupeKey = $this->withdrawalNotificationDedupeKey(
                $details['transaction_reference'] ?? '',
                $status,
                'user'
            );

            [$log, $duplicate] = $this->reserveNotificationLog(
                $payload,
                $data,
                $eventName,
                $dedupeKey,
                "crypto_withdrawal_{$status}",
                $user
            );

            if (!$duplicate && $user && !empty($user->email)) {
                $this->queueMailSafely(
                    $log,
                    "crypto_withdrawal_{$status}",
                    $details['transaction_reference'] ?? null,
                    fn () => Mail::to($user->email)
                        ->queue(
                            new CryptoWithdrawalNotificationMail(
                                $user,
                                $details
                            )
                        )
                );
            } elseif (!$duplicate) {
                $log->update([
                    'status' => 'ignored',
                    'error_message' =>
                        'No matching local user/email for withdrawal webhook.',
                ]);
            }

            $audit->markProcessed(
                $eventLog,
                'reconciled'
            );

            return response()->json([
                'status' => $duplicate
                    ? 'duplicate'
                    : 'success',
            ], 200);
        }

        /*
         * Intermediate statuses do not send user notification.
         */
        if (in_array(
            $status,
            ['initiated', 'processing'],
            true
        )) {
            $audit->markProcessed(
                $eventLog,
                'reconciled'
            );

            return response()->json([
                'status' => 'success',
            ], 200);
        }

        /*
         * Failed withdrawals.
         *
         * Notify both:
         * 1. the affected user;
         * 2. active administrators.
         */
        if ($status === 'failed') {
            $userDedupeKey =
                $this->withdrawalNotificationDedupeKey(
                    $details['transaction_reference'] ?? '',
                    'failed',
                    'user'
                );

            [$userLog, $userDuplicate] =
                $this->reserveNotificationLog(
                    $payload,
                    $data,
                    $eventName,
                    $userDedupeKey,
                    'crypto_withdrawal_failed',
                    $user
                );

            if (
                !$userDuplicate &&
                $user &&
                !empty($user->email)
            ) {
                $this->queueMailSafely(
                    $userLog,
                    'crypto_withdrawal_failed',
                    $details['transaction_reference'] ?? null,
                    fn () => Mail::to($user->email)
                        ->queue(
                            new CryptoWithdrawalNotificationMail(
                                $user,
                                $details
                            )
                        )
                );
            } elseif (!$userDuplicate) {
                $userLog->update([
                    'status' => 'ignored',
                    'error_message' =>
                        'No matching local user/email for failed withdrawal webhook.',
                ]);
            }

            /*
             * Admin notification.
             */
            $adminDedupeKey =
                $this->withdrawalNotificationDedupeKey(
                    $details['transaction_reference'] ?? '',
                    'failed',
                    'admin'
                );

            [$adminLog, $adminDuplicate] =
                $this->reserveNotificationLog(
                    $payload,
                    $data,
                    $eventName,
                    $adminDedupeKey,
                    'crypto_withdrawal_failed_admin',
                    $user
                );

            if (!$adminDuplicate) {
                $adminQueued = $this->notifyAdminsOfFailedWithdrawal(
                    $details
                );

                $adminLog->update([
                    'status' => $adminQueued
                        ? 'queued'
                        : 'failed',
                    'sent_at' => $adminQueued
                        ? now()
                        : null,
                    'error_message' => $adminQueued
                        ? null
                        : 'No administrator notification could be queued.',
                ]);
            }

            $audit->markProcessed(
                $eventLog,
                'reconciled'
            );

            return response()->json([
                'status' => (
                    $userDuplicate &&
                    $adminDuplicate
                )
                    ? 'duplicate'
                    : 'success',
            ], 200);
        }

        $audit->markIgnored(
            $eventLog,
            'Withdrawal status did not require notification.'
        );

        return response()->json([
            'status' => 'ignored',
        ], 200);
    }

    /**
     * -------------------------------------------------------------
     * CARD FUNDING / OFF-RAMP
     * -------------------------------------------------------------
     *
     * CARD-FUND-* transactions represent crypto being sold/withdrawn
     * through Quidax in order to fund a crypto card.
     */
    protected function isCardFundingOfframp(
        array $data,
        ?string $reference
    ): bool {
        $merchantReference = $this->firstDataValue(
            $data,
            [
                'merchant_reference',
                'reference',
                'transaction_reference',
            ]
        ) ?? $reference;

        if (!$merchantReference) {
            return false;
        }

        return Str::startsWith(
            strtoupper(trim($merchantReference)),
            'CARD-FUND-'
        );
    }

    /**
     * Handle Card Funding webhook.
     *
     * Financially critical path:
     *
     * Quidax success
     *      ↓
     * lock transaction
     *      ↓
     * lock card
     *      ↓
     * verify not already completed
     *      ↓
     * credit card exactly once
     *      ↓
     * complete escrow
     *      ↓
     * save provider reconciliation
     *      ↓
     * queue notification after commit
     */
    protected function handleCardFundingOfframp(
        array $payload,
        array $data,
        string $eventName,
        WebhookEventLog $eventLog,
        WebhookAuditService $audit
    ): JsonResponse {
        $merchantReference = $this->firstDataValue($data, [
            'merchant_reference',
            'reference',
            'transaction_reference',
        ]);

        $status = $this->normalizeStatus(
            $this->extractStatus($data)
        );

        Log::info('Quidax card funding webhook received.', [
            'merchant_reference' => $merchantReference,
            'status' => $status,
            'event' => $eventName,
        ]);

        if (!$merchantReference) {
            $audit->markIgnored(
                $eventLog,
                'Card funding webhook does not contain a merchant reference.'
            );

            return response()->json([
                'status' => 'ignored',
            ], 200);
        }

        $transaction = CryptoCardTransactions::query()
            ->where('metadata->quidax_merchant_reference', $merchantReference)
            ->first();

        if (!$transaction) {
            /*
             * Do not repeatedly queue a job for something we cannot reconcile.
             */
            Log::warning(
                'Quidax card funding transaction not found.',
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

        $audit->enrich($eventLog, [
            'event_name' => $eventName,
            'event_id' => $this->extractEventId($payload, $data),
            'transaction_reference' => $merchantReference,
            'transaction_type' => 'card_funding',
            'provider_status' => $this->extractStatus($data),
            'internal_status' => $this->isSuccessfulCardFundingStatus($status)
                ? 'successful'
                : (
                    $this->isFailedCardFundingStatus($status)
                        ? 'failed'
                        : 'processing'
                ),
            'user_id' => $this->resolveUser($data)?->id,
        ]);

        /*
         * Use the provider event ID/reference as the audit idempotency key.
         */
        $dedupeKey = $audit->dedupeKey(
            'quidax',
            $merchantReference,
            $status ?: 'unknown',
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
         * Unknown/intermediate provider states are accepted but not
         * financially processed.
         */
        if (
            !$this->isSuccessfulCardFundingStatus($status)
            && !$this->isFailedCardFundingStatus($status)
        ) {
            $audit->markIgnored(
                $eventLog,
                'Card funding is still processing.'
            );

            return response()->json([
                'status' => 'processing',
            ], 200);
        }

        /*
         * Queue the financial reconciliation.
         *
         * The webhook itself remains fast.
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
            'action' => 'quidax_card_funding_job_dispatched',
            'transaction_type' => 'card_funding',
            'transaction_id' => $transaction->id,
            'transaction_reference' => $merchantReference,
            'status_after' => $this->isSuccessfulCardFundingStatus($status)
                ? 'queued_success'
                : 'queued_failure',
            'notes' => 'Card funding webhook queued for atomic reconciliation and notification dispatch.',
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
     * Resolve the user associated with a Card Funding transaction.
     */
    protected function resolveCardTransactionUser(
        CryptoCardTransactions $transaction,
        $card = null
    ): ?User {
        /*
         * Prefer an explicit user relationship if the model provides one.
         */
        try {
            if (
                method_exists($transaction, 'user') &&
                $transaction->user
            ) {
                return $transaction->user;
            }
        } catch (Throwable $e) {
            Log::debug(
                'Unable to resolve Card Funding transaction user relation.',
                [
                    'transaction_id' => $transaction->id,
                    'message' => $e->getMessage(),
                ]
            );
        }

        $userId =
            $transaction->user_id
            ?? data_get($transaction->metadata, 'user_id')
            ?? data_get($transaction->metadata, 'customer_id');

        if ($userId) {
            $user = User::find($userId);

            if ($user) {
                return $user;
            }
        }

        /*
         * Try card owner.
         */
        if ($card) {
            try {
                if (
                    method_exists($card, 'user') &&
                    $card->user
                ) {
                    return $card->user;
                }
            } catch (Throwable $e) {
                Log::debug(
                    'Unable to resolve Card owner relationship.',
                    [
                        'card_id' => $card->id ?? null,
                        'message' => $e->getMessage(),
                    ]
                );
            }

            if (!empty($card->user_id)) {
                return User::find($card->user_id);
            }
        }

        return null;
    }

    /**
     * Build notification details for Card Funding.
     */
    protected function buildCardFundingDetails(
        array $data,
        CryptoCardTransactions $transaction,
        $card,
        string $status
    ): array {
        $metadata = is_array($transaction->metadata)
            ? $transaction->metadata
            : [];

        return [
            'amount' => (string) (
                $transaction->amount
                ?? $this->firstDataValue(
                    $data,
                    ['amount', 'value']
                )
                ?? '0'
            ),

            'coin' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'currency',
                        'currency_code',
                        'coin',
                    ]
                )
                ?? data_get($metadata, 'coin')
                ?? ''
            ),

            'status' => $status,

            'transaction_reference' =>
                $this->extractTransactionReference($data)
                ?? data_get(
                    $metadata,
                    'quidax_merchant_reference'
                ),

            'merchant_reference' =>
                data_get(
                    $metadata,
                    'quidax_merchant_reference'
                ),

            'transaction_hash' =>
                $this->extractTransactionHash($data)
                ?? data_get(
                    $metadata,
                    'transaction_hash'
                ),

            'card_id' => $card?->id,

            'failure_reason' =>
                $this->firstDataValue(
                    $data,
                    [
                        'reason',
                        'failure_reason',
                        'reject_reason',
                        'message',
                    ]
                ),

            'timestamp' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'done_at',
                        'completed_at',
                        'updated_at',
                        'created_at',
                    ]
                )
                ?? now()->toDateTimeString()
            ),
        ];
    }

    /**
     * Queue Card Funding notification exactly once.
     */
    protected function queueCardFundingNotification(
        array $payload,
        array $data,
        string $eventName,
        CryptoCardTransactions $transaction,
        ?User $user,
        array $details,
        string $notificationStatus
    ): void {
        /*
         * The notification must be deduplicated independently of the
         * webhook event because Quidax may deliver the same business event
         * under multiple webhook event IDs.
         */
        $reference =
            $details['merchant_reference']
            ?? $details['transaction_reference']
            ?? 'transaction-' . $transaction->id;

        $dedupeKey = sha1(
            implode('|', [
                'quidax',
                'crypto_card_funding',
                strtolower($notificationStatus),
                strtolower((string) $reference),
                (string) $transaction->id,
            ])
        );

        [$log, $duplicate] = $this->reserveNotificationLog(
            $payload,
            $data,
            $eventName,
            $dedupeKey,
            'crypto_card_funding_' . $notificationStatus,
            $user
        );

        if ($duplicate) {
            return;
        }

        if (!$user || empty($user->email)) {
            $log->update([
                'status' => 'ignored',
                'error_message' =>
                    'Card Funding completed but no user email was found.',
            ]);

            Log::warning(
                'Card Funding notification skipped: user/email unavailable.',
                [
                    'transaction_id' =>
                        $transaction->id,
                    'card_id' =>
                        $details['card_id'] ?? null,
                    'reference' =>
                        $reference,
                ]
            );

            return;
        }

        /*
         * Mail::queue() dispatches the mail to Laravel's queue.
         *
         * The mail itself should implement ShouldQueue or the application
         * queue configuration should support queued mail.
         */
        $this->queueMailSafely(
            $log,
            'crypto_card_funding_' . $notificationStatus,
            (string) $reference,
            fn () => Mail::to($user->email)
                ->queue(
                    new CryptoCardFundingNotificationMail(
                        $user,
                        $details
                    )
                )
        );
    }

    /**
     * -------------------------------------------------------------
     * NOTIFICATION LOGGING
     * -------------------------------------------------------------
     */
    protected function reserveNotificationLog(
        array $payload,
        array $data,
        string $eventName,
        string $dedupeKey,
        string $notificationType,
        ?User $user = null
    ): array {
        /*
         * This must ideally have a UNIQUE index on dedupe_key.
         *
         * The transaction/exception handling below also protects against
         * duplicate-key races.
         */
        try {
            $existing = CryptoNotificationLog::query()
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'duplicate' => true,
                    'duplicate_count' =>
                        ((int) $existing->duplicate_count) + 1,
                ])->save();

                Log::info(
                    'Duplicate Quidax notification suppressed.',
                    [
                        'event' => $eventName,
                        'notification_type' =>
                            $notificationType,
                        'dedupe_key' => $dedupeKey,
                        'duplicate_count' =>
                            $existing->duplicate_count,
                    ]
                );

                return [$existing, true];
            }

            $log = CryptoNotificationLog::create([
                'provider' => 'quidax',
                'event_name' => $eventName,
                'event_id' =>
                    $this->extractEventId(
                        $payload,
                        $data
                    ),
                'dedupe_key' => $dedupeKey,
                'transaction_reference' =>
                    $this->extractTransactionReference($data),
                'user_id' => $user?->id,
                'payload' => $payload,
                'notification_type' =>
                    $notificationType,
                'status' => 'processing',
                'duplicate' => false,
                'duplicate_count' => 0,
            ]);

            return [$log, false];
        } catch (Throwable $exception) {
            /*
             * A unique constraint race means another worker inserted
             * the notification log first.
             */
            $existing = CryptoNotificationLog::query()
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existing) {
                $existing->increment(
                    'duplicate_count'
                );

                $existing->update([
                    'duplicate' => true,
                ]);

                return [$existing, true];
            }

            throw $exception;
        }
    }

    /**
     * Queue mail and record the queue result.
     */
    protected function queueMailSafely(
        CryptoNotificationLog $log,
        string $notificationType,
        ?string $reference,
        callable $queueCallback
    ): bool {
        try {
            $queueCallback();

            $log->update([
                'status' => 'queued',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            Log::info(
                'Crypto notification email queued.',
                [
                    'notification_type' =>
                        $notificationType,
                    'reference' => $reference,
                    'notification_log_id' =>
                        $log->id,
                ]
            );

            return true;
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' =>
                    $exception->getMessage(),
            ]);

            Log::error(
                'Crypto notification email queue failed.',
                [
                    'notification_type' =>
                        $notificationType,
                    'reference' => $reference,
                    'notification_log_id' =>
                        $log->id,
                    'message' =>
                        $exception->getMessage(),
                    'exception' =>
                        get_class($exception),
                ]
            );

            return false;
        }
    }

    /**
     * Queue admin failed-withdrawal notifications.
     *
     * Returns true only when at least one administrator email was queued.
     */
    protected function notifyAdminsOfFailedWithdrawal(
        array $details
    ): bool {
        $adminEmails = Admin::query()
            ->where('status', true)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->pluck('email')
            ->filter()
            ->map(
                fn ($email) => strtolower(trim($email))
            )
            ->unique()
            ->values()
            ->all();

        if (
            $adminEmails === [] &&
            config('mail.from.address')
        ) {
            $adminEmails = [
                config('mail.from.address'),
            ];
        }

        if ($adminEmails === []) {
            Log::warning(
                'No admin email available for failed withdrawal notification.',
                [
                    'reference' =>
                        $details['transaction_reference']
                        ?? null,
                ]
            );

            return false;
        }

        $queued = false;

        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)
                    ->queue(
                        new CryptoWithdrawalFailedAdminMail(
                            $details
                        )
                    );

                $queued = true;
            } catch (Throwable $exception) {
                Log::error(
                    'Failed to queue admin crypto withdrawal failure email.',
                    [
                        'email' => $email,
                        'reference' =>
                            $details['transaction_reference']
                            ?? null,
                        'message' =>
                            $exception->getMessage(),
                        'exception' =>
                            get_class($exception),
                    ]
                );
            }
        }

        return $queued;
    }

    /**
     * -------------------------------------------------------------
     * LOCAL WITHDRAWAL RECONCILIATION
     * -------------------------------------------------------------
     */
    protected function updateLocalWithdrawalStatus(
        Withdrawals $withdrawal,
        string $status,
        array $data,
        array $details
    ): void {
        $walletMeta = is_array($withdrawal->wallet)
            ? $withdrawal->wallet
            : [];

        $walletMeta['status'] = $status;

        $walletMeta['provider_status'] =
            $this->extractStatus($data);

        $walletMeta['provider_event_id'] =
            $this->extractEventId([], $data);

        $userMeta = is_array($withdrawal->user)
            ? $withdrawal->user
            : [];

        $userMeta['status'] = $status;

        $recipientData =
            is_array($withdrawal->recipient_data)
                ? $withdrawal->recipient_data
                : [];

        $reference =
            $this->extractTransactionReference($data)
            ?? $withdrawal->reference
            ?? $withdrawal->id;

        $providerWithdrawalId =
            $this->firstDataValue(
                $data,
                [
                    'id',
                    'uuid',
                    'provider_id',
                    'withdrawal_id',
                ]
            );

        $txHash =
            ($details['transaction_hash'] ?? null)
            ?: $this->extractTransactionHash($data)
            ?: $this->validStoredTransactionHash(
                $walletMeta['transaction_hash'] ?? null
            )
            ?: $this->validStoredTransactionHash(
                $walletMeta['txid'] ?? null
            )
            ?: $this->validStoredTransactionHash(
                $withdrawal->trans_id ?? null,
                [
                    $providerWithdrawalId,
                    $reference,
                ]
            );

        $recipientAddress =
            ($details['recipient_address'] ?? null)
            ?: $this->extractRecipientAddress($data)
            ?: $this->extractRecipientAddress(
                $recipientData
            )
            ?: $this->extractRecipientAddress(
                $walletMeta
            );

        $network =
            ($details['network'] ?? null)
            ?: $this->extractNetwork($data)
            ?: $this->extractNetwork($recipientData)
            ?: $this->extractNetwork($walletMeta);

        $transId = $withdrawal->trans_id;

        if ($txHash) {
            $walletMeta['transaction_hash'] = $txHash;
            $walletMeta['txid'] = $txHash;
        }

        if ($providerWithdrawalId) {
            $walletMeta['provider_withdrawal_id'] =
                $providerWithdrawalId;
        }

        if ($recipientAddress) {
            data_set(
                $recipientData,
                'details.address',
                $recipientAddress
            );

            $recipientData['address'] =
                $recipientData['address']
                ?? $recipientAddress;

            $walletMeta['recipient_address'] =
                $recipientAddress;
        }

        if ($network) {
            $normalizedNetwork = strtolower(
                (string) $network
            );

            data_set(
                $recipientData,
                'details.network',
                $normalizedNetwork
            );

            $walletMeta['network'] =
                $normalizedNetwork;
        }

        if ($status === 'completed') {
            $transId =
                $txHash
                ?: $this->extractEventId([], $data)
                ?: $reference;

            $walletMeta['reservation_released_at'] =
                now()->toDateTimeString();

            $walletMeta['reservation_release_reason'] =
                'withdrawal_completed';
        } elseif ($status === 'failed') {
            $transId =
                Str::startsWith(
                    (string) $withdrawal->trans_id,
                    'failed:'
                )
                    ? $withdrawal->trans_id
                    : 'failed:' . $reference;

            $walletMeta['failure_reason'] =
                data_get($data, 'reason')
                ?? data_get(
                    $data,
                    'failure_reason'
                )
                ?? data_get(
                    $data,
                    'reject_reason'
                );

            $walletMeta['reservation_released_at'] =
                now()->toDateTimeString();

            $walletMeta['reservation_release_reason'] =
                'withdrawal_failed';
        } elseif (
            !$transId ||
            Str::startsWith(
                (string) $transId,
                [
                    'pending:',
                    'processing:',
                    'initiated:',
                ]
            )
        ) {
            $transId =
                "{$status}:{$reference}";
        }

        $withdrawal->update([
            'trans_id' => $transId,
            'recipient_data' => $recipientData,
            'wallet' => $walletMeta,
            'user' => $userMeta,
        ]);
    }

    /**
     * -------------------------------------------------------------
     * RAMP RECONCILIATION
     * -------------------------------------------------------------
     */
    protected function reconcileRampTransactionFromWithdrawalWebhook(
        array $data,
        string $status,
        array $details
    ): ?RampTransaction {
        [$transaction, $withdrawalRole] =
            $this->resolveRampTransactionFromWithdrawalData(
                $data
            );

        if (!$transaction) {
            return null;
        }

        $transaction = DB::transaction(
            function () use (
                $transaction,
                $withdrawalRole,
                $data,
                $status,
                $details
            ) {
                $transaction = RampTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    return null;
                }

                $metadata = is_array(
                    $transaction->metadata
                )
                    ? $transaction->metadata
                    : [];

                $jobMetadata =
                    is_array($metadata['job'] ?? null)
                        ? $metadata['job']
                        : [];

                $jobKey =
                    $withdrawalRole === 'main'
                        ? 'main_account_withdrawal'
                        : 'ramp_withdrawal';

                $jobWithdrawal =
                    is_array(
                        $jobMetadata[$jobKey] ?? null
                    )
                        ? $jobMetadata[$jobKey]
                        : [];

                $jobWithdrawalData =
                    is_array(
                        $jobWithdrawal['data'] ?? null
                    )
                        ? $jobWithdrawal['data']
                        : [];

                $providerWithdrawalId =
                    $this->firstDataValue(
                        $data,
                        [
                            'id',
                            'uuid',
                            'provider_id',
                            'withdrawal_id',
                        ]
                    );

                $transactionHash =
                    ($details['transaction_hash'] ?? null)
                    ?: $this->extractTransactionHash($data)
                    ?: $this->validStoredTransactionHash(
                        $transaction->transaction_hash ?? null
                    );

                $recipientAddress =
                    ($details['recipient_address'] ?? null)
                    ?: $this->extractRecipientAddress($data)
                    ?: (string) (
                        $transaction->wallet_address
                        ?? ''
                    );

                $network =
                    ($details['network'] ?? null)
                    ?: $this->extractNetwork($data)
                    ?: (string) (
                        $transaction->network
                        ?? ''
                    );

                $jobWithdrawal['data'] =
                    array_merge(
                        $jobWithdrawalData,
                        $data
                    );

                $jobWithdrawal['webhook_status'] =
                    $status;

                $jobWithdrawal[
                    'webhook_received_at'
                ] = now()->toIso8601String();

                if ($transactionHash) {
                    data_set(
                        $jobWithdrawal,
                        'data.txid',
                        $transactionHash
                    );

                    data_set(
                        $jobWithdrawal,
                        'data.transaction_hash',
                        $transactionHash
                    );
                }

                if ($recipientAddress) {
                    data_set(
                        $jobWithdrawal,
                        'data.recipient.details.address',
                        $recipientAddress
                    );
                }

                if ($network) {
                    data_set(
                        $jobWithdrawal,
                        'data.recipient.details.network',
                        strtolower(
                            (string) $network
                        )
                    );
                }

                $jobMetadata[$jobKey] =
                    $jobWithdrawal;

                $metadata['job'] =
                    $jobMetadata;

                $metadata[
                    'quidax_withdrawal_webhook'
                ] = [
                    'role' =>
                        $withdrawalRole,
                    'status' =>
                        $status,
                    'provider_status' =>
                        $this->extractStatus(
                            $data
                        ),
                    'provider_withdrawal_id' =>
                        $providerWithdrawalId,
                    'transaction_hash' =>
                        $transactionHash,
                    'recipient_address' =>
                        $recipientAddress
                            ?: null,
                    'received_at' =>
                        now()->toIso8601String(),
                ];

                $updates = [
                    'metadata' => $metadata,
                ];

                if ($withdrawalRole === 'ramp') {
                    if ($status === 'failed') {
                        $updates['status'] =
                            'failed';

                        $metadata[
                            'failure_reason'
                        ] =
                            data_get(
                                $data,
                                'reason'
                            )
                            ?? data_get(
                                $data,
                                'failure_reason'
                            )
                            ?? data_get(
                                $data,
                                'reject_reason'
                            )
                            ?? 'Quidax ramp withdrawal failed.';

                        $updates['metadata'] =
                            $metadata;
                    } elseif (
                        $status === 'completed' &&
                        !in_array(
                            (string) $transaction->status,
                            [
                                'completed',
                                'failed',
                            ],
                            true
                        )
                    ) {
                        $updates['status'] =
                            'awaiting_payout';
                    }

                    if (
                        $transactionHash &&
                        $this->hasRampTransactionColumn(
                            'transaction_hash'
                        )
                    ) {
                        $updates['transaction_hash'] =
                            $transactionHash;
                    }

                    if ($recipientAddress) {
                        $updates['wallet_address'] =
                            $recipientAddress;
                    }

                    if ($network) {
                        $updates['network'] =
                            strtolower(
                                (string) $network
                            );
                    }
                }

                if (
                    $providerWithdrawalId &&
                    $this->hasRampTransactionColumn(
                        'provider_transaction_id'
                    ) &&
                    empty(
                        $transaction->provider_transaction_id
                    )
                ) {
                    $updates[
                        'provider_transaction_id'
                    ] = $providerWithdrawalId;
                }

                if (
                    $this->hasRampTransactionColumn(
                        'provider_status'
                    )
                ) {
                    $updates['provider_status'] =
                        $this->extractStatus($data)
                        ?: $status;
                }

                if (
                    $this->hasRampTransactionColumn(
                        'provider_status_checked_at'
                    )
                ) {
                    $updates[
                        'provider_status_checked_at'
                    ] = now();
                }

                $transaction->update(
                    $updates
                );

                return $transaction->fresh();
            },
            5
        );

        if ($transaction) {
            Log::info(
                'Quidax withdrawal webhook reconciled ramp transaction.',
                [
                    'ramp_transaction_id' =>
                        $transaction->id,
                    'merchant_reference' =>
                        $transaction->merchant_reference,
                    'withdrawal_role' =>
                        $withdrawalRole,
                    'status' => $status,
                ]
            );
        }

        return $transaction;
    }

    protected function resolveRampTransactionFromWithdrawalData(
        array $data
    ): array {
        $reference =
            $this->extractTransactionReference(
                $data
            );

        $providerId =
            $this->firstDataValue(
                $data,
                [
                    'id',
                    'uuid',
                    'provider_id',
                    'withdrawal_id',
                ]
            );

        $txHash =
            $this->extractTransactionHash(
                $data
            );

        $values = array_values(
            array_unique(
                array_filter([
                    $reference,
                    $providerId,
                    $txHash,
                ])
            )
        );

        if ($values === []) {
            return [null, null];
        }

        $query = RampTransaction::query()
            ->where(
                'type',
                'off_ramp'
            )
            ->where(
                function ($query) use ($values) {
                    foreach ($values as $value) {
                        if (
                            $this->hasRampTransactionColumn(
                                'provider_transaction_id'
                            )
                        ) {
                            $query->orWhere(
                                'provider_transaction_id',
                                $value
                            );
                        }

                        if (
                            $this->hasRampTransactionColumn(
                                'transaction_hash'
                            )
                        ) {
                            $query->orWhere(
                                'transaction_hash',
                                $value
                            );
                        }

                        $query
                            ->orWhere(
                                'reference',
                                $value
                            )
                            ->orWhere(
                                'public_id',
                                $value
                            )
                            ->orWhere(
                                'merchant_reference',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->ramp_withdrawal->data->id',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->ramp_withdrawal->data->uuid',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->ramp_withdrawal->data->reference',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->ramp_withdrawal->data->txid',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->ramp_withdrawal->data->transaction_hash',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->main_account_withdrawal->data->id',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->main_account_withdrawal->data->uuid',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->main_account_withdrawal->data->reference',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->main_account_withdrawal->data->txid',
                                $value
                            )
                            ->orWhere(
                                'metadata->job->main_account_withdrawal->data->transaction_hash',
                                $value
                            );
                    }
                }
            );

        $transaction =
            $query
                ->latest('id')
                ->first();

        if (!$transaction) {
            return [null, null];
        }

        $metadata = is_array(
            $transaction->metadata
        )
            ? $transaction->metadata
            : [];

        $role = 'ramp';

        foreach ($values as $value) {
            $mainValues = array_filter([
                data_get(
                    $metadata,
                    'main_account_withdrawal.data.id'
                ),
                data_get(
                    $metadata,
                    'job.main_account_withdrawal.data.id'
                ),
                data_get(
                    $metadata,
                    'job.main_account_withdrawal.data.uuid'
                ),
                data_get(
                    $metadata,
                    'job.main_account_withdrawal.data.reference'
                ),
                data_get(
                    $metadata,
                    'job.main_account_withdrawal.data.txid'
                ),
                data_get(
                    $metadata,
                    'job.main_account_withdrawal.data.transaction_hash'
                ),
            ]);

            if (
                in_array(
                    $value,
                    $mainValues,
                    true
                )
            ) {
                $role = 'main';
                break;
            }
        }

        return [
            $transaction,
            $role,
        ];
    }

    protected function hasRampTransactionColumn(
        string $column
    ): bool {
        static $columns = null;

        if ($columns === null) {
            $columns =
                Schema::hasTable(
                    'ramp_transactions'
                )
                    ? Schema::getColumnListing(
                        'ramp_transactions'
                    )
                    : [];
        }

        return in_array(
            $column,
            $columns,
            true
        );
    }

    /**
     * -------------------------------------------------------------
     * DETAILS / RESOLUTION
     * -------------------------------------------------------------
     */
    protected function buildDepositDetails(
        array $data
    ): array {
        return [
            'amount' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'amount',
                        'value',
                        'confirmed_amount',
                    ]
                ) ?? '0'
            ),

            'coin' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'currency',
                        'currency_code',
                        'coin',
                    ]
                ) ?? ''
            ),

            'transaction_reference' =>
                $this->extractTransactionReference(
                    $data
                ),

            'wallet_address' =>
                $this->firstDataValue(
                    $data,
                    [
                        'address',
                        'wallet_address',
                        'payment_address.address',
                        'wallet.address',
                        'destination.address',
                    ]
                ),

            'status' =>
                $this->normalizeStatus(
                    $this->extractStatus($data)
                ) ?: null,

            'timestamp' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'done_at',
                        'confirmed_at',
                        'completed_at',
                        'updated_at',
                        'created_at',
                    ]
                )
                ?? now()->toDateTimeString()
            ),

            'txid' =>
                $this->extractTransactionHash(
                    $data
                ),
        ];
    }

    protected function buildWithdrawalDetails(
        array $data,
        string $status,
        ?User $user = null,
        ?Withdrawals $withdrawal = null
    ): array {
        $recipientData =
            is_array($withdrawal?->recipient_data)
                ? $withdrawal->recipient_data
                : [];

        $walletMeta =
            is_array($withdrawal?->wallet)
                ? $withdrawal->wallet
                : [];

        return [
            'user_id' =>
                $user?->id
                ?? $withdrawal?->user_id,

            'amount' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'amount',
                        'value',
                    ]
                )
                ?? $withdrawal?->amount
                ?? '0'
            ),

            'coin' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'currency',
                        'currency_code',
                        'coin',
                    ]
                )
                ?? $withdrawal?->currency
                ?? ''
            ),

            'network' => (string) (
                $this->extractNetwork($data)
                ?? $this->extractNetwork(
                    $recipientData
                )
                ?? $this->extractNetwork(
                    $walletMeta
                )
                ?? ''
            ),

            'recipient_address' =>
                $this->extractRecipientAddress(
                    $data
                )
                ?? $this->extractRecipientAddress(
                    $recipientData
                )
                ?? $this->extractRecipientAddress(
                    $walletMeta
                ),

            'transaction_hash' =>
                $this->extractTransactionHash(
                    $data
                )
                ?? $this->validStoredTransactionHash(
                    $walletMeta['transaction_hash']
                    ?? null
                )
                ?? $this->validStoredTransactionHash(
                    $walletMeta['txid']
                    ?? null
                )
                ?? $this->validStoredTransactionHash(
                    $withdrawal?->trans_id,
                    [
                        $walletMeta[
                            'provider_withdrawal_id'
                        ] ?? null,
                        $withdrawal?->reference,
                    ]
                ),

            'transaction_reference' =>
                $this->extractTransactionReference(
                    $data
                )
                ?? $withdrawal?->reference,

            'status' => $status,

            'timestamp' => (string) (
                $this->firstDataValue(
                    $data,
                    [
                        'done_at',
                        'completed_at',
                        'updated_at',
                        'created_at',
                    ]
                )
                ?? now()->toDateTimeString()
            ),
        ];
    }

    protected function resolveUser(
        array $data
    ): ?User {
        $quidaxUserId =
            $this->firstDataValue(
                $data,
                [
                    'user.id',
                    'user.uid',
                    'user_id',
                    'uid',
                    'funded_by.id',
                ]
            );

        if ($quidaxUserId) {
            $user = User::where(
                'quidax_id',
                $quidaxUserId
            )->first();

            if ($user) {
                return $user;
            }
        }

        $email =
            $this->firstDataValue(
                $data,
                [
                    'user.email',
                    'email',
                    'customer.email',
                ]
            );

        return $email
            ? User::where(
                'email',
                $email
            )->first()
            : null;
    }

    protected function resolveWithdrawal(
        array $data
    ): ?Withdrawals {
        $reference =
            $this->extractTransactionReference(
                $data
            );

        $providerId =
            $this->firstDataValue(
                $data,
                [
                    'id',
                    'uuid',
                    'provider_id',
                    'withdrawal_id',
                ]
            );

        $txHash =
            $this->extractTransactionHash(
                $data
            );

        $values = array_filter([
            $reference,
            $providerId,
            $txHash,
        ]);

        if ($values === []) {
            return null;
        }

        return Withdrawals::query()
            ->where(
                function ($query) use ($values) {
                    foreach ($values as $value) {
                        $query
                            ->orWhere(
                                'reference',
                                $value
                            )
                            ->orWhere(
                                'trans_id',
                                $value
                            )
                            ->orWhere(
                                'trans_id',
                                'pending:' . $value
                            )
                            ->orWhere(
                                'trans_id',
                                'processing:' . $value
                            )
                            ->orWhere(
                                'trans_id',
                                'initiated:' . $value
                            )
                            ->orWhere(
                                'wallet->provider_withdrawal_id',
                                $value
                            )
                            ->orWhere(
                                'wallet->transaction_hash',
                                $value
                            )
                            ->orWhere(
                                'wallet->txid',
                                $value
                            );
                    }
                }
            )
            ->latest('id')
            ->first();
    }

    /**
     * -------------------------------------------------------------
     * EVENT DETECTION
     * -------------------------------------------------------------
     */
    protected function isDepositEvent(
        string $eventName,
        array $data
    ): bool {
        $event = strtolower($eventName);

        return Str::contains(
            $event,
            ['deposit']
        )
            || strtolower(
                (string) data_get(
                    $data,
                    'resource'
                )
            ) === 'deposit';
    }

    protected function isSuccessfulDeposit(
        string $eventName,
        array $data
    ): bool {
        $event =
            strtolower($eventName);

        $status =
            $this->normalizeStatus(
                $this->extractStatus($data)
            );

        return Str::contains(
            $event,
            [
                'deposit.success',
                'deposit_success',
                'deposit.confirm',
                'deposit.completed',
            ]
        )
            || in_array(
                $status,
                [
                    'success',
                    'successful',
                    'confirmed',
                    'completed',
                    'done',
                ],
                true
            );
    }

    protected function isFailedDeposit(
        string $eventName,
        array $data
    ): bool {
        $event =
            strtolower($eventName);

        $status =
            $this->normalizeStatus(
                $this->extractStatus($data)
            );

        return Str::contains(
            $event,
            [
                'deposit.failed',
                'deposit_fail',
                'deposit.rejected',
                'deposit.cancelled',
                'deposit.canceled',
            ]
        )
            || in_array(
                $status,
                [
                    'failed',
                    'failure',
                    'rejected',
                    'cancelled',
                    'canceled',
                    'expired',
                    'error',
                ],
                true
            );
    }

    protected function isWithdrawalEvent(
        string $eventName,
        array $data
    ): bool {
        $event =
            strtolower($eventName);

        return Str::contains(
            $event,
            ['withdraw']
        )
            || strtolower(
                (string) data_get(
                    $data,
                    'resource'
                )
            ) === 'withdrawal';
    }

    protected function normalizeWithdrawalStatus(
        string $eventName,
        array $data
    ): ?string {
        $event =
            strtolower($eventName);

        $status =
            $this->normalizeStatus(
                $this->extractStatus($data)
            );

        if (
            Str::contains(
                $event,
                [
                    'withdraw.success',
                    'withdrawal.success',
                    'withdraw.completed',
                    'withdrawal.completed',
                ]
            )
            || in_array(
                $status,
                [
                    'done',
                    'completed',
                    'success',
                    'successful',
                ],
                true
            )
        ) {
            return 'completed';
        }

        if (
            Str::contains(
                $event,
                [
                    'withdraw.reject',
                    'withdraw.failed',
                    'withdraw.reversed',
                    'withdrawal.failed',
                    'withdrawal.rejected',
                    'withdrawal.reversed',
                ]
            )
            || in_array(
                $status,
                [
                    'failed',
                    'rejected',
                    'cancelled',
                    'canceled',
                    'reversed',
                    'expired',
                    'error',
                ],
                true
            )
        ) {
            return 'failed';
        }

        if (
            Str::contains(
                $event,
                [
                    'withdraw.created',
                    'withdraw.initiated',
                    'withdrawal.initiated',
                ]
            )
            || in_array(
                $status,
                [
                    'created',
                    'submitted',
                    'initialized',
                    'initiated',
                ],
                true
            )
        ) {
            return 'initiated';
        }

        if (
            Str::contains(
                $event,
                [
                    'withdraw.processing',
                    'withdrawal.processing',
                ]
            )
            || in_array(
                $status,
                [
                    'processing',
                    'pending',
                    'confirming',
                    'approved',
                ],
                true
            )
        ) {
            return 'processing';
        }

        return null;
    }

    protected function shouldSendUserWithdrawalNotification(
        string $status,
        array $details
    ): bool {
        if ($status === 'failed') {
            return true;
        }

        if ($status !== 'completed') {
            return false;
        }

        return trim(
            (string) (
                $details['transaction_hash']
                ?? ''
            )
        ) !== '';
    }

    /**
     * -------------------------------------------------------------
     * SIGNATURE
     * -------------------------------------------------------------
     */
    protected function verifySignatureIfConfigured(
        Request $request,
        string $rawPayload
    ): bool {
        $secret =
            config('services.quidax.webhook_secret');

        /*
         * Fail closed in production if webhook authentication is expected
         * but no secret has been configured.
         *
         * For local development, you may explicitly configure
         * QUIDAX_WEBHOOK_ALLOW_UNSIGNED=true.
         */
        if (empty($secret)) {
            if (
                app()->environment('local', 'testing') &&
                (bool) config(
                    'services.quidax.allow_unsigned_webhooks',
                    false
                )
            ) {
                Log::warning(
                    'Quidax webhook signature verification bypassed in non-production environment.'
                );

                return true;
            }

            Log::critical(
                'Quidax webhook rejected because webhook secret is not configured.'
            );

            return false;
        }

        $signature =
            $request->header(
                'x-quidax-signature'
            )
            ?? $request->header(
                'quidax-signature'
            )
            ?? $request->header(
                'x-signature'
            )
            ?? $request->header(
                'signature'
            );

        if (empty($signature)) {
            return false;
        }

        $timestamp = null;
        $parts = [];

        foreach (
            explode(
                ',',
                $signature
            ) as $part
        ) {
            [
                $key,
                $value
            ] = array_pad(
                explode(
                    '=',
                    trim($part),
                    2
                ),
                2,
                null
            );

            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        if (isset($parts['t'])) {
            $timestamp = $parts['t'];
        }

        $providedSignature =
            $parts['v1']
            ?? $parts['sha256']
            ?? $signature;

        $providedSignature =
            Str::startsWith(
                $providedSignature,
                'sha256='
            )
                ? substr(
                    $providedSignature,
                    7
                )
                : $providedSignature;

        /*
         * Reject absurdly old/future signed payloads when a timestamp is
         * supplied. Five minutes is a sensible replay window.
         */
        if ($timestamp !== null) {
            if (
                !ctype_digit(
                    (string) $timestamp
                )
            ) {
                return false;
            }

            if (
                abs(
                    now()->timestamp -
                    (int) $timestamp
                ) > 300
            ) {
                Log::warning(
                    'Quidax webhook rejected because signature timestamp is outside replay window.'
                );

                return false;
            }
        }

        $candidatePayloads = [
            $rawPayload,
        ];

        if ($timestamp) {
            $candidatePayloads[] =
                "{$timestamp}.{$rawPayload}";
        }

        foreach ($candidatePayloads as $candidatePayload) {
            $expected = hash_hmac(
                'sha256',
                $candidatePayload,
                $secret
            );

            if (
                hash_equals(
                    $expected,
                    $providedSignature
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Never log the actual signature value.
     */
    protected function signatureHeaders(
        Request $request
    ): array {
        return [
            'x-quidax-signature' =>
                $request->hasHeader(
                    'x-quidax-signature'
                ),
            'quidax-signature' =>
                $request->hasHeader(
                    'quidax-signature'
                ),
            'x-signature' =>
                $request->hasHeader(
                    'x-signature'
                ),
            'signature' =>
                $request->hasHeader(
                    'signature'
                ),
        ];
    }

    /**
     * -------------------------------------------------------------
     * EXTRACTION HELPERS
     * -------------------------------------------------------------
     */
    protected function extractEventName(
        array $payload
    ): string {
        return strtolower(
            (string) (
                $payload['event']
                ?? $payload['type']
                ?? $payload['event_type']
                ?? data_get(
                    $payload,
                    'data.event'
                )
                ?? data_get(
                    $payload,
                    'data.type'
                )
                ?? ''
            )
        );
    }

    protected function extractEventId(
        array $payload,
        array $data
    ): ?string {
        return $this->firstDataValue(
            $payload,
            [
                'id',
                'event_id',
                'eventId',
                'uuid',
            ]
        )
            ?? $this->firstDataValue(
                $data,
                [
                    'event_id',
                    'eventId',
                    'id',
                    'uuid',
                    'reference',
                    'txid',
                    'tx_id',
                    'transaction_hash',
                ]
            );
    }

    protected function extractTransactionReference(
        array $data
    ): ?string {
        return $this->firstDataValue(
            $data,
            [
                'reference',
                'transaction_reference',
                'merchant_reference',
                'id',
                'uuid',
                'txid',
                'tx_id',
            ]
        );
    }

    protected function extractStatus(
        array $data
    ): ?string {
        return $this->firstDataValue(
            $data,
            [
                'status',
                'state',
                'event_status',
            ]
        );
    }

    protected function normalizeStatus(
        ?string $status
    ): string {
        return strtolower(
            str_replace(
                [
                    ' ',
                    '-',
                ],
                '_',
                trim(
                    (string) $status
                )
            )
        );
    }

    protected function dedupeKey(
        string $type,
        string $eventName,
        array $payload,
        array $data
    ): string {
        $parts = array_filter([
            'quidax',
            $type,
            $eventName,
            $this->extractEventId(
                $payload,
                $data
            ),
            $this->extractTransactionReference(
                $data
            ),
            $this->extractTransactionHash(
                $data
            ),
        ]);

        if ($parts !== []) {
            return sha1(
                implode('|', $parts)
            );
        }

        return sha1(
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            )
        );
    }

    protected function withdrawalNotificationDedupeKey(
        ?string $reference,
        string $status,
        string $scope = 'user'
    ): string {
        $reference =
            strtolower(
                trim(
                    (string) $reference
                )
            );

        $reference =
            $reference !== ''
                ? $reference
                : 'missing-reference';

        return sha1(
            implode(
                '|',
                [
                    'quidax',
                    'withdrawal',
                    strtolower($scope),
                    $reference,
                    strtolower($status),
                ]
            )
        );
    }

    protected function extractTransactionHash(
        array $data
    ): ?string {
        $value =
            $this->firstDataValue(
                $data,
                [
                    'txid',
                    'tx_id',
                    'transaction_hash',
                    'hash',
                    'blockchain_transaction_hash',
                    'blockchain_txid',
                    'transaction.hash',
                    'transaction.txid',
                    'withdrawal.txid',
                    'withdrawal.transaction_hash',
                    'blockchain.txid',
                    'blockchain.transaction_hash',
                    'data.txid',
                    'data.transaction_hash',
                ]
            );

        return is_scalar($value)
            ? (string) $value
            : null;
    }

    protected function extractRecipientAddress(
        array $data
    ): ?string {
        $value =
            $this->firstDataValue(
                $data,
                [
                    'fund_uid',
                    'address',
                    'recipient_address',
                    'destination_address',
                    'to_address',
                    'wallet_address',
                    'recipient.address',
                    'recipient.details.address',
                    'recipient.data.address',
                    'destination.address',
                    'destination.details.address',
                    'details.address',
                    'data.fund_uid',
                    'data.recipient.details.address',
                ]
            );

        return is_scalar($value)
            ? (string) $value
            : null;
    }

    protected function extractNetwork(
        array $data
    ): ?string {
        $value =
            $this->firstDataValue(
                $data,
                [
                    'network',
                    'blockchain',
                    'chain',
                    'recipient.network',
                    'recipient.details.network',
                    'destination.network',
                    'details.network',
                    'data.network',
                ]
            );

        return is_scalar($value)
            ? (string) $value
            : null;
    }

    protected function validStoredTransactionHash(
        $value,
        array $rejectValues = []
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        if (
            $value === '' ||
            strtolower($value) === 'unknown'
        ) {
            return null;
        }

        foreach ($rejectValues as $rejectValue) {
            if (
                $rejectValue !== null &&
                strtolower($value) ===
                strtolower(
                    trim(
                        (string) $rejectValue
                    )
                )
            ) {
                return null;
            }
        }

        foreach (
            [
                'failed:',
                'pending:',
                'processing:',
                'initiated:',
                'cancelled:',
                'canceled:',
            ] as $prefix
        ) {
            if (
                Str::startsWith(
                    strtolower($value),
                    $prefix
                )
            ) {
                return null;
            }
        }

        return $value;
    }

    protected function firstDataValue(
        array $data,
        array $keys
    ) {
        foreach ($keys as $key) {
            $value = data_get(
                $data,
                $key
            );

            if (
                $value !== null &&
                $value !== ''
            ) {
                return is_scalar($value)
                    ? (string) $value
                    : $value;
            }
        }

        return null;
    }

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