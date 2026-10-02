<?php

namespace App\Services;

use App\Models\CommissionWallet;
use App\Models\CommissionWalletTransaction;
use App\Models\OrderTransaction;
use App\Models\GraphTransaction;
use App\Models\RampTransaction;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralCommissionSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Support\UserScopedCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SharedReferralCommissionService
{
    public function capture(User $referredUser, string $transactionId, string $transactionType, string $platformFee, string $currencyCode = 'NGN', array $metadata = []): ?ReferralCommission
    {
        $platformFee = $this->normalize($platformFee);

        if (bccomp($platformFee, '0', 18) <= 0) {
            return null;
        }

        $referrer = $this->referrerFor($referredUser);
        if (!$referrer || $referrer->id === $referredUser->id) {
            return null;
        }

        $settings = ReferralCommissionSetting::current();
        $commissionAmount = $this->calculateCommission($platformFee, (string) $settings->commission_rate_percent);
        $status = $settings->auto_credit ? ReferralCommission::STATUS_CREDITED : ReferralCommission::STATUS_PENDING;

        return DB::transaction(function () use ($referrer, $referredUser, $transactionId, $transactionType, $platformFee, $currencyCode, $metadata, $settings, $commissionAmount, $status) {
            $commission = ReferralCommission::firstOrCreate(
                [
                    'transaction_id' => $transactionId,
                    'transaction_type' => $transactionType,
                ],
                [
                    'referrer_user_id' => $referrer->id,
                    'referred_user_id' => $referredUser->id,
                    'currency_code' => strtoupper($currencyCode),
                    'platform_fee_amount' => $platformFee,
                    'commission_rate_percent' => $settings->commission_rate_percent,
                    'commission_amount' => $commissionAmount,
                    'status' => $status,
                    'metadata' => $metadata,
                    'credited_at' => $status === ReferralCommission::STATUS_CREDITED ? now() : null,
                ]
            );

            if ($commission->wasRecentlyCreated) {
                $wallet = $this->walletFor($referrer->id, strtoupper($currencyCode), true);

                if ($status === ReferralCommission::STATUS_CREDITED) {
                    $wallet->available_balance = bcadd((string) $wallet->available_balance, $commissionAmount, 18);
                } else {
                    $wallet->pending_balance = bcadd((string) $wallet->pending_balance, $commissionAmount, 18);
                }

                $wallet->save();

                CommissionWalletTransaction::create([
                    'commission_wallet_id' => $wallet->id,
                    'user_id' => $referrer->id,
                    'referral_commission_id' => $commission->id,
                    'type' => 'commission_' . $status,
                    'status' => 'successful',
                    'currency_code' => strtoupper($currencyCode),
                    'amount' => $commissionAmount,
                    'balance_after' => $status === ReferralCommission::STATUS_CREDITED ? $wallet->available_balance : $wallet->pending_balance,
                    'reference' => $this->reference('commission'),
                    'metadata' => ['transaction_id' => $transactionId, 'transaction_type' => $transactionType],
                ]);

                $this->flushCache($referrer->id);
            }

            return $commission;
        });
    }

    public function captureFromTransaction(Transaction $transaction): ?ReferralCommission
    {
        $status = (int) ($transaction->status ?? 0);
        if ($status !== 1) {
            return null;
        }

        $user = $transaction->user ?: User::find($transaction->user_id);
        if (!$user) {
            return null;
        }

        return $this->capture(
            $user,
            (string) ($transaction->trx_id ?: $transaction->id),
            (string) ($transaction->type ?: 'transaction'),
            (string) ($transaction->total_charge ?? 0),
            (string) ($transaction->request_currency ?: $transaction->payment_currency ?: 'NGN'),
            ['source_model' => Transaction::class, 'source_id' => $transaction->id]
        );
    }

    public function captureFromOrderTransaction(OrderTransaction $transaction): ?ReferralCommission
    {
        $wallet = $transaction->wallet;
        $user = $wallet?->user;
        if (!$user) {
            return null;
        }

        $metadata = $transaction->metadata ?? [];
        $fee = data_get($metadata, 'platform_fee')
            ?? data_get($metadata, 'fees.platform_fee')
            ?? data_get($metadata, 'fee_amount')
            ?? data_get($metadata, 'fee')
            ?? 0;

        $currency = data_get($metadata, 'currency')
            ?? data_get($metadata, 'currency_code')
            ?? $wallet->currency_code
            ?? $wallet->quote_currency_code
            ?? 'NGN';

        return $this->capture(
            $user,
            (string) ($transaction->reference ?: $transaction->id),
            (string) data_get($metadata, 'source', 'order_transaction'),
            (string) $fee,
            (string) $currency,
            ['source_model' => OrderTransaction::class, 'source_id' => $transaction->id, 'metadata' => $metadata]
        );
    }

    public function captureFromRampTransaction(RampTransaction $transaction): ?ReferralCommission
    {
        $status = strtolower((string) ($transaction->status ?? ''));
        if (!in_array($status, ['completed', 'success', 'successful'], true)) {
            return null;
        }

        $user = $transaction->user ?: User::find($transaction->user_id);
        if (!$user) {
            return null;
        }

        $metadata = $transaction->metadata ?? [];
        $fee = data_get($metadata, 'fees.platform_fee')
            ?? data_get($metadata, 'sell_balance_validation.platform_fee')
            ?? data_get($metadata, 'platform_fee')
            ?? 0;

        return $this->capture(
            $user,
            (string) ($transaction->merchant_reference ?: $transaction->reference ?: $transaction->id),
            (string) ($transaction->type ?: 'ramp_transaction'),
            (string) $fee,
            (string) ($transaction->from_currency ?: $transaction->to_currency ?: 'NGN'),
            ['source_model' => RampTransaction::class, 'source_id' => $transaction->id]
        );
    }

    public function captureFromGraphTransaction(GraphTransaction $transaction): ?ReferralCommission
    {
        $status = strtolower((string) ($transaction->status ?? ''));
        if (!in_array($status, ['completed', 'success', 'successful'], true)) {
            return null;
        }

        $user = $transaction->user ?: User::find($transaction->user_id);
        if (!$user) {
            return null;
        }

        $metadata = $transaction->metadata ?? [];
        $fee = data_get($metadata, 'fees.platform_fee')
            ?? data_get($metadata, 'platform_fee')
            ?? data_get($metadata, 'fee')
            ?? 0;

        return $this->capture(
            $user,
            (string) ($transaction->reference ?: $transaction->transaction_id ?: $transaction->id),
            'usd_' . (string) ($transaction->type ?: 'graph_transaction'),
            (string) $fee,
            (string) ($transaction->currency ?: 'USD'),
            ['source_model' => GraphTransaction::class, 'source_id' => $transaction->id]
        );
    }

    public function approve(ReferralCommission $commission): ReferralCommission
    {
        if ($commission->status === ReferralCommission::STATUS_CREDITED) {
            return $commission;
        }

        return DB::transaction(function () use ($commission) {
            $commission = ReferralCommission::whereKey($commission->id)->lockForUpdate()->firstOrFail();
            $wallet = $this->walletFor($commission->referrer_user_id, $commission->currency_code, true);

            if ($commission->status === ReferralCommission::STATUS_PENDING) {
                $wallet->pending_balance = bcsub((string) $wallet->pending_balance, (string) $commission->commission_amount, 18);
            }

            $wallet->available_balance = bcadd((string) $wallet->available_balance, (string) $commission->commission_amount, 18);
            $wallet->save();

            $commission->update([
                'status' => ReferralCommission::STATUS_CREDITED,
                'credited_at' => now(),
                'flagged_at' => null,
            ]);

            CommissionWalletTransaction::create([
                'commission_wallet_id' => $wallet->id,
                'user_id' => $commission->referrer_user_id,
                'referral_commission_id' => $commission->id,
                'type' => 'commission_approved',
                'status' => 'successful',
                'currency_code' => $commission->currency_code,
                'amount' => $commission->commission_amount,
                'balance_after' => $wallet->available_balance,
                'reference' => $this->reference('approve'),
            ]);

            $this->flushCache($commission->referrer_user_id);

            return $commission->refresh();
        });
    }

    public function flag(ReferralCommission $commission): ReferralCommission
    {
        $commission->update([
            'status' => ReferralCommission::STATUS_FLAGGED,
            'flagged_at' => now(),
        ]);

        $this->flushCache($commission->referrer_user_id);

        return $commission->refresh();
    }

    public function withdraw(User $user, string $amount, string $currencyCode = 'NGN'): CommissionWalletTransaction
    {
        $amount = $this->normalize($amount);
        $currencyCode = strtoupper($currencyCode);

        return DB::transaction(function () use ($user, $amount, $currencyCode) {
            $commissionWallet = $this->walletFor($user->id, $currencyCode, true);

            if (bccomp((string) $commissionWallet->available_balance, $amount, 18) < 0) {
                throw new RuntimeException('Insufficient commission balance.');
            }

            $mainWallet = $this->mainWalletFor($user->id, $currencyCode, true);
            $commissionWallet->available_balance = bcsub((string) $commissionWallet->available_balance, $amount, 18);
            $commissionWallet->withdrawn_balance = bcadd((string) $commissionWallet->withdrawn_balance, $amount, 18);
            $commissionWallet->save();

            $mainWallet->balance = bcadd((string) $mainWallet->balance, $amount, 18);
            $mainWallet->save();

            $reference = $this->reference('withdraw');

            $transaction = CommissionWalletTransaction::create([
                'commission_wallet_id' => $commissionWallet->id,
                'user_id' => $user->id,
                'type' => 'withdrawal_to_main_wallet',
                'status' => 'successful',
                'currency_code' => $currencyCode,
                'amount' => $amount,
                'balance_after' => $commissionWallet->available_balance,
                'reference' => $reference,
            ]);

            OrderTransaction::create([
                'user_wallet_id' => $mainWallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $mainWallet->balance,
                'reference' => $reference,
                'metadata' => ['source' => 'shared_referral_commission_withdrawal'],
            ]);

            $this->flushCache($user->id);

            return $transaction;
        });
    }

    public function walletFor(int $userId, string $currencyCode = 'NGN', bool $lock = false): CommissionWallet
    {
        $wallet = CommissionWallet::firstOrCreate(
            ['user_id' => $userId, 'currency_code' => strtoupper($currencyCode)],
            ['available_balance' => '0', 'pending_balance' => '0', 'withdrawn_balance' => '0']
        );

        if ($lock) {
            return CommissionWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }

        return $wallet;
    }

    protected function mainWalletFor(int $userId, string $currencyCode, bool $lock = false): UserWallet
    {
        $query = UserWallet::where('user_id', $userId)
            ->where(function ($walletQuery) use ($currencyCode) {
                $walletQuery->where('currency_code', $currencyCode)
                    ->orWhere('quote_currency_code', $currencyCode)
                    ->orWhereHas('currency', fn ($currency) => $currency->where('code', $currencyCode));
            })
            ->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    protected function referrerFor(User $referredUser): ?User
    {
        $referral = Referral::where('referred_id', $referredUser->id)->oldest()->first();
        if ($referral?->referrer) {
            return $referral->referrer;
        }

        $referrerId = $referredUser->referral_id ?? null;

        return $referrerId ? User::find($referrerId) : null;
    }

    protected function calculateCommission(string $platformFee, string $rate): string
    {
        return $this->normalize(bcdiv(bcmul($platformFee, $rate, 18), '100', 18));
    }

    protected function normalize(string $amount): string
    {
        return bcadd($amount, '0', 18);
    }

    protected function reference(string $type): string
    {
        return 'shared-referral:' . $type . ':' . Str::uuid();
    }

    protected function flushCache(int $userId): void
    {
        UserScopedCache::flush(['shared-referral-commission', "user:{$userId}"]);
    }
}
