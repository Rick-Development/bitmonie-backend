<?php

namespace App\Services;

use App\Models\RampTransaction;
use App\Models\User;
use App\Models\Withdrawals;
use Illuminate\Support\Facades\Log;


class QuidaxSpendableBalanceService
{
    protected int $scale = 18;
    protected int $displayScale = 8;
    protected array $activeWithdrawalReservationStatuses = [
        'created',
        'submitted',
        'initialized',
        'initiated',
        'pending',
        'processing',
        'confirming',
        'approved',
    ];
    protected array $terminalWithdrawalReservationStatuses = [
        'complete',
        'completed',
        'done',
        'success',
        'successful',
        'failed',
        'rejected',
        'reject',
        'cancelled',
        'canceled',
        'reversed',
        'refunded',
        'expired',
        'error',
    ];
    protected array $activeOffRampReservationStatuses = [
        'processing',
    ];

  public function augmentWalletPayload(
    User $user,
    array $wallet,
    ?string $currency = null,
    $exchangeRate = null,
    array $options = []
): array
    {
    
    
        $currencyCode = $this->normalizeCurrency(
            $currency
                ?? $wallet['currency']
                ?? $wallet['currency_code']
                ?? null
        );

        $providerBalance = $this->decimal($wallet['balance'] ?? '0');
        $providerLockedBalance = $this->decimal($wallet['locked'] ?? '0');
        $providerConvertedBalance = $this->decimal($wallet['converted_balance'] ?? '0');

        $reservedOutgoingBalance = $currencyCode === ''
            ? '0'
            : $this->getReservedOutgoingBalance($user, $currencyCode, $options);

        if ($this->compare($reservedOutgoingBalance, $providerBalance) > 0) {
            Log::error('Quidax reserved outgoing balance exceeds provider balance; clamping local reservation for response.', [
                'user_id' => $user->id,
                'currency' => $currencyCode,
                'provider_balance' => $this->formatDecimal($providerBalance),
                'reserved_outgoing_balance' => $this->formatDecimal($reservedOutgoingBalance),
            ]);

            $reservedOutgoingBalance = $providerBalance;
        }

        $availableBalance = $this->maxZero($this->sub($providerBalance, $reservedOutgoingBalance));
        $lockedBalance = $this->add($providerLockedBalance, $reservedOutgoingBalance);
        $totalBalance = $this->add($providerBalance, $providerLockedBalance);

       $exchangeRate = $exchangeRate !== null
    ? $this->decimal((string) $exchangeRate)
    : null;

if ($exchangeRate !== null && $this->compare($exchangeRate, '0') > 0) {
    $availableConvertedBalance = $this->mul($availableBalance, $exchangeRate);
    $lockedConvertedBalance = $this->mul($lockedBalance, $exchangeRate);
    $totalConvertedBalance = $this->mul($totalBalance, $exchangeRate);
} else {
    // Fallback to Quidax's converted balance if no exchange rate was supplied.
    $conversionRate = $this->compare($providerBalance, '0') > 0
        ? $this->div($providerConvertedBalance, $providerBalance)
        : null;

    $availableConvertedBalance = $conversionRate === null
        ? $providerConvertedBalance
        : $this->mul($availableBalance, $conversionRate);

    $lockedConvertedBalance = $conversionRate === null
        ? '0'
        : $this->mul($lockedBalance, $conversionRate);

    $totalConvertedBalance = $this->add(
        $availableConvertedBalance,
        $lockedConvertedBalance
    );
}

        $wallet['provider_balance'] = $this->formatDecimal($providerBalance);
        $wallet['provider_locked_balance'] = $this->formatDecimal($providerLockedBalance);
        $wallet['provider_converted_balance'] = $this->formatDecimal($providerConvertedBalance);
        $wallet['reserved_outgoing_balance'] = $this->formatDecimal($reservedOutgoingBalance);
        
        // Keep the legacy balance field spendable so older mobile builds show what can actually be sold/sent.
        $wallet['balance'] = $this->formatDecimal($availableBalance);
        $wallet['available_balance'] = $this->formatDecimal($availableBalance);
        $wallet['locked_balance'] = $this->formatDecimal($lockedBalance);
        $wallet['total_balance'] = $this->formatDecimal($totalBalance);
        $wallet['available_converted_balance'] = $this->formatDecimal($availableConvertedBalance);
        $wallet['locked_converted_balance'] = $this->formatDecimal($lockedConvertedBalance);
        $wallet['reference_currency'] = 'NGN';
        $wallet['provider_converted_balance'] = $this->formatDecimal($totalConvertedBalance);
        if (array_key_exists('converted_balance', $wallet)) {
            $wallet['converted_balance'] = $this->formatDecimal($availableConvertedBalance);
        }

        Log::info('Quidax spendable balance calculated.', [
            'user_id' => $user->id,
            'currency' => $currencyCode,
            'provider_balance' => $wallet['provider_balance'],
            'provider_locked_balance' => $wallet['provider_locked_balance'],
            'reserved_outgoing_balance' => $wallet['reserved_outgoing_balance'],
            'available_balance' => $wallet['available_balance'],
            'exclude_ramp_transaction_id' => $options['exclude_ramp_transaction_id'] ?? null,
        ]);
        return $wallet;
    }

