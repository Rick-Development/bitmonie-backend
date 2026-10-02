<?php

namespace App\Services;

use App\Models\RampTransaction;
use Illuminate\Support\Facades\DB;

class RampTransactionSyncService
{
    public function __construct(
        protected SafeHavenService $safeHavenService
    ) {
    }

    public function syncOnRamp(RampTransaction $transaction, array $providerData): RampTransaction
    {
        return DB::transaction(function () use ($transaction, $providerData) {
            /** @var RampTransaction $lockedTransaction */
            $lockedTransaction = RampTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $this->mapOnRampStatus($providerData, (string) $lockedTransaction->status);

            $lockedTransaction->update([
                'public_id' => $lockedTransaction->public_id ?? ($providerData['public_id'] ?? null),
                'reference' => $lockedTransaction->reference ?? ($providerData['reference'] ?? null),
                'to_amount' => $providerData['to_amount'] ?? $lockedTransaction->to_amount,
                'status' => $status,
                'metadata' => $this->mergeMetadata($lockedTransaction->metadata, [
                    'provider_transaction' => $providerData,
                    'settlement' => [
                        'fiat_deposit' => $providerData['fiat_deposit'] ?? null,
                        'crypto_payout' => $providerData['crypto_payout'] ?? null,
                    ],
                    'provider_status_checked_at' => now()->toIso8601String(),
                ]),
            ]);

            return $lockedTransaction->fresh();
        });
    }

    public function syncOffRamp(RampTransaction $transaction, array $providerData): RampTransaction
    {
        return DB::transaction(function () use ($transaction, $providerData) {
            /** @var RampTransaction $lockedTransaction */
            $lockedTransaction = RampTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = $this->mapOffRampStatus($providerData, (string) $lockedTransaction->status);
            $metadata = $lockedTransaction->metadata ?? [];

            $walletUpdate = $metadata['wallet_update'] ?? null;
            if (!is_array($walletUpdate)) {
                $walletUpdate = null;
            }

            if ($status === 'completed') {
                $walletUpdate = $this->syncOffRampWallet($lockedTransaction, $providerData, $metadata);
            }

            $lockedTransaction->update([
                'public_id' => $lockedTransaction->public_id ?? ($providerData['public_id'] ?? null),
                'reference' => $lockedTransaction->reference ?? ($providerData['reference'] ?? null),
                'network' => strtolower((string) ($providerData['network'] ?? $lockedTransaction->network)),
                'wallet_address' => $providerData['address']
                    ?? $providerData['crypto_deposit']['address']
                    ?? $lockedTransaction->wallet_address,
                'to_amount' => $providerData['to_amount']
                    ?? $providerData['fiat_payout']['amount']
                    ?? $lockedTransaction->to_amount,
                'status' => $status,
                'metadata' => $this->mergeMetadata($metadata, [
                    'provider_transaction' => $providerData,
                    'settlement' => [
                        'crypto_deposit' => $providerData['crypto_deposit'] ?? null,
                        'fiat_payout' => $providerData['fiat_payout'] ?? null,
                    ],
                    'wallet_update' => $walletUpdate,
                    'provider_status_checked_at' => now()->toIso8601String(),
                ]),
            ]);

            return $lockedTransaction->fresh();
        });
    }

    public function handleWebhook(array $payload): ?RampTransaction
    {
        $event = strtolower((string) ($payload['event'] ?? ''));
        $data = $payload['data'] ?? [];

        if (!is_array($data)) {
            return null;
        }

        $merchantReference = $data['merchant_reference'] ?? null;
        if (!$merchantReference) {
            return null;
        }

        $mode = strtolower((string) ($data['mode'] ?? ''));
        $type = str_contains($event, 'sell_transaction') || $mode === 'sell'
            ? 'off_ramp'
            : 'on_ramp';

        $transaction = RampTransaction::where('merchant_reference', $merchantReference)
            ->where('type', $type)
            ->first();

        if (!$transaction) {
            return null;
        }

        $data['_event'] = $payload['event'] ?? null;

        return $type === 'off_ramp'
            ? $this->syncOffRamp($transaction, $data)
            : $this->syncOnRamp($transaction, $data);
    }

