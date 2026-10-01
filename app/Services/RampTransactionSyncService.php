<?php

namespace App\Services;

use App\Models\RampTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RampTransactionSyncService
{
    public function __construct(
        protected SafeHavenService $safeHavenService
    ) {
    }

    /**
     * Sync a Quidax on-ramp transaction.
     *
     * Local status:
     * - processing
     * - successful
     * - failed
     */
    public function syncOnRamp(
        RampTransaction $transaction,
        array $providerData
    ): RampTransaction {
        return DB::transaction(function () use ($transaction, $providerData) {
            /** @var RampTransaction $lockedTransaction */
            $lockedTransaction = RampTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $this->mapOnRampStatus(
                $providerData,
                (string) $lockedTransaction->status
            );

            $metadata = $this->mergeMetadata(
                $lockedTransaction->metadata,
                [
                    'provider_transaction' => $providerData,

                    'settlement' => [
                        'fiat_deposit' => data_get(
                            $providerData,
                            'fiat_deposit'
                        ),
                        'crypto_payout' => data_get(
                            $providerData,
                            'crypto_payout'
                        ),
                    ],

                    'provider_status_checked_at' => now()->toIso8601String(),
                ]
            );

            $lockedTransaction->update(
                array_merge(
                    [
                        'public_id' => $lockedTransaction->public_id
                            ?? ($providerData['public_id'] ?? null),

                        'reference' => $lockedTransaction->reference
                            ?? ($providerData['reference'] ?? null),

                        'to_amount' => $providerData['to_amount']
                            ?? $lockedTransaction->to_amount,

                        /*
                         * IMPORTANT:
                         *
                         * This is our INTERNAL status.
                         *
                         * Quidax may say "completed",
                         * but our RampTransaction status is "successful".
                         */
                        'status' => $status,

                        'metadata' => $metadata,
                    ],
                    $this->providerTrackingAttributes(
                        $providerData,
                        $status
                    )
                )
            );

            return $lockedTransaction->fresh();
        });
    }

    /**
     * Sync a Quidax off-ramp transaction.
     *
     * Local status:
     * - awaiting_payout
     * - successful
     * - failed
     */
    public function syncOffRamp(
        RampTransaction $transaction,
        array $providerData
    ): RampTransaction {
        return DB::transaction(function () use ($transaction, $providerData) {
            /** @var RampTransaction $lockedTransaction */
            $lockedTransaction = RampTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $this->mapOffRampStatus(
                $providerData,
                (string) $lockedTransaction->status
            );

            $metadata = $lockedTransaction->metadata ?? [];

            $walletUpdate = $metadata['wallet_update'] ?? null;

            if (!is_array($walletUpdate)) {
                $walletUpdate = null;
            }

            /*
             * IMPORTANT:
             *
             * "successful" is the local terminal success status.
             */
            if ($status === 'successful') {
                $walletUpdate = $this->syncOffRampWallet(
                    $lockedTransaction,
                    $providerData,
                    $metadata
                );
            }

            $metadataUpdates = [
                'provider_transaction' => $providerData,

                'settlement' => [
                    'crypto_deposit' => data_get(
                        $providerData,
                        'crypto_deposit'
                    ),
                    'fiat_payout' => data_get(
                        $providerData,
                        'fiat_payout'
                    ),
                ],

                'wallet_update' => $walletUpdate,

                'provider_status_checked_at' => now()->toIso8601String(),
            ];

            /*
             * Release reservation once the off-ramp reaches a final/
             * payout stage.
             */
            if (
                in_array(
                    $status,
                    [
                        'awaiting_payout',
                        'successful',
                        'failed',
                    ],
                    true
                )
            ) {
                $metadataUpdates['reservation_released_at']
                    = $metadata['reservation_released_at']
                    ?? now()->toDateTimeString();

                $metadataUpdates['reservation_release_reason']
                    = $metadata['reservation_release_reason']
                    ?? "off_ramp_status_{$status}";
            }

            $lockedTransaction->update(
                array_merge(
                    [
                        'public_id' => $lockedTransaction->public_id
                            ?? ($providerData['public_id'] ?? null),

                        'reference' => $lockedTransaction->reference
                            ?? ($providerData['reference'] ?? null),

                        'network' => strtolower(
                            (string) (
                                $providerData['network']
                                ?? $lockedTransaction->network
                            )
                        ),

                        /*
                         * Use data_get() instead of directly accessing
                         * crypto_deposit.address because the key may
                         * not exist in every Quidax webhook.
                         */
                        'wallet_address' => data_get(
                            $providerData,
                            'address'
                        )
                            ?? data_get(
                                $providerData,
                                'crypto_deposit.address'
                            )
                            ?? $lockedTransaction->wallet_address,

                        'to_amount' => $providerData['to_amount']
                            ?? data_get(
                                $providerData,
                                'fiat_payout.amount'
                            )
                            ?? $lockedTransaction->to_amount,

                        /*
                         * INTERNAL LOCAL STATUS.
                         */
                        'status' => $status,

                        'metadata' => $this->mergeMetadata(
                            $metadata,
                            $metadataUpdates
                        ),
                    ],
                    $this->providerTrackingAttributes(
                        $providerData,
                        $status
                    )
                )
            );

            return $lockedTransaction->fresh();
        });
    }

    /**
     * Handle a Quidax webhook.
     */
    public function handleWebhook(array $payload): ?RampTransaction
    {
        $event = $this->normalizeStatus(
            $payload['event']
                ?? $payload['type']
                ?? ''
        );

        $data = $payload['data'] ?? [];

        if (!is_array($data)) {
            return null;
        }

        $merchantReference = trim(
            (string) ($data['merchant_reference'] ?? '')
        );

        if ($merchantReference === '') {
            return null;
        }

        $mode = $this->normalizeStatus(
            $data['mode'] ?? ''
        );

        /*
         * Quidax:
         *
         * buy_transaction.*  => on_ramp
         * sell_transaction.* => off_ramp
         */
        $type = str_contains($event, 'sell_transaction')
            || $mode === 'sell'
            ? 'off_ramp'
            : 'on_ramp';

        $transaction = RampTransaction::where(
                'merchant_reference',
                $merchantReference
            )
            ->where('type', $type)
            ->first();

        if (!$transaction) {
            return null;
        }

        /*
         * Store the event inside providerData so the status mapper
         * can make a decision from both event and provider status.
         *
         * Example:
         *
         * buy_transaction.successful
         * becomes
         * buy_transaction_successful
         */
        $data['_event'] = $event;

        return $type === 'off_ramp'
            ? $this->syncOffRamp($transaction, $data)
            : $this->syncOnRamp($transaction, $data);
    }

    /**
     * Map Quidax on-ramp status to our internal status.
     */
    protected function mapOnRampStatus(
        array $providerData,
        string $currentStatus
    ): string {
        /*
         * Never move a successful/failed/cancelled transaction backwards.
         */
        if (
            in_array(
                $currentStatus,
                [
                    'successful',
                    'failed',
                    'cancelled',
                ],
                true
            )
        ) {
            return $currentStatus;
        }

        $event = $this->normalizeStatus(
            $providerData['_event'] ?? null
        );

        $status = $this->normalizeStatus(
            $providerData['status'] ?? null
        );

        $fiatDepositStatus = $this->normalizeStatus(
            data_get($providerData, 'fiat_deposit.status')
        );

        $cryptoPayoutStatus = $this->normalizeStatus(
            data_get($providerData, 'crypto_payout.status')
        );

        /*
         * ============================================================
         * SUCCESS
         * ============================================================
         *
         * Quidax may send:
         *
         * event:  buy_transaction.successful
         * status: completed
         *
         * Our database must contain:
         *
         * status = successful
         */
        if (
            in_array(
                $event,
                [
                    'buy_transaction_successful',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'completed',
                    'success',
                    'successful',
                ],
                true
            )
            || in_array(
                $cryptoPayoutStatus,
                [
                    'completed',
                    'success',
                    'successful',
                ],
                true
            )
        ) {
            return 'successful';
        }

        /*
         * ============================================================
         * FAILURE
         * ============================================================
         */
        if (
            in_array(
                $event,
                [
                    'buy_transaction_failed',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'failed',
                    'error',
                    'cancelled',
                    'rejected',
                ],
                true
            )
            || in_array(
                $cryptoPayoutStatus,
                [
                    'failed',
                    'error',
                    'cancelled',
                    'rejected',
                ],
                true
            )
        ) {
            return 'failed';
        }

        /*
         * ============================================================
         * PROCESSING
         * ============================================================
         */
        if (
            in_array(
                $event,
                [
                    'buy_transaction_processing',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'processing',
                    'pending',
                    'confirmed',
                ],
                true
            )
            || in_array(
                $fiatDepositStatus,
                [
                    'success',
                    'processing',
                    'pending',
                ],
                true
            )
            || in_array(
                $cryptoPayoutStatus,
                [
                    'processing',
                    'pending',
                ],
                true
            )
        ) {
            return 'processing';
        }

        /*
         * Unknown provider status:
         * keep whatever status we already have.
         */
        return $currentStatus;
    }

    /**
     * Map Quidax off-ramp status to our internal status.
     */
    protected function mapOffRampStatus(
        array $providerData,
        string $currentStatus
    ): string {
        /*
         * Never move a successful/failed/cancelled transaction backwards.
         */
        if (
            in_array(
                $currentStatus,
                [
                    'successful',
                    'failed',
                    'cancelled',
                ],
                true
            )
        ) {
            return $currentStatus;
        }

        $event = $this->normalizeStatus(
            $providerData['_event'] ?? null
        );

        $status = $this->normalizeStatus(
            $providerData['status'] ?? null
        );

        $fiatPayoutStatus = $this->normalizeStatus(
            data_get($providerData, 'fiat_payout.status')
        );

        $cryptoDepositStatus = $this->normalizeStatus(
            data_get($providerData, 'crypto_deposit.status')
        );

        /*
         * ============================================================
         * SUCCESS
         * ============================================================
         *
         * Quidax example:
         *
         * event: sell_transaction.successful
         * status: completed
         * fiat_payout.status: completed
         *
         * Local:
         *
         * status = successful
         */
        if (
            in_array(
                $event,
                [
                    'sell_transaction_successful',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'completed',
                    'success',
                    'successful',
                ],
                true
            )
            || in_array(
                $fiatPayoutStatus,
                [
                    'completed',
                    'success',
                    'successful',
                ],
                true
            )
        ) {
            return 'successful';
        }

        /*
         * ============================================================
         * FAILURE
         * ============================================================
         */
        if (
            in_array(
                $event,
                [
                    'sell_transaction_failed',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'failed',
                    'error',
                    'needs_attention',
                    'cancelled',
                    'rejected',
                ],
                true
            )
            || in_array(
                $fiatPayoutStatus,
                [
                    'failed',
                    'error',
                    'cancelled',
                    'rejected',
                ],
                true
            )
        ) {
            return 'failed';
        }

        /*
         * ============================================================
         * STILL PROCESSING
         * ============================================================
         */
        if (
            in_array(
                $event,
                [
                    'sell_transaction_processing',
                ],
                true
            )
            || in_array(
                $status,
                [
                    'processing',
                    'pending',
                    'confirmed',
                ],
                true
            )
            || in_array(
                $fiatPayoutStatus,
                [
                    'processing',
                    'pending',
                    'queued',
                    'initiated',
                ],
                true
            )
            || in_array(
                $cryptoDepositStatus,
                [
                    'accepted',
                    'processing',
                    'pending',
                ],
                true
            )
        ) {
            return 'awaiting_payout';
        }

        /*
         * Unknown provider status:
         * keep whatever status we already have.
         */
        return $currentStatus;
    }

    /**
     * Normalize provider statuses and event names.
     *
     * Example:
     *
     * buy_transaction.successful
     * ->
     * buy_transaction_successful
     */
    protected function normalizeStatus($value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        return strtolower(
            str_replace(
                [
                    ' ',
                    '-',
                    '.',
                ],
                '_',
                trim($value)
            )
        );
    }

    /**
     * Format monetary values consistently.
     */
    protected function formatAmount($value): ?string
    {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            return null;
        }

        return bcadd(
            (string) $value,
            '0',
            8
        );
    }

    /**
     * Merge metadata safely.
     */
    protected function mergeMetadata(
        ?array $existing,
        array $updates
    ): array {
        return array_merge(
            $existing ?? [],
            $updates
        );
    }

    /**
     * Sync wallet after a successful SafeHaven off-ramp.
     */
    protected function syncOffRampWallet(
        RampTransaction $transaction,
        array $providerData,
        array $metadata
    ): array {
        $user = $transaction->user;

        $payoutAmount = $this->formatAmount(
            data_get($providerData, 'fiat_payout.amount')
            ?? ($providerData['to_amount'] ?? null)
            ?? $transaction->to_amount
        );

        if (!$user) {
            return [
                'currency' => 'NGN',
                'credited_amount' => $payoutAmount,
                'balance_after' => null,
                'sync_method' => 'user_not_found',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if (!$this->isSafeHavenPayoutAccount($transaction, $user)) {
            return [
                'currency' => 'NGN',
                'credited_amount' => $payoutAmount,
                'balance_after' => null,
                'sync_method' => 'external_payout_account',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $wallet = $user->wallets()
            ->where('currency_code', 'NGN')
            ->first();

        $previousBalance = $wallet
            ? (string) $wallet->balance
            : null;

        $syncedBalance = $this->safeHavenService
            ->syncUserWalletBalance($user, 'NGN');

        $existingWalletUpdate = $metadata['wallet_update'] ?? null;

        if ($syncedBalance !== null) {
            if (
                is_array($existingWalletUpdate)
                && ($existingWalletUpdate['sync_method'] ?? null)
                    === 'safehaven_account'
                && ($existingWalletUpdate['balance_after'] ?? null)
                    === $syncedBalance
            ) {
                return array_merge(
                    $existingWalletUpdate,
                    [
                        'checked_at' => now()->toIso8601String(),
                    ]
                );
            }

            if (
                $previousBalance === null
                || bccomp(
                    $syncedBalance,
                    $previousBalance,
                    8
                ) !== 0
            ) {
                return [
                    'currency' => 'NGN',
                    'credited_amount' => $payoutAmount,
                    'balance_after' => $syncedBalance,
                    'sync_method' => 'safehaven_account',
                    'checked_at' => now()->toIso8601String(),
                ];
            }

            return [
                'currency' => 'NGN',
                'credited_amount' => $payoutAmount,
                'balance_after' => $syncedBalance,
                'sync_method' => 'safehaven_balance_unchanged',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'currency' => 'NGN',
            'credited_amount' => $payoutAmount,
            'balance_after' => $previousBalance,
            'sync_method' => $wallet
                ? 'safehaven_sync_unavailable'
                : 'wallet_not_found',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine whether the off-ramp payout account belongs
     * to the user's SafeHaven sub-account.
     */
    protected function isSafeHavenPayoutAccount(
        RampTransaction $transaction,
        $user
    ): bool {
        $account = $this->safeHavenService
            ->getUserSubAccount($user);

        if (
            !$account
            || empty($account->account_number)
            || empty($transaction->account_number)
        ) {
            return false;
        }

        if (
            (string) $account->account_number
            !== (string) $transaction->account_number
        ) {
            return false;
        }

        if (
            !empty($transaction->bank_code)
            && !empty($account->bank_code)
        ) {
            return (string) $account->bank_code
                === (string) $transaction->bank_code;
        }

        return true;
    }

    /**
     * Extract and store provider-side tracking information.
     */
    protected function providerTrackingAttributes(
        array $providerData,
        string $internalStatus
    ): array {
        $attributes = [];

        /*
         * Provider transaction ID.
         */
        if ($this->hasRampTransactionColumn('provider_transaction_id')) {
            $providerTransactionId = $this->firstDataValue(
                $providerData,
                [
                    'id',
                    'uuid',
                    'public_id',
                    'reference',
                    'transaction_id',
                    'transaction.id',
                    'fiat_deposit.id',
                    'crypto_payout.id',
                    'crypto_deposit.id',
                    'fiat_payout.id',
                ]
            );

            if (
                $providerTransactionId !== null
                && $providerTransactionId !== ''
            ) {
                $attributes['provider_transaction_id']
                    = (string) $providerTransactionId;
            }
        }

        /*
         * Blockchain transaction hash.
         */
        if ($this->hasRampTransactionColumn('transaction_hash')) {
            $transactionHash = $this->firstDataValue(
                $providerData,
                [
                    'txid',
                    'tx_id',
                    'hash',
                    'transaction_hash',
                    'transaction.hash',
                    'transaction.txid',
                    'crypto_payout.txid',
                    'crypto_payout.tx_id',
                    'crypto_payout.hash',
                    'crypto_payout.transaction_hash',
                    'crypto_deposit.txid',
                    'crypto_deposit.tx_id',
                    'crypto_deposit.hash',
                    'crypto_deposit.transaction_hash',
                ]
            );

            if (
                $transactionHash !== null
                && $transactionHash !== ''
            ) {
                $attributes['transaction_hash']
                    = (string) $transactionHash;
            }
        }

        /*
         * Store Quidax's status separately from our internal status.
         *
         * Example:
         *
         * provider_status = completed
         * status          = successful
         */
        if ($this->hasRampTransactionColumn('provider_status')) {
            $providerStatus = $this->normalizeStatus(
                $this->firstDataValue(
                    $providerData,
                    [
                        'status',
                        'fiat_deposit.status',
                        'crypto_payout.status',
                        'crypto_deposit.status',
                        'fiat_payout.status',
                    ]
                )
            );

            $attributes['provider_status']
                = $providerStatus ?: $internalStatus;
        }

        /*
         * Last time provider status was checked.
         */
        if (
            $this->hasRampTransactionColumn(
                'provider_status_checked_at'
            )
        ) {
            $attributes['provider_status_checked_at'] = now();
        }

        return $attributes;
    }

    /**
     * Return the first non-empty value from a list of nested keys.
     */
    protected function firstDataValue(
        array $data,
        array $keys
    ) {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (
                $value !== null
                && $value !== ''
            ) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Check whether a column exists before writing to it.
     */
    protected function hasRampTransactionColumn(
        string $column
    ): bool {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('ramp_transactions')
                ? Schema::getColumnListing('ramp_transactions')
                : [];
        }

        return in_array(
            $column,
            $columns,
            true
        );
    }
}