    public function getReservedOutgoingBalance(User $user, string $currency, array $options = []): string
    {
        $currencyCode = $this->normalizeCurrency($currency);

        return $this->formatDecimal($this->add(
            $this->sumPendingCryptoWithdrawals($user, $currencyCode),
            $this->sumPendingOffRampReservations($user, $currencyCode, $options)
        ));
    }

    protected function sumPendingCryptoWithdrawals(User $user, string $currency): string
    {
        $total = '0';
        $countedReservationKeys = [];

        Withdrawals::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(function (Withdrawals $withdrawal) use ($currency, &$total, &$countedReservationKeys, $user) {
                $decision = $this->inspectCryptoWithdrawalReservation($withdrawal, $currency);

                if (!$decision['is_crypto_reservation']) {
                    return;
                }

                if (!$decision['active']) {
                    if (in_array($decision['reason'], ['stale_active_reservation', 'terminal_status', 'terminal_provider_status', 'terminal_user_status', 'terminal_trans_id', 'failure_marker', 'invalid_reservation_amount'], true)) {
                        Log::warning('Crypto withdrawal reservation excluded from spendable balance.', $this->reservationLogContext($withdrawal, $decision));
                    }

                    return;
                }

                $reservationKey = $decision['reservation_key'];
                if (isset($countedReservationKeys[$reservationKey])) {
                    Log::warning('Duplicate crypto withdrawal reservation suppressed from spendable balance.', $this->reservationLogContext($withdrawal, $decision) + [
                        'first_withdrawal_id' => $countedReservationKeys[$reservationKey],
                    ]);

                    return;
                }

                $countedReservationKeys[$reservationKey] = $withdrawal->id;
                $total = $this->add($total, $decision['amount']);

                Log::info('Active crypto withdrawal reservation counted.', $this->reservationLogContext($withdrawal, $decision) + [
                    'user_id' => $user->id,
                    'running_reserved_outgoing_balance' => $this->formatDecimal($total),
                ]);
            });

        return $total;
    }