    protected function syncOffRampWallet(RampTransaction $transaction, array $providerData, array $metadata): array
    {
        $user = $transaction->user;
        $payoutAmount = $this->formatAmount(
            $providerData['fiat_payout']['amount']
            ?? $providerData['to_amount']
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

        $wallet = $user->wallets()->where('currency_code', 'NGN')->first();
        $previousBalance = $wallet ? (string) $wallet->balance : null;
        $syncedBalance = $this->safeHavenService->syncUserWalletBalance($user, 'NGN');
        $existingWalletUpdate = $metadata['wallet_update'] ?? null;

        if ($syncedBalance !== null) {
            if (
                is_array($existingWalletUpdate)
                && ($existingWalletUpdate['sync_method'] ?? null) === 'safehaven_account'
                && ($existingWalletUpdate['balance_after'] ?? null) === $syncedBalance
            ) {
                return array_merge($existingWalletUpdate, [
                    'checked_at' => now()->toIso8601String(),
                ]);
            }

            if ($previousBalance === null || bccomp($syncedBalance, $previousBalance, 8) !== 0) {
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
            'sync_method' => $wallet ? 'safehaven_sync_unavailable' : 'wallet_not_found',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function isSafeHavenPayoutAccount(RampTransaction $transaction, $user): bool
    {
        $account = $this->safeHavenService->getUserSubAccount($user);

        if (!$account || empty($account->account_number) || empty($transaction->account_number)) {
            return false;
        }

        if ((string) $account->account_number !== (string) $transaction->account_number) {
            return false;
        }

        if (!empty($transaction->bank_code) && !empty($account->bank_code)) {
            return (string) $transaction->bank_code === (string) $account->bank_code;
        }

        return true;
    }

    protected function mapOnRampStatus(array $providerData, string $currentStatus): string
    {
        $event = $this->normalizeStatus($providerData['_event'] ?? null);
        $status = $this->normalizeStatus($providerData['status'] ?? null);
        $fiatDepositStatus = $this->normalizeStatus($providerData['fiat_deposit']['status'] ?? null);
        $cryptoPayoutStatus = $this->normalizeStatus($providerData['crypto_payout']['status'] ?? null);

        if (in_array($event, ['buy_transaction_successful'], true) || in_array($status, ['completed', 'success', 'successful'], true) || in_array($cryptoPayoutStatus, ['completed', 'success', 'successful'], true)) {
            return 'completed';
        }

        if (in_array($event, ['buy_transaction_failed'], true) || in_array($status, ['failed', 'error', 'cancelled', 'rejected'], true) || in_array($cryptoPayoutStatus, ['failed', 'error', 'cancelled', 'rejected'], true)) {
            return 'failed';
        }

        if (
            in_array($event, ['buy_transaction_processing'], true)
            || in_array($status, ['processing', 'pending', 'confirmed'], true)
            || in_array($fiatDepositStatus, ['success', 'processing', 'pending'], true)
            || in_array($cryptoPayoutStatus, ['processing', 'pending'], true)
        ) {
            return 'processing';
        }

        return $currentStatus;
    }

    protected function mapOffRampStatus(array $providerData, string $currentStatus): string
    {
        $event = $this->normalizeStatus($providerData['_event'] ?? null);
        $status = $this->normalizeStatus($providerData['status'] ?? null);
        $fiatPayoutStatus = $this->normalizeStatus($providerData['fiat_payout']['status'] ?? null);
        $cryptoDepositStatus = $this->normalizeStatus($providerData['crypto_deposit']['status'] ?? null);

        if (in_array($event, ['sell_transaction_successful'], true) || in_array($status, ['completed', 'success', 'successful'], true) || in_array($fiatPayoutStatus, ['completed', 'success', 'successful'], true)) {
            return 'completed';
        }

        if (
            in_array($event, ['sell_transaction_failed'], true)
            || in_array($status, ['failed', 'error', 'needs_attention', 'cancelled', 'rejected'], true)
            || in_array($fiatPayoutStatus, ['failed', 'error', 'cancelled', 'rejected'], true)
        ) {
            return 'failed';
        }

        if (
            in_array($event, ['sell_transaction_processing'], true)
            || in_array($status, ['processing', 'pending', 'confirmed'], true)
            || in_array($fiatPayoutStatus, ['processing', 'pending', 'queued', 'initiated'], true)
            || in_array($cryptoDepositStatus, ['accepted', 'processing', 'pending'], true)
        ) {
            return 'awaiting_payout';
        }

        return $currentStatus;
    }

    protected function normalizeStatus($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return strtolower(str_replace([' ', '-', '.'], '_', trim($value)));
    }

    protected function formatAmount($value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }

    protected function mergeMetadata(?array $existing, array $updates): array
    {
        return array_merge($existing ?? [], $updates);
    }
}
