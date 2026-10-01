<?php

namespace App\Services;

use App\Models\EasyEarnPlan;
use App\Models\EasyEarnSetting;
use App\Models\EasyEarnTransaction;
use App\Models\OrderTransaction;
use App\Models\User;
use App\Models\UserWallet;
use App\Notifications\EasyEarnNotification;
use App\Support\UserScopedCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EasyEarnService
{
    public function __construct(protected QuidaxService $quidaxService)
    {
    }

    public function preview(string $amount, ?int $termDays = null): array
    {
        $settings = EasyEarnSetting::current();
        $termDays = $termDays ?: $settings->term_days;
        $expectedReturn = $this->calculateReturn($amount, (string) $settings->return_multiplier);
        $maturityDate = now()->addDays($termDays);

        return [
            'amount_deposited' => $this->normalizeAmount($amount),
            'return_multiplier' => (string) $settings->return_multiplier,
            'expected_return' => $expectedReturn,
            'maturity_date' => $maturityDate->toDateTimeString(),
            'term_days' => $termDays,
            'early_withdrawal_enabled' => (bool) $settings->early_withdrawal_enabled,
            'early_withdrawal_penalty_percent' => (string) $settings->early_withdrawal_penalty_percent,
            'terms' => $settings->terms,
        ];
    }

    public function createPlan(User $user, string $amount, ?int $termDays = null): EasyEarnPlan
    {
        $amount = $this->normalizeAmount($amount);
        $settings = EasyEarnSetting::current();
        $termDays = $termDays ?: $settings->term_days;
        $expectedReturn = $this->calculateReturn($amount, (string) $settings->return_multiplier);
        $maturityDate = now()->addDays($termDays);
        $midpoint = now()->addSeconds((int) floor(now()->diffInSeconds($maturityDate) / 2));

        return DB::transaction(function () use ($user, $amount, $settings, $termDays, $expectedReturn, $maturityDate, $midpoint) {
            if (!$user->quidax_id) {
                throw new RuntimeException('USDT wallet not found. Please complete your crypto wallet setup first.');
            }

            $providerWallet = $this->fetchProviderUsdtWallet($user);
            $providerBalance = (string) data_get($providerWallet, 'data.balance', '0');

            if (bccomp($providerBalance, $amount, 18) < 0) {
                throw new RuntimeException('Insufficient USDT wallet balance.');
            }

            $reference = $this->reference('deposit');
            try {
                $lockResponse = $this->quidaxService->transferToEscrow(
                    $user->quidax_id,
                    $amount,
                    'usdt',
                    'EasyEarn USDT lock: ' . $reference
                );
            } catch (\Throwable $exception) {
                throw new RuntimeException('Unable to lock USDT for EasyEarn: ' . $exception->getMessage());
            }

            if (!isset($lockResponse['data'])) {
                throw new RuntimeException(data_get($lockResponse, 'message', 'Unable to lock USDT for EasyEarn. Please try again.'));
            }

            $wallet = $this->localUsdtWallet($user->id, true);
            $balanceAfter = null;

            if ($wallet) {
                $wallet->balance = bcsub((string) $wallet->balance, $amount, 18);
                $wallet->save();
                $balanceAfter = (string) $wallet->balance;
            }

            $plan = EasyEarnPlan::create([
                'user_id' => $user->id,
                'user_wallet_id' => $wallet?->id,
                'usdt_amount_deposited' => $amount,
                'expected_return' => $expectedReturn,
                'return_multiplier' => $settings->return_multiplier,
                'maturity_date' => $maturityDate,
                'midpoint_notify_at' => $midpoint,
                'status' => EasyEarnPlan::STATUS_ACTIVE,
                'metadata' => [
                    'term_days' => $termDays,
                    'terms' => $settings->terms,
                    'early_withdrawal_enabled' => (bool) $settings->early_withdrawal_enabled,
                    'early_withdrawal_penalty_percent' => (string) $settings->early_withdrawal_penalty_percent,
                    'wallet_source' => 'quidax',
                    'provider_wallet_balance_before' => $providerBalance,
                    'provider_lock_response' => $lockResponse['data'],
                ],
            ]);

            if ($wallet) {
                OrderTransaction::create([
                    'user_wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'reference' => $reference,
                    'metadata' => ['source' => 'easyearn', 'easyearn_plan_id' => $plan->id],
                ]);
            }

            $this->log($plan, EasyEarnTransaction::TYPE_DEPOSIT, 'successful', $amount, $reference, $balanceAfter, [
                'wallet_source' => 'quidax',
                'provider_withdrawal_id' => data_get($lockResponse, 'data.id'),
                'provider_status' => data_get($lockResponse, 'data.status'),
            ]);
            $plan->user?->notify(new EasyEarnNotification($plan, 'created'));
            $this->flushCache($plan);

            return $plan->refresh();
        });
    }

    public function withdraw(EasyEarnPlan $plan, bool $manual = false): EasyEarnPlan
    {
        return DB::transaction(function () use ($plan, $manual) {
            $plan = EasyEarnPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if (!in_array($plan->status, [EasyEarnPlan::STATUS_ACTIVE, EasyEarnPlan::STATUS_MATURED], true)) {
                throw new RuntimeException('This EasyEarn plan cannot be withdrawn.');
            }

            if ($plan->maturity_date->isFuture()) {
                $settings = EasyEarnSetting::current();
                if (!$settings->early_withdrawal_enabled) {
                    throw new RuntimeException('Early withdrawal is locked until maturity.');
                }

                $refund = $this->earlyWithdrawalAmount($plan, $settings);
                return $this->creditAndClose($plan, $refund, EasyEarnPlan::STATUS_CANCELLED, EasyEarnTransaction::TYPE_WITHDRAWAL, 'early_withdrawal', $manual);
            }

            return $this->creditAndClose($plan, (string) $plan->expected_return, EasyEarnPlan::STATUS_WITHDRAWN, EasyEarnTransaction::TYPE_MATURITY_PAYOUT, 'maturity_payout', $manual);
        });
    }

    public function markMatured(EasyEarnPlan $plan): EasyEarnPlan
    {
        if ($plan->status !== EasyEarnPlan::STATUS_ACTIVE || $plan->maturity_date->isFuture()) {
            return $plan;
        }

        $plan->update([
            'status' => EasyEarnPlan::STATUS_MATURED,
            'matured_at' => now(),
        ]);

        $this->log($plan, EasyEarnTransaction::TYPE_NOTIFICATION, 'successful', '0', $this->reference('matured'), null, ['event' => 'matured']);
        $plan->user?->notify(new EasyEarnNotification($plan->refresh(), 'matured'));
        $this->flushCache($plan);

        return $plan->refresh();
    }

    public function cancel(EasyEarnPlan $plan): EasyEarnPlan
    {
        return DB::transaction(function () use ($plan) {
            $plan = EasyEarnPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if (!in_array($plan->status, [EasyEarnPlan::STATUS_ACTIVE, EasyEarnPlan::STATUS_MATURED], true)) {
                throw new RuntimeException('This EasyEarn plan cannot be cancelled.');
            }

            $plan->update([
                'status' => EasyEarnPlan::STATUS_CANCELLED,
                'withdrawn_at' => now(),
            ]);

            $this->log($plan, EasyEarnTransaction::TYPE_CANCEL, 'successful', '0', $this->reference('cancel'));
            $plan->user?->notify(new EasyEarnNotification($plan->refresh(), 'cancelled'));
            $this->flushCache($plan);

            return $plan->refresh();
        });
    }

    public function sendMidpointReminder(EasyEarnPlan $plan): void
    {
        if ($plan->midpoint_notified_at || $plan->status !== EasyEarnPlan::STATUS_ACTIVE) {
            return;
        }

        $plan->update(['midpoint_notified_at' => now()]);
        $this->log($plan, EasyEarnTransaction::TYPE_NOTIFICATION, 'successful', '0', $this->reference('midpoint'), null, ['event' => 'midpoint']);
        $plan->user?->notify(new EasyEarnNotification($plan->refresh(), 'midpoint'));
        $this->flushCache($plan);
    }

    public function calculateReturn(string $amount, string $multiplier): string
    {
        return $this->normalizeAmount(bcmul($amount, $multiplier, 18));
    }

    protected function creditAndClose(EasyEarnPlan $plan, string $amount, string $status, string $type, string $referencePrefix, bool $manual): EasyEarnPlan
    {
        $reference = $this->reference($referencePrefix);
        $wallet = $plan->user_wallet_id ? UserWallet::whereKey($plan->user_wallet_id)->lockForUpdate()->first() : null;
        $balanceAfter = null;
        $metadata = ['manual' => $manual];

        if ($wallet) {
            $wallet->balance = bcadd((string) $wallet->balance, $amount, 18);
            $wallet->save();
            $balanceAfter = (string) $wallet->balance;

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'metadata' => ['source' => 'easyearn', 'easyearn_plan_id' => $plan->id, 'manual' => $manual],
            ]);
        } else {
            $user = $plan->user ?: User::find($plan->user_id);

            if (!$user?->quidax_id) {
                throw new RuntimeException('USDT wallet not found. Please complete your crypto wallet setup first.');
            }

            try {
                $fundResponse = $this->quidaxService->fundSubAccount(
                    $user->quidax_id,
                    $amount,
                    'usdt',
                    $reference,
                    'EasyEarn USDT payout: ' . $reference
                );
            } catch (\Throwable $exception) {
                throw new RuntimeException('Unable to credit EasyEarn payout: ' . $exception->getMessage());
            }

            if (!isset($fundResponse['data'])) {
                throw new RuntimeException(data_get($fundResponse, 'message', 'Unable to credit EasyEarn payout. Please try again.'));
            }

            $metadata['wallet_source'] = 'quidax';
            $metadata['provider_fund_response'] = $fundResponse['data'];
        }

        $plan->update([
            'status' => $status,
            'matured_at' => $plan->matured_at ?: now(),
            'withdrawn_at' => now(),
        ]);

        $this->log($plan, $type, 'successful', $amount, $reference, $balanceAfter, $metadata);
        $plan->user?->notify(new EasyEarnNotification($plan->refresh(), $status === EasyEarnPlan::STATUS_WITHDRAWN ? 'withdrawn' : 'cancelled'));
        $this->flushCache($plan);

        return $plan->refresh();
    }

    protected function earlyWithdrawalAmount(EasyEarnPlan $plan, EasyEarnSetting $settings): string
    {
        $penalty = bcdiv((string) $settings->early_withdrawal_penalty_percent, '100', 18);
        $penaltyAmount = bcmul((string) $plan->usdt_amount_deposited, $penalty, 18);

        return $this->normalizeAmount(bcsub((string) $plan->usdt_amount_deposited, $penaltyAmount, 18));
    }

    protected function localUsdtWallet(int $userId, bool $lock = false): ?UserWallet
    {
        $query = UserWallet::where('user_id', $userId)
            ->where(function ($walletQuery) {
                $walletQuery->where('currency_code', 'USDT')
                    ->orWhere('currency_code', 'usdt')
                    ->orWhere('quote_currency_code', 'USDT')
                    ->orWhere('quote_currency_code', 'usdt')
                    ->orWhereHas('currency', fn ($currency) => $currency->where('code', 'USDT')->orWhere('code', 'usdt'));
            })
            ->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    protected function fetchProviderUsdtWallet(User $user): array
    {
        try {
            $response = $this->quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to fetch USDT wallet: ' . $exception->getMessage());
        }

        if (!isset($response['data'])) {
            throw new RuntimeException(data_get($response, 'message', 'Unable to fetch USDT wallet. Please try again.'));
        }

        return $response;
    }

    protected function log(EasyEarnPlan $plan, string $type, string $status, string $amount, string $reference, ?string $walletBalanceAfter = null, array $metadata = []): EasyEarnTransaction
    {
        return EasyEarnTransaction::create([
            'easyearn_plan_id' => $plan->id,
            'user_id' => $plan->user_id,
            'user_wallet_id' => $plan->user_wallet_id,
            'type' => $type,
            'status' => $status,
            'amount' => $this->normalizeAmount($amount),
            'wallet_balance_after' => $walletBalanceAfter,
            'reference' => $reference,
            'metadata' => $metadata,
        ]);
    }

    protected function reference(string $type): string
    {
        return 'easyearn:' . $type . ':' . Str::uuid();
    }

    protected function normalizeAmount(string $amount): string
    {
        return bcadd($amount, '0', 18);
    }

    public function flushCache(EasyEarnPlan $plan): void
    {
        UserScopedCache::flush(['easyearn', "user:{$plan->user_id}"]);
        UserScopedCache::flush(['easyearn', "user:{$plan->user_id}", "easyearn-plan:{$plan->id}"]);
    }
public function terminate(EasyEarnPlan $plan, bool $manual = true): EasyEarnPlan
{
    return DB::transaction(function () use ($plan, $manual) {

        $plan = EasyEarnPlan::whereKey($plan->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($plan->status !== EasyEarnPlan::STATUS_ACTIVE) {
            throw new RuntimeException('Only active plans can be terminated.');
        }

        $settings = EasyEarnSetting::current();

        if (!$settings->early_withdrawal_enabled) {
            throw new RuntimeException(
                'Early termination is currently disabled.'
            );
        }

        $refund = $this->earlyWithdrawalAmount(
            $plan,
            $settings
        );

        /*
         * Release funds from escrow
         */

        try {

            $response = $this->quidaxService->releaseFromEscrow(

                $plan->user->quidax_id,

                $refund,

                'usdt',

                'EasyEarn termination: '.$plan->id

            );

        } catch (\Throwable $e) {

            throw new RuntimeException(
                'Unable to release escrow: '.$e->getMessage()
            );

        }

        if (!isset($response['data'])) {

            throw new RuntimeException(

                data_get(
                    $response,
                    'message',
                    'Unable to release escrow.'
                )

            );

        }

        /*
         * Credit wallet
         */

        $reference = $this->reference('termination');

    $wallet = $plan->user_wallet_id
    ? UserWallet::whereKey($plan->user_wallet_id)
        ->lockForUpdate()
        ->first()
    : $this->localUsdtWallet($plan->user_id, true);
        $balanceAfter = null;

        if ($wallet) {

            $wallet->balance = bcadd(
                (string) $wallet->balance,
                $refund,
                18
            );

            $wallet->save();

            $balanceAfter = (string) $wallet->balance;

            OrderTransaction::create([

                'user_wallet_id' => $wallet->id,

                'type' => 'credit',

                'amount' => $refund,

                'balance_after' => $wallet->balance,

                'reference' => $reference,

                'metadata' => [

                    'source' => 'easyearn',

                    'action' => 'termination',

                    'easyearn_plan_id' => $plan->id,

                    'penalty_percent' => $settings->early_withdrawal_penalty_percent,

                    'manual' => $manual,

                ],

            ]);

        }

        /*
         * Update plan
         */

        $plan->update([

            'status' => EasyEarnPlan::STATUS_CANCELLED,

            'withdrawn_at' => now(),

            'metadata' => array_merge(

                $plan->metadata ?? [],

                [

                    'terminated' => true,

                    'terminated_at' => now()->toDateTimeString(),

                    'refund_amount' => $refund,

                    'penalty_percent' => (string) $settings->early_withdrawal_penalty_percent,

                    'provider_release_response' => $response['data'],

                ]

            ),

        ]);

        /*
         * Audit
         */

        $this->log(

            $plan,

            EasyEarnTransaction::TYPE_CANCEL,

            'successful',

            $refund,

            $reference,

            $balanceAfter,

            [

                'manual' => $manual,

                'provider_release_id' => data_get($response,'data.id'),

                'provider_status' => data_get($response,'data.status'),

            ]

        );

        $plan->user?->notify(
            new EasyEarnNotification(
                $plan->refresh(),
                'cancelled'
            )
        );

        $this->flushCache($plan);

        return $plan->refresh();

    });
}
}