    public function inspectCryptoWithdrawalReservation(Withdrawals $withdrawal, ?string $currency = null): array
    {
        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $userMeta = is_array($withdrawal->user) ? $withdrawal->user : [];
        $reservationType = (string) ($walletMeta['reservation_type'] ?? '');
        $currencyCode = $this->normalizeCurrency($withdrawal->currency);
        $requestedCurrency = $currency === null ? null : $this->normalizeCurrency($currency);
        $status = $this->normalizeStatus($walletMeta['status'] ?? null);
        $providerStatus = $this->normalizeStatus($walletMeta['provider_status'] ?? null);
        $userStatus = $this->normalizeStatus($userMeta['status'] ?? null);
        $amount = $this->decimal($withdrawal->total ?? $withdrawal->amount ?? '0');

        $base = [
            'active' => false,
            'is_crypto_reservation' => $reservationType === 'crypto_withdrawal',
            'reason' => null,
            'currency' => $currencyCode,
            'requested_currency' => $requestedCurrency,
            'status' => $status,
            'provider_status' => $providerStatus,
            'user_status' => $userStatus,
            'amount' => $this->formatDecimal($amount),
            'reservation_key' => $this->cryptoWithdrawalReservationKey($withdrawal),
            'stale_after_hours' => $this->reservationTtlHours(),
        ];

        if ($reservationType !== 'crypto_withdrawal') {
            return array_merge($base, ['reason' => 'not_crypto_withdrawal_reservation']);
        }

        if ($requestedCurrency !== null && $currencyCode !== $requestedCurrency) {
            return array_merge($base, ['reason' => 'currency_mismatch']);
        }

        if ($this->compare($amount, '0') <= 0) {
            return array_merge($base, ['reason' => 'invalid_reservation_amount']);
        }

        if ($this->reservationAlreadyReleased($walletMeta)) {
            return array_merge($base, ['reason' => 'reservation_released']);
        }

        if ($this->isTerminalStatus($status)) {
            return array_merge($base, ['reason' => 'terminal_status']);
        }

        if ($this->isTerminalStatus($providerStatus)) {
            return array_merge($base, ['reason' => 'terminal_provider_status']);
        }

        if ($this->isTerminalStatus($userStatus)) {
            return array_merge($base, ['reason' => 'terminal_user_status']);
        }

        if ($this->hasTerminalTransId($withdrawal)) {
            return array_merge($base, ['reason' => 'terminal_trans_id']);
        }

        if (!empty($walletMeta['failure_reason']) || !empty($walletMeta['failure_response'])) {
            return array_merge($base, ['reason' => 'failure_marker']);
        }

        if (!$this->isActiveStatus($status)) {
            return array_merge($base, ['reason' => 'non_active_status']);
        }

        if ($this->isStaleActiveReservation($withdrawal)) {
            return array_merge($base, ['reason' => 'stale_active_reservation']);
        }

        return array_merge($base, [
            'active' => true,
            'reason' => 'active',
        ]);
    }

    public function cryptoWithdrawalReservationKey(Withdrawals $withdrawal): string
    {
        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $key = (string) (
            $withdrawal->reference
            ?? $walletMeta['reference']
            ?? $walletMeta['transaction_reference']
            ?? $withdrawal->trans_id
            ?? $withdrawal->id
        );

        $key = strtolower(trim($key));
        $key = preg_replace('/^(pending|processing|initiated|created|submitted):/i', '', $key) ?: $key;

        return $key !== '' ? $key : (string) $withdrawal->id;
    }

    public function reservationTtlHours(): int
    {
        $configured = (int) config('services.quidax.withdrawal_reservation_ttl_hours', 24);

        return $configured > 0 ? $configured : 24;
    }

    protected function sumPendingOffRampReservations(User $user, string $currency, array $options = []): string
    {
        $excludeRampTransactionId = $options['exclude_ramp_transaction_id'] ?? null;
        $total = '0';

        RampTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'off_ramp')
            ->whereIn('status', ['processing', 'awaiting_payout'])
            ->get()
            ->each(function (RampTransaction $transaction) use ($currency, $excludeRampTransactionId, $user, &$total) {
                $decision = $this->inspectOffRampReservation(
                    $transaction,
                    $currency,
                    $excludeRampTransactionId ? (int) $excludeRampTransactionId : null
                );

                if (!$decision['active']) {
                    if (in_array($decision['reason'], ['awaiting_payout_released', 'reservation_released', 'stale_active_reservation', 'terminal_status', 'terminal_provider_status', 'invalid_reservation_amount'], true)) {
                        Log::warning('Off-ramp reservation excluded from spendable balance.', $this->offRampReservationLogContext($transaction, $decision));
                    }

                    return;
                }

                $total = $this->add($total, $decision['amount']);

                Log::info('Active off-ramp reservation counted.', $this->offRampReservationLogContext($transaction, $decision) + [
                    'user_id' => $user->id,
                    'running_reserved_outgoing_balance' => $this->formatDecimal($total),
                ]);
            });

        return $total;
    }

    public function inspectOffRampReservation(RampTransaction $transaction, ?string $currency = null, ?int $excludeRampTransactionId = null): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $currencyCode = $this->normalizeCurrency($transaction->from_currency);
        $requestedCurrency = $currency === null ? null : $this->normalizeCurrency($currency);
        $status = $this->normalizeStatus($transaction->status ?? null);
        $providerStatuses = $this->offRampProviderStatuses($transaction);
        $amount = $this->offRampReservedAmount($transaction);

        $base = [
            'active' => false,
            'is_off_ramp_reservation' => (string) $transaction->type === 'off_ramp',
            'reason' => null,
            'currency' => $currencyCode,
            'requested_currency' => $requestedCurrency,
            'status' => $status,
            'provider_statuses' => $providerStatuses,
            'amount' => $this->formatDecimal($amount),
            'stale_after_hours' => $this->reservationTtlHours(),
        ];

        if ((string) $transaction->type !== 'off_ramp') {
            return array_merge($base, ['reason' => 'not_off_ramp_reservation']);
        }

        if ($excludeRampTransactionId !== null && (int) $transaction->id === $excludeRampTransactionId) {
            return array_merge($base, ['reason' => 'current_transaction_excluded']);
        }

        if ($requestedCurrency !== null && $currencyCode !== $requestedCurrency) {
            return array_merge($base, ['reason' => 'currency_mismatch']);
        }

        if ($this->compare($amount, '0') <= 0) {
            return array_merge($base, ['reason' => 'invalid_reservation_amount']);
        }

        if ($this->reservationAlreadyReleased($metadata)) {
            return array_merge($base, ['reason' => 'reservation_released']);
        }

        if ($this->isTerminalStatus($status)) {
            return array_merge($base, ['reason' => 'terminal_status']);
        }

        foreach ($providerStatuses as $providerStatus) {
            if ($this->isTerminalStatus($providerStatus)) {
                return array_merge($base, ['reason' => 'terminal_provider_status']);
            }
        }

        if ($status === 'awaiting_payout') {
            return array_merge($base, ['reason' => 'awaiting_payout_released']);
        }

        if (!in_array($status, $this->activeOffRampReservationStatuses, true)) {
            return array_merge($base, ['reason' => 'non_active_status']);
        }

        if ($this->isStaleOffRampReservation($transaction)) {
            return array_merge($base, ['reason' => 'stale_active_reservation']);
        }

        return array_merge($base, [
            'active' => true,
            'reason' => 'active',
        ]);
    }

    protected function offRampReservedAmount(RampTransaction $transaction): string
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $fees = is_array($metadata['fees'] ?? null) ? $metadata['fees'] : [];

        $reservedTotal = $fees['total'] ?? null;
        if ($reservedTotal !== null && $reservedTotal !== '') {
            return $this->decimal($reservedTotal);
        }

        return $this->add(
            $transaction->from_amount ?? '0',
            $this->add($transaction->blockchain_fee ?? '0', $transaction->processor_fee ?? '0')
        );
    }

    protected function offRampProviderStatuses(RampTransaction $transaction): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $paths = [
            'provider_status',
            'status',
            'provider_transaction.status',
            'settlement.crypto_deposit.status',
            'settlement.fiat_payout.status',
            'confirm_data.status',
            'confirm_data.data.status',
            'job.main_account_withdrawal.status',
            'job.main_account_withdrawal.data.status',
            'job.ramp_withdrawal.status',
            'job.ramp_withdrawal.data.status',
        ];

        $statuses = [];
        foreach ($paths as $path) {
            $status = $this->normalizeStatus(data_get($metadata, $path));
            if ($status !== '') {
                $statuses[$status] = true;
            }
        }

        return array_keys($statuses);
    }

    protected function isStaleOffRampReservation(RampTransaction $transaction): bool
    {
        $timestamp = $transaction->updated_at ?? $transaction->created_at;

        if (!$timestamp) {
            return false;
        }

        return $timestamp->lt(now()->subHours($this->reservationTtlHours()));
    }

    protected function offRampReservationLogContext(RampTransaction $transaction, array $decision): array
    {
        return [
            'ramp_transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'merchant_reference' => $transaction->merchant_reference,
            'reference' => $transaction->reference,
            'currency' => $decision['currency'] ?? $this->normalizeCurrency($transaction->from_currency),
            'network' => $transaction->network,
            'amount' => $decision['amount'] ?? $this->formatDecimal($this->offRampReservedAmount($transaction)),
            'status' => $decision['status'] ?? $this->normalizeStatus($transaction->status),
            'provider_statuses' => $decision['provider_statuses'] ?? [],
            'reason' => $decision['reason'] ?? null,
            'created_at' => optional($transaction->created_at)->toDateTimeString(),
            'updated_at' => optional($transaction->updated_at)->toDateTimeString(),
        ];
    }

    protected function normalizeCurrency(?string $currency): string
    {
        return strtolower(trim((string) $currency));
    }

    protected function normalizeStatus($status): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim((string) $status)));
    }

    protected function isActiveStatus(string $status): bool
    {
        return in_array($status, $this->activeWithdrawalReservationStatuses, true);
    }

    protected function isTerminalStatus(string $status): bool
    {
        return in_array($status, $this->terminalWithdrawalReservationStatuses, true);
    }

    protected function hasTerminalTransId(Withdrawals $withdrawal): bool
    {
        $transId = strtolower(trim((string) $withdrawal->trans_id));

        foreach (['failed:', 'rejected:', 'reject:', 'cancelled:', 'canceled:', 'reversed:', 'completed:', 'success:', 'successful:', 'expired:'] as $prefix) {
            if (str_starts_with($transId, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function reservationAlreadyReleased(array $walletMeta): bool
    {
        return !empty($walletMeta['reservation_released_at'])
            || !empty($walletMeta['released_at'])
            || !empty($walletMeta['reservation_release_reason']);
    }

    protected function isStaleActiveReservation(Withdrawals $withdrawal): bool
    {
        $timestamp = $withdrawal->updated_at ?? $withdrawal->created_at;

        if (!$timestamp) {
            return false;
        }

        return $timestamp->lt(now()->subHours($this->reservationTtlHours()));
    }

    protected function reservationLogContext(Withdrawals $withdrawal, array $decision): array
    {
        return [
            'withdrawal_id' => $withdrawal->id,
            'user_id' => $withdrawal->user_id,
            'reference' => $withdrawal->reference,
            'trans_id' => $withdrawal->trans_id,
            'currency' => $decision['currency'] ?? $this->normalizeCurrency($withdrawal->currency),
            'amount' => $decision['amount'] ?? $this->formatDecimal($withdrawal->total ?? $withdrawal->amount ?? '0'),
            'status' => $decision['status'] ?? null,
            'provider_status' => $decision['provider_status'] ?? null,
            'user_status' => $decision['user_status'] ?? null,
            'reason' => $decision['reason'] ?? null,
            'reservation_key' => $decision['reservation_key'] ?? $this->cryptoWithdrawalReservationKey($withdrawal),
            'created_at' => optional($withdrawal->created_at)->toDateTimeString(),
            'updated_at' => optional($withdrawal->updated_at)->toDateTimeString(),
        ];
    }

    protected function decimal($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            $value = sprintf('%.18F', $value);
        }

        $value = trim((string) $value);
        if (!is_numeric($value)) {
            return '0';
        }

        return bcadd($value, '0', $this->scale);
    }

    protected function add($left, $right): string
    {
        return bcadd($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function sub($left, $right): string
    {
        return bcsub($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function mul($left, $right): string
    {
        return bcmul($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function div($left, $right): string
    {
        if ($this->compare($right, '0') === 0) {
            return '0';
        }

        return bcdiv($this->decimal($left), $this->decimal($right), $this->scale);
    }

    protected function compare($left, $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), $this->displayScale);
    }

    protected function maxZero(string $value): string
    {
        return $this->compare($value, '0') < 0 ? '0' : $value;
    }

    protected function formatDecimal($value): string
    {
        $formatted = bcadd($this->decimal($value), '0', $this->displayScale);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
