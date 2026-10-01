<?php

namespace App\Jobs;

use App\Models\CryptoCardEscrow;
use App\Models\CryptoCardsModel;
use App\Models\CryptoCardTransactions;
use App\Models\User;
use App\Services\QuidaxRampService;
use App\Traits\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InitiateQuidaxCardFundingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Notify;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Maximum execution time per attempt.
     */
    public int $timeout = 120;

    /**
     * Delay between retry attempts.
     */
    public array $backoff = [15, 45, 90];

    public function __construct(
        protected int $userId,
        protected int $cardId,
        protected float $amount,
        protected int $transactionId,
        protected int $escrowId,
        protected array $context = []
    ) {
        $this->onQueue(
            config('queue.queues.crypto_cards', 'crypto-cards')
        );
    }

    /**
     * Prevent duplicate initiation for the same card transaction.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(
                'initiate-card-offramp:' . $this->transactionId
            ))
                ->releaseAfter(15)
                ->expireAfter(300),
        ];
    }

    /**
     * Write to the dedicated crypto-card log.
     */
    protected function cryptoLog(
        string $level,
        string $message,
        array $context = []
    ): void {
        Log::channel('crypto_card')->{$level}(
            $message,
            array_merge(
                [
                    'job' => self::class,
                    'transaction_id' => $this->transactionId,
                    'escrow_id' => $this->escrowId,
                    'user_id' => $this->userId,
                    'card_id' => $this->cardId,
                ],
                $context
            )
        );
    }

    /**
     * Mask sensitive account numbers for logs.
     */
    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if (!$accountNumber) {
            return null;
        }

        $accountNumber = preg_replace('/\D+/', '', $accountNumber);

        if (!$accountNumber) {
            return null;
        }

        if (strlen($accountNumber) <= 4) {
            return $accountNumber;
        }

        return str_repeat('*', strlen($accountNumber) - 4)
            . substr($accountNumber, -4);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transaction = null;
        $escrow = null;
        $card = null;
        $user = null;

        $this->cryptoLog(
            'info',
            'Quidax card funding off-ramp job started.',
            [
                'amount' => $this->amount,
                'attempt' => $this->attempts(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Load records
        |--------------------------------------------------------------------------
        */

        $transaction = CryptoCardTransactions::find($this->transactionId);
        $escrow = CryptoCardEscrow::find($this->escrowId);
        $card = CryptoCardsModel::find($this->cardId);
        $user = User::find($this->userId);

        $this->cryptoLog(
            'info',
            'Card funding records loaded.',
            [
                'transaction_found' => (bool) $transaction,
                'escrow_found' => (bool) $escrow,
                'card_found' => (bool) $card,
                'user_found' => (bool) $user,
            ]
        );

        if (!$transaction || !$escrow || !$card || !$user) {
            $this->cryptoLog(
                'error',
                'Quidax card funding job stopped because required records are missing.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction status
        |--------------------------------------------------------------------------
        */

        $transactionStatus = strtolower(
            trim((string) $transaction->transaction_status)
        );

        $this->cryptoLog(
            'info',
            'Checking card funding transaction status.',
            [
                'transaction_status' => $transactionStatus,
            ]
        );

        if (!in_array(
            $transactionStatus,
            ['initiated', 'pending', 'processing'],
            true
        )) {
            $this->cryptoLog(
                'info',
                'Card funding transaction already processed; job will stop.',
                [
                    'transaction_status' => $transactionStatus,
                ]
            );

            return;
        }

        try {
            $quidaxRamp = new QuidaxRampService();

            /*
            |--------------------------------------------------------------------------
            | Transaction metadata
            |--------------------------------------------------------------------------
            */

            $metadata = is_array($transaction->metadata)
                ? $transaction->metadata
                : [];

            /*
            |--------------------------------------------------------------------------
            | Crypto currency
            |--------------------------------------------------------------------------
            */

            $cryptoCurrency = strtolower(
                (string) (
                    $this->context['crypto_currency']
                    ?? $this->context['currency']
                    ?? data_get($metadata, 'coin')
                    ?? data_get($metadata, 'crypto_currency')
                    ?? 'usdt'
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Network
            |--------------------------------------------------------------------------
            */

            $network = strtolower(
                (string) (
                    $this->context['network']
                    ?? data_get($metadata, 'network')
                    ?? 'trc20'
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Fiat currency
            |--------------------------------------------------------------------------
            */

            $fiatCurrency = strtolower(
                (string) (
                    $this->context['fiat_currency']
                    ?? data_get($metadata, 'fiat_currency')
                    ?? 'ngn'
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Crypto amount
            |--------------------------------------------------------------------------
            */

            $cryptoAmount = (string) (
                $this->context['crypto_amount']
                ?? data_get($metadata, 'crypto_amount')
                ?? $this->amount
            );

            $this->cryptoLog(
                'info',
                'Quidax off-ramp parameters resolved.',
                [
                    'crypto_currency' => $cryptoCurrency,
                    'crypto_amount' => $cryptoAmount,
                    'fiat_currency' => $fiatCurrency,
                    'network' => $network,
                ]
            );

            if (!is_numeric($cryptoAmount) || (float) $cryptoAmount <= 0) {
                throw new \RuntimeException(
                    'Invalid crypto amount supplied for Quidax off-ramp.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Settlement account
            |--------------------------------------------------------------------------
            |
            | These values come from config/sudo.php:
            config('services.sudo.settlement_bank_code')
            config('services.sudo.settlement_account_number')
            config('services.sudo.settlement_account_name')
            |
            */

            $bankCode = (string) (
                $this->context['bank_code']
                ?? data_get($metadata, 'bank_code')
                ??    config('services.sudo.settlement_bank_code')
                ?? ''
            );

            $accountNumber = (string) (
                $this->context['account_number']
                ?? data_get($metadata, 'account_number')
                ?? config('services.sudo.settlement_account_number')
                ?? ''
            );

            $accountName = (string) (
                $this->context['account_name']
                ?? data_get($metadata, 'account_name')
                ?? config('services.sudo.settlement_account_name')
                ?? ''
            );

            $this->cryptoLog(
                'info',
                'Settlement bank configuration resolved.',
                [
                    'bank_code' => $bankCode,
                    'account_number' => $this->maskAccountNumber(
                        $accountNumber
                    ),
                    'account_name_configured' => $accountName !== '',
                    'currency' => strtoupper($fiatCurrency),
                ]
            );

            if (!$bankCode || !$accountNumber) {
                throw new \RuntimeException(
                    'Crypto card settlement bank account is not configured.'
                );
            }

            if ($fiatCurrency !== 'ngn') {
                $this->cryptoLog(
                    'warning',
                    'Unexpected settlement fiat currency.',
                    [
                        'fiat_currency' => $fiatCurrency,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Stable merchant reference
            |--------------------------------------------------------------------------
            */

            $merchantReference = data_get(
                $metadata,
                'quidax_offramp.merchant_reference'
            ) ?? data_get(
                $metadata,
                'quidax_merchant_reference'
            ) ?? (
                'CARD-OFFRAMP-'
                . strtoupper(Str::random(10))
                . '-'
                . $transaction->id
            );

            $this->cryptoLog(
                'info',
                'Quidax off-ramp merchant reference resolved.',
                [
                    'merchant_reference' => $merchantReference,
                    'existing_reference' => (bool) data_get(
                        $metadata,
                        'quidax_offramp.merchant_reference'
                    ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $customer = [
                'email' => $user->email,
                'first_name' => $user->firstname
                    ?? $user->first_name
                    ?? 'Customer',
                'last_name' => $user->lastname
                    ?? $user->last_name
                    ?? '',
            ];

            $this->cryptoLog(
                'info',
                'Quidax off-ramp customer information prepared.',
                [
                    'email_domain' => str_contains(
                        (string) $user->email,
                        '@'
                    )
                        ? substr(strrchr($user->email, '@'), 1)
                        : null,
                    'first_name' => $customer['first_name'],
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Check existing Quidax off-ramp
            |--------------------------------------------------------------------------
            */

            $existingRampReference = data_get(
                $metadata,
                'quidax_offramp.merchant_reference'
            ) ?? data_get(
                $metadata,
                'quidax_merchant_reference'
            );

            $initiationResponse = null;

            if ($existingRampReference) {
                $this->cryptoLog(
                    'info',
                    'Existing Quidax off-ramp reference found; checking Quidax before creating another transaction.',
                    [
                        'merchant_reference' => $existingRampReference,
                    ]
                );

                $existingTransaction = $quidaxRamp->offRampTransaction(
                    $existingRampReference
                );

                $this->cryptoLog(
                    'info',
                    'Existing Quidax off-ramp lookup completed.',
                    [
                        'merchant_reference' => $existingRampReference,
                        'ok' => $existingTransaction['ok'] ?? false,
                        'status' => $existingTransaction['status'] ?? null,
                        'http_status' => $existingTransaction['http_status'] ?? null,
                        'message' => $existingTransaction['message'] ?? null,
                    ]
                );

                if ($existingTransaction['ok'] ?? false) {
                    $initiationResponse = $existingTransaction;
                    $merchantReference = $existingRampReference;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Initiate off-ramp
            |--------------------------------------------------------------------------
            */

            if (!$initiationResponse) {
                $initiationPayload = [
                    'from_currency' => $cryptoCurrency,
                    'to_currency' => $fiatCurrency,
                    'from_amount' => $cryptoAmount,
                    'network' => $network,
                    'merchant_reference' => $merchantReference,
                    'customer' => $customer,
                ];

                $this->cryptoLog(
                    'info',
                    'Initiating Quidax custodial off-ramp.',
                    [
                        'merchant_reference' => $merchantReference,
                        'from_currency' => $cryptoCurrency,
                        'to_currency' => $fiatCurrency,
                        'from_amount' => $cryptoAmount,
                        'network' => $network,
                    ]
                );

                $initiationResponse = $quidaxRamp->initiateOffRamp(
                    $initiationPayload
                );

                $this->cryptoLog(
                    'info',
                    'Quidax off-ramp initiation response received.',
                    [
                        'merchant_reference' => $merchantReference,
                        'ok' => $initiationResponse['ok'] ?? false,
                        'status' => $initiationResponse['status'] ?? null,
                        'http_status' => $initiationResponse['http_status'] ?? null,
                        'message' => $initiationResponse['message'] ?? null,
                        'quidax_id' => data_get(
                            $initiationResponse,
                            'data.id'
                        ),
                    ]
                );
            }

            if (!($initiationResponse['ok'] ?? false)) {
                throw new \RuntimeException(
                    $initiationResponse['message']
                    ?? 'Quidax off-ramp initiation failed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Attach settlement bank account
            |--------------------------------------------------------------------------
            */

            $bankAccountPayload = [
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'currency_code' => strtoupper($fiatCurrency),
            ];

            /*
             * Only include account name when configured.
             * This avoids sending an empty value to the provider.
             */
            if ($accountName !== '') {
                $bankAccountPayload['account_name'] = $accountName;
            }

            $this->cryptoLog(
                'info',
                'Adding settlement bank account to Quidax off-ramp.',
                [
                    'merchant_reference' => $merchantReference,
                    'bank_code' => $bankCode,
                    'account_number' => $this->maskAccountNumber(
                        $accountNumber
                    ),
                    'account_name_configured' => $accountName !== '',
                    'currency_code' => strtoupper($fiatCurrency),
                ]
            );

            $bankResponse = $quidaxRamp->addBankAccountOffRamp(
                $merchantReference,
                $bankAccountPayload
            );

            $this->cryptoLog(
                'info',
                'Quidax off-ramp bank account response received.',
                [
                    'merchant_reference' => $merchantReference,
                    'ok' => $bankResponse['ok'] ?? false,
                    'status' => $bankResponse['status'] ?? null,
                    'http_status' => $bankResponse['http_status'] ?? null,
                    'message' => $bankResponse['message'] ?? null,
                ]
            );

            if (!($bankResponse['ok'] ?? false)) {
                throw new \RuntimeException(
                    $bankResponse['message']
                    ?? 'Unable to attach settlement bank account to Quidax off-ramp.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Confirm off-ramp
            |--------------------------------------------------------------------------
            */

            $this->cryptoLog(
                'info',
                'Confirming Quidax off-ramp.',
                [
                    'merchant_reference' => $merchantReference,
                ]
            );

            $confirmationResponse = $quidaxRamp->confirmOffRamp(
                $merchantReference
            );

            $this->cryptoLog(
                'info',
                'Quidax off-ramp confirmation response received.',
                [
                    'merchant_reference' => $merchantReference,
                    'ok' => $confirmationResponse['ok'] ?? false,
                    'status' => $confirmationResponse['status'] ?? null,
                    'http_status' => $confirmationResponse['http_status'] ?? null,
                    'message' => $confirmationResponse['message'] ?? null,
                    'offramp_id' => data_get(
                        $confirmationResponse,
                        'data.id'
                    ),
                ]
            );

            if (!($confirmationResponse['ok'] ?? false)) {
                throw new \RuntimeException(
                    $confirmationResponse['message']
                    ?? 'Quidax off-ramp confirmation failed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Extract confirmation data
            |--------------------------------------------------------------------------
            */

            $confirmationData = $confirmationResponse['data'] ?? [];

            $depositAddress = data_get(
                $confirmationData,
                'deposit_address'
            )
                ?? data_get($confirmationData, 'address')
                ?? data_get($confirmationData, 'deposit.address')
                ?? data_get(
                    $confirmationData,
                    'wallet_address.address'
                );

            $offRampId = data_get(
                $confirmationData,
                'id'
            )
                ?? data_get($confirmationData, 'transaction_id')
                ?? data_get($initiationResponse, 'data.id');

            $this->cryptoLog(
                'info',
                'Quidax off-ramp confirmation data processed.',
                [
                    'merchant_reference' => $merchantReference,
                    'offramp_id' => $offRampId,
                    'deposit_address_available' => !empty(
                        $depositAddress
                    ),
                    'deposit_address' => $depositAddress
                        ? substr($depositAddress, 0, 8)
                            . '...'
                            . substr($depositAddress, -6)
                        : null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Persist transaction and escrow state
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use (
                $transaction,
                $escrow,
                $metadata,
                $merchantReference,
                $cryptoCurrency,
                $fiatCurrency,
                $cryptoAmount,
                $network,
                $customer,
                $initiationResponse,
                $bankResponse,
                $confirmationResponse,
                $depositAddress,
                $offRampId,
                $bankCode,
                $accountNumber,
                $accountName
            ) {
                $metadata['quidax_merchant_reference'] =
                    $merchantReference;

                $metadata['quidax_offramp'] = array_merge(
                    is_array($metadata['quidax_offramp'] ?? null)
                        ? $metadata['quidax_offramp']
                        : [],
                    [
                        'merchant_reference' => $merchantReference,
                        'offramp_id' => $offRampId,
                        'from_currency' => $cryptoCurrency,
                        'to_currency' => $fiatCurrency,
                        'from_amount' => $cryptoAmount,
                        'network' => $network,

                        'customer' => [
                            'email' => $customer['email'],
                            'first_name' => $customer['first_name'],
                            'last_name' => $customer['last_name'],
                        ],

                        'settlement_bank' => [
                            'bank_code' => $bankCode,
                            'account_number' =>
                                $this->maskAccountNumber(
                                    $accountNumber
                                ),
                            'account_name' => $accountName ?: null,
                        ],

                        'deposit_address' => $depositAddress,

                        'initiation' =>
                            $initiationResponse['data'] ?? null,

                        'bank_account' =>
                            $bankResponse['data'] ?? null,

                        'confirmation' =>
                            $confirmationResponse['data'] ?? null,

                        'initiated_at' => data_get(
                            $metadata,
                            'quidax_offramp.initiated_at'
                        ) ?? now()->toIso8601String(),

                        'confirmed_at' =>
                            now()->toIso8601String(),
                    ]
                );

                $transaction->update([
                    'transaction_status' => 'processing',
                    'metadata' => $metadata,
                ]);

                $escrow->update([
                    'status' => 'processing',
                    'master_account_reference' =>
                        $merchantReference,
                ]);
            });

            $this->cryptoLog(
                'info',
                'Quidax card funding off-ramp successfully initiated and confirmed.',
                [
                    'merchant_reference' => $merchantReference,
                    'offramp_id' => $offRampId,
                    'crypto_currency' => $cryptoCurrency,
                    'crypto_amount' => $cryptoAmount,
                    'fiat_currency' => $fiatCurrency,
                    'network' => $network,
                    'deposit_address_available' =>
                        !empty($depositAddress),
                    'transaction_status' => 'processing',
                    'escrow_status' => 'processing',
                ]
            );
        } catch (Throwable $e) {
            $this->cryptoLog(
                'error',
                'InitiateQuidaxCardFundingJob failed.',
                [
                    'error_class' => get_class($e),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Do not mark the transaction failed while queue retries remain.
            |
            | If Quidax times out after creating the off-ramp, the next
            | attempt must be allowed to find the existing merchant reference
            | instead of seeing "failed" and stopping.
            |
            */

            if ($this->attempts() >= $this->tries) {
                $this->markFailed(
                    $transaction,
                    $escrow,
                    $user,
                    $card,
                    $e->getMessage()
                );
            }

            throw $e;
        }
    }

    /**
     * Mark the transaction failed after all queue retries are exhausted.
     */
    protected function markFailed(
        CryptoCardTransactions $transaction,
        CryptoCardEscrow $escrow,
        User $user,
        CryptoCardsModel $card,
        string $reason
    ): void {
        $this->cryptoLog(
            'warning',
            'Marking crypto card funding transaction as permanently failed.',
            [
                'reason' => $reason,
            ]
        );

        DB::transaction(function () use (
            $transaction,
            $escrow,
            $reason
        ) {
            $metadata = is_array($transaction->metadata)
                ? $transaction->metadata
                : [];

            $metadata['initiation_error'] = $reason;
            $metadata['failed_at'] = now()->toIso8601String();

            $transaction->update([
                'transaction_status' => 'failed',
                'metadata' => $metadata,
            ]);

            $escrow->update([
                'status' => 'failed',
            ]);
        });

        $this->cryptoLog(
            'warning',
            'Crypto card funding transaction and escrow marked as failed.',
            [
                'transaction_status' =>
                    $transaction->fresh()->transaction_status,
                'escrow_status' =>
                    $escrow->fresh()->status,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Failure notification
        |--------------------------------------------------------------------------
        */

        try {
            $this->cryptoLog(
                'info',
                'Sending crypto card funding failure notification.'
            );

            $this->sendNotification(
                $user,
                'CRYPTO_CARD_FUNDING_FAILED',
                [
                    'user' => $user->firstname
                        ?? $user->name
                        ?? 'User',

                    'amount' => number_format(
                        $this->amount,
                        2,
                        '.',
                        ','
                    ),

                    'currency' => strtoupper(
                        (string) ($transaction->currency ?? 'NGN')
                    ),

                    'card' => $card->card_last_four
                        ?? $card->last4
                        ?? '****',

                    'reference' =>
                        (string) $transaction->id,

                    'transaction_id' =>
                        (string) $transaction->id,

                    'status' => 'Failed',

                    'reason' => $reason,

                    'provider' => 'Quidax',
                ],
                ['mail', 'push', 'inapp']
            );

            $this->cryptoLog(
                'info',
                'Crypto card funding failure notification sent.'
            );
        } catch (Throwable $e) {
            /*
             * Notification failure must never cause the financial
             * transaction failure handling itself to fail.
             */
            $this->cryptoLog(
                'warning',
                'Crypto card funding failure notification failed.',
                [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]
            );
        }
    }

    /**
     * Queue failure callback.
     */
    public function failed(Throwable $exception): void
    {
        $this->cryptoLog(
            'critical',
            'InitiateQuidaxCardFundingJob permanently failed after queue retries.',
            [
                'error_class' => get_class($exception),
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        );
    }
}