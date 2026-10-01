<?php

namespace App\Jobs;

use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardTransactions;
use App\Traits\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessQuidaxOfframpForCardFunding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Notify;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60, 120];

    protected int|string $transactionId;
    protected array $payload;
    protected array $data;
    protected string $eventName;
    protected ?string $merchantReference;
    protected ?int $eventLogId;

    public function __construct(
        int|string $transactionId,
        array $payload,
        array $data,
        string $eventName,
        ?string $merchantReference = null,
        ?int $eventLogId = null
    ) {
        $this->transactionId = $transactionId;
        $this->payload = $payload;
        $this->data = $data;
        $this->eventName = $eventName;
        $this->merchantReference = $merchantReference;
        $this->eventLogId = $eventLogId;

        $this->onQueue(config('queue.queues.crypto_cards', 'crypto-cards'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'quidax-card-funding:' . $this->transactionId
            ))->releaseAfter(10)->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $transaction = null;
        $status = $this->normalizeStatus(
            $this->firstDataValue($this->data, [
                'status',
                'state',
                'event_status',
            ])
        );

        $this->log('info', 'Quidax card funding webhook job started.', [
            'transaction_id' => $this->transactionId,
            'merchant_reference' => $this->merchantReference,
            'event' => $this->eventName,
            'provider_status' => $status,
            'event_log_id' => $this->eventLogId,
        ]);

        try {
            DB::transaction(function () use (&$transaction, $status) {
                $transaction = CryptoCardTransactions::query()
                    ->with(['card'])
                    ->lockForUpdate()
                    ->find($this->transactionId);

                if (!$transaction) {
                    $this->log('error', 'Crypto card transaction not found.', [
                        'transaction_id' => $this->transactionId,
                        'merchant_reference' => $this->merchantReference,
                        'event' => $this->eventName,
                    ]);

                    throw new \RuntimeException(
                        "Crypto card transaction {$this->transactionId} was not found."
                    );
                }

                $this->log('info', 'Crypto card transaction loaded.', [
                    'transaction_id' => $transaction->id,
                    'transaction_status' => $transaction->transaction_status,
                    'provider_status' => $status,
                    'merchant_reference' => $this->merchantReference,
                ]);

                if ($this->isAlreadyCompleted($transaction)) {
                    $this->log('warning', 'Duplicate Quidax success webhook ignored.', [
                        'transaction_id' => $transaction->id,
                        'transaction_status' => $transaction->transaction_status,
                        'merchant_reference' => $this->merchantReference,
                        'event' => $this->eventName,
                    ]);

                    $this->recordWebhookMetadata(
                        $transaction,
                        'duplicate',
                        $status
                    );

                    return;
                }

                $escrow = CryptoCardEscrow::query()
                    ->where(
                        'crypto_card_transaction_id',
                        $transaction->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (!$escrow) {
                    $this->log('warning', 'Crypto card escrow not found.', [
                        'transaction_id' => $transaction->id,
                        'merchant_reference' => $this->merchantReference,
                    ]);
                }

                if ($this->isSuccessfulStatus($status)) {
                    $this->log('info', 'Processing successful Quidax card funding webhook.', [
                        'transaction_id' => $transaction->id,
                        'merchant_reference' => $this->merchantReference,
                        'provider_status' => $status,
                    ]);

                    $this->completeFunding($transaction, $escrow);
                    return;
                }

                if ($this->isFailedStatus($status)) {
                    $this->log('warning', 'Processing failed Quidax card funding webhook.', [
                        'transaction_id' => $transaction->id,
                        'merchant_reference' => $this->merchantReference,
                        'provider_status' => $status,
                    ]);

                    $this->failFunding($transaction, $escrow);
                    return;
                }

                $this->log('info', 'Quidax card funding webhook received with non-terminal status.', [
                    'transaction_id' => $transaction->id,
                    'merchant_reference' => $this->merchantReference,
                    'provider_status' => $status,
                ]);

                $this->recordWebhookMetadata(
                    $transaction,
                    'processing',
                    $status
                );
            });

            $transaction?->refresh();

            if (!$transaction) {
                return;
            }

            if ($this->isSuccessfulStatus($status)) {
                try {
                    $this->sendSuccessNotification($transaction);
                } catch (Throwable $e) {
                    $this->log('error', 'Card funding success notification failed.', [
                        'transaction_id' => $transaction->id,
                        'merchant_reference' => $this->merchantReference,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($this->isFailedStatus($status)) {
                try {
                    $this->sendFailureNotification($transaction);
                } catch (Throwable $e) {
                    $this->log('error', 'Card funding failure notification failed.', [
                        'transaction_id' => $transaction->id,
                        'merchant_reference' => $this->merchantReference,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->log('info', 'Quidax card funding webhook job completed.', [
                'transaction_id' => $transaction->id,
                'merchant_reference' => $this->merchantReference,
                'provider_status' => $status,
                'event' => $this->eventName,
                'transaction_status' => $transaction->transaction_status,
            ]);
        } catch (Throwable $e) {
            $this->log('error', 'Quidax card funding webhook job failed.', [
                'transaction_id' => $this->transactionId,
                'merchant_reference' => $this->merchantReference,
                'event' => $this->eventName,
                'provider_status' => $status,
                'event_log_id' => $this->eventLogId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }

    protected function completeFunding(
        CryptoCardTransactions $transaction,
        ?CryptoCardEscrow $escrow
    ): void {
        if ($this->isAlreadyCompleted($transaction)) {
            $this->log('warning', 'Funding already completed; card credit skipped.', [
                'transaction_id' => $transaction->id,
                'merchant_reference' => $this->merchantReference,
                'transaction_status' => $transaction->transaction_status,
            ]);

            return;
        }

        $card = $transaction->card;

        if (!$card) {
            throw new \RuntimeException(
                "Card not found for crypto card transaction {$transaction->id}."
            );
        }

        $amount = $this->decimalString($transaction->amount);

        if (bccomp($amount, '0', 18) <= 0) {
            throw new \RuntimeException(
                "Invalid card funding amount for transaction {$transaction->id}."
            );
        }

        $this->log('info', 'Crediting crypto card after successful Quidax settlement.', [
            'transaction_id' => $transaction->id,
            'card_id' => $card->id,
            'amount' => $amount,
            'merchant_reference' => $this->merchantReference,
        ]);

        $card->credit($amount);

        $metadata = $this->metadata($transaction);

        $metadata['quidax_webhook'] = $this->sanitizeWebhookData($this->data);

        $metadata['quidax'] = array_merge(
            is_array($metadata['quidax'] ?? null)
                ? $metadata['quidax']
                : [],
            [
                'merchant_reference' => $this->merchantReference,
                'event' => $this->eventName,
                'provider_status' => $this->firstDataValue(
                    $this->data,
                    ['status', 'state', 'event_status']
                ),
                'provider_transaction_id' => $this->firstDataValue(
                    $this->data,
                    ['id', 'uuid', 'provider_id', 'withdrawal_id']
                ),
                'transaction_hash' => $this->extractTransactionHash($this->data),
                'completed_at' => now()->toIso8601String(),
            ]
        );

        $transaction->update([
            'transaction_status' => 'approved',
            'metadata' => $metadata,
        ]);

        if ($escrow) {
            $escrow->update([
                'status' => 'completed',
                'master_account_impact' => $this->negativeDecimal($amount),
                'master_account_reference' => $this->merchantReference,
            ]);
        }

        $this->log('info', 'Quidax card funding successfully completed.', [
            'transaction_id' => $transaction->id,
            'card_id' => $card->id,
            'escrow_id' => $escrow?->id,
            'amount' => $amount,
            'merchant_reference' => $this->merchantReference,
            'provider_transaction_id' => $this->firstDataValue(
                $this->data,
                ['id', 'uuid', 'provider_id', 'withdrawal_id']
            ),
            'transaction_hash' => $this->extractTransactionHash($this->data),
        ]);
    }

    protected function failFunding(
        CryptoCardTransactions $transaction,
        ?CryptoCardEscrow $escrow
    ): void {
        if ($this->isAlreadyCompleted($transaction)) {
            $this->log('warning', 'Failure webhook received after funding was already completed.', [
                'transaction_id' => $transaction->id,
                'merchant_reference' => $this->merchantReference,
                'transaction_status' => $transaction->transaction_status,
            ]);

            return;
        }

        $metadata = $this->metadata($transaction);

        $failureReason = $this->firstDataValue($this->data, [
            'reason',
            'failure_reason',
            'reject_reason',
            'message',
            'error',
        ]);

        $failureReason = $failureReason
            ?: 'Quidax card funding transaction failed.';

        $metadata['quidax_webhook'] = $this->sanitizeWebhookData($this->data);
        $metadata['failure_reason'] = $failureReason;
        $metadata['failed_at'] = now()->toIso8601String();

        $metadata['quidax'] = array_merge(
            is_array($metadata['quidax'] ?? null)
                ? $metadata['quidax']
                : [],
            [
                'merchant_reference' => $this->merchantReference,
                'event' => $this->eventName,
                'provider_status' => $this->firstDataValue(
                    $this->data,
                    ['status', 'state', 'event_status']
                ),
                'provider_transaction_id' => $this->firstDataValue(
                    $this->data,
                    ['id', 'uuid', 'provider_id', 'withdrawal_id']
                ),
                'transaction_hash' => $this->extractTransactionHash($this->data),
            ]
        );

        $transaction->update([
            'transaction_status' => 'failed',
            'metadata' => $metadata,
        ]);

        if ($escrow) {
            $escrow->update([
                'status' => 'failed',
            ]);
        }

        $this->log('warning', 'Quidax card funding marked as failed.', [
            'transaction_id' => $transaction->id,
            'escrow_id' => $escrow?->id,
            'merchant_reference' => $this->merchantReference,
            'provider_status' => $this->firstDataValue(
                $this->data,
                ['status', 'state', 'event_status']
            ),
            'failure_reason' => $failureReason,
        ]);
    }

    protected function sendSuccessNotification(
        CryptoCardTransactions $transaction
    ): void {
        $user = $this->resolveUserFromTransaction($transaction);

        if (!$user) {
            $this->log('warning', 'Unable to send card funding success notification: user not found.', [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        $card = $transaction->relationLoaded('card')
            ? $transaction->card
            : $transaction->card()->first();

        $params = [
            'user' => $user->firstname ?? $user->name ?? 'User',
            'card' => $card?->last_four
                ?? $card?->masked_number
                ?? '****',
            'amount' => $this->formatAmount($transaction->amount),
            'currency' => strtoupper(
                (string) ($transaction->currency ?? $transaction->coin ?? 'USD')
            ),
            'reference' => $this->merchantReference
                ?: (string) $transaction->id,
            'transaction_id' => (string) $transaction->id,
            'provider' => 'Quidax',
            'status' => 'Completed',
        ];

        $this->queueNotification(
            $user,
            'CRYPTO_CARD_FUNDING_SUCCESS',
            $params,
            ['mail', 'push', 'inapp'],
            [
                'action' => [
                    'link' => '#',
                    'icon' => 'fa fa-credit-card text-white',
                ],
                'referenceId' => 'CARD_FUNDING_' . $transaction->id,
                'referenceType' => 'crypto_card_transaction',
                'type' => 'card_funding',
                'priority' => 'high',
            ]
        );

        $this->log('info', 'Card funding success notification queued.', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
        ]);
    }

    protected function sendFailureNotification(
        CryptoCardTransactions $transaction
    ): void {
        $user = $this->resolveUserFromTransaction($transaction);

        if (!$user) {
            $this->log('warning', 'Unable to send card funding failure notification: user not found.', [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        $card = $transaction->relationLoaded('card')
            ? $transaction->card
            : $transaction->card()->first();

        $metadata = $this->metadata($transaction);

        $params = [
            'user' => $user->firstname ?? $user->name ?? 'User',
            'card' => $card?->last_four
                ?? $card?->masked_number
                ?? '****',
            'amount' => $this->formatAmount($transaction->amount),
            'currency' => strtoupper(
                (string) ($transaction->currency ?? $transaction->coin ?? 'USD')
            ),
            'reference' => $this->merchantReference
                ?: (string) $transaction->id,
            'transaction_id' => (string) $transaction->id,
            'provider' => 'Quidax',
            'status' => 'Failed',
            'reason' => (string) (
                $metadata['failure_reason']
                ?? 'The card funding transaction could not be completed.'
            ),
        ];

        $this->queueNotification(
            $user,
            'CRYPTO_CARD_FUNDING_FAILED',
            $params,
            ['mail', 'push', 'inapp'],
            [
                'action' => [
                    'link' => '#',
                    'icon' => 'fa fa-credit-card text-white',
                ],
                'referenceId' => 'CARD_FUNDING_FAILED_' . $transaction->id,
                'referenceType' => 'crypto_card_transaction',
                'type' => 'card_funding',
                'priority' => 'high',
            ]
        );

        $this->log('info', 'Card funding failure notification queued.', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
        ]);
    }

    protected function resolveUserFromTransaction(
        CryptoCardTransactions $transaction
    ) {
        if ($transaction->relationLoaded('user')) {
            return $transaction->user;
        }

        if (method_exists($transaction, 'user')) {
            $user = $transaction->user()->first();

            if ($user) {
                return $user;
            }
        }

        $card = $transaction->relationLoaded('card')
            ? $transaction->card
            : $transaction->card()->first();

        if ($card && method_exists($card, 'user')) {
            return $card->user()->first();
        }

        return null;
    }

    protected function isAlreadyCompleted(
        CryptoCardTransactions $transaction
    ): bool {
        return in_array(
            strtolower((string) $transaction->transaction_status),
            ['approved', 'completed'],
            true
        );
    }

    protected function isSuccessfulStatus(?string $status): bool
    {
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

    protected function isFailedStatus(?string $status): bool
    {
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

    protected function normalizeStatus(?string $status): string
    {
        return strtolower(
            str_replace(
                [' ', '-'],
                '_',
                trim((string) $status)
            )
        );
    }

    protected function metadata(
        CryptoCardTransactions $transaction
    ): array {
        return is_array($transaction->metadata)
            ? $transaction->metadata
            : [];
    }

    protected function recordWebhookMetadata(
        CryptoCardTransactions $transaction,
        string $processingState,
        ?string $providerStatus
    ): void {
        $metadata = $this->metadata($transaction);

        $metadata['quidax_webhook'] =
            $this->sanitizeWebhookData($this->data);

        $metadata['quidax_processing'] = [
            'state' => $processingState,
            'provider_status' => $providerStatus,
            'event' => $this->eventName,
            'received_at' => now()->toIso8601String(),
        ];

        $transaction->update([
            'metadata' => $metadata,
        ]);

        $this->log('info', 'Quidax webhook processing metadata recorded.', [
            'transaction_id' => $transaction->id,
            'processing_state' => $processingState,
            'provider_status' => $providerStatus,
            'event' => $this->eventName,
        ]);
    }

    protected function sanitizeWebhookData(array $data): array
    {
        $sensitiveKeys = [
            'authorization',
            'access_token',
            'token',
            'secret',
            'password',
            'api_key',
            'signature',
        ];

        $sanitized = $data;

        array_walk_recursive(
            $sanitized,
            function (&$value, $key) use ($sensitiveKeys) {
                if (in_array(
                    strtolower((string) $key),
                    $sensitiveKeys,
                    true
                )) {
                    $value = '[REDACTED]';
                }
            }
        );

        return $sanitized;
    }

    protected function firstDataValue(
        array $data,
        array $keys
    ) {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value)
                    ? (string) $value
                    : $value;
            }
        }

        return null;
    }

    protected function extractTransactionHash(
        array $data
    ): ?string {
        $value = $this->firstDataValue($data, [
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
        ]);

        return is_scalar($value)
            ? (string) $value
            : null;
    }

    protected function decimalString($value): string
    {
        $value = trim((string) $value);

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(
                "Invalid decimal value: {$value}"
            );
        }

        return $value;
    }

    protected function negativeDecimal(string $amount): string
    {
        return bcsub('0', $amount, 18);
    }

    protected function formatAmount($amount): string
    {
        return rtrim(
            rtrim(
                number_format(
                    (float) $amount,
                    18,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }

    protected function log(
        string $level,
        string $message,
        array $context = []
    ): void {
        Log::channel('crypto_card')->{$level}(
            $message,
            array_merge(
                [
                    'job' => static::class,
                    'transaction_id' => $this->transactionId,
                ],
                $context
            )
        );
    }

    public function failed(Throwable $exception): void
    {
        $this->log('critical', 'Quidax card funding job permanently failed.', [
            'merchant_reference' => $this->merchantReference,
            'event' => $this->eventName,
            'event_log_id' => $this->eventLogId,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }
}