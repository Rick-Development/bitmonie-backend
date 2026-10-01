<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\MerchantApplication;
use App\Models\P2PAd;
use App\Models\P2POrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MerchantSecurityDepositService
{
    public function __construct(protected QuidaxService $quidaxService)
    {
    }

    public function checkEligibility(User $user): array
    {
        $settings = $this->getSettings();
        $usdtBalance = $this->fetchUsdtBalance($user);
        $requiredAvailableUsdt = max($settings['merchant_min_usdt'], $settings['merchant_security_deposit_usdt']);
        $activeApplication = $this->findActiveApplication($user);

        return [
            'eligible' => $usdtBalance >= $requiredAvailableUsdt,
            'can_apply' => $usdtBalance >= $requiredAvailableUsdt && $activeApplication === null,
            'quidax_usdt_balance' => $usdtBalance,
            'min_usdt_required' => $settings['merchant_min_usdt'],
            'security_deposit_usdt' => $settings['merchant_security_deposit_usdt'],
            'required_available_usdt' => $requiredAvailableUsdt,
            'shortfall' => max(0, $requiredAvailableUsdt - $usdtBalance),
            'has_active_application' => $activeApplication !== null,
            'active_application_id' => $activeApplication?->id,
            'active_application_status' => $activeApplication ? $this->getDisplayStatus($activeApplication) : null,
        ];
    }

    public function apply(User $user, array $attributes = []): MerchantApplication
    {
        $this->assertUserHasQuidaxAccount($user);

        if ($activeApplication = $this->findActiveApplication($user)) {
            $status = $this->getDisplayStatus($activeApplication);
            $message = $status === 'approved'
                ? 'You are already an approved merchant.'
                : 'You already have an active merchant application.';

            throw new RuntimeException($message, 409);
        }

        $settings = $this->getSettings();
        $requiredAvailableUsdt = max($settings['merchant_min_usdt'], $settings['merchant_security_deposit_usdt']);
        $usdtBalance = $this->fetchUsdtBalance($user);

        if ($usdtBalance < $requiredAvailableUsdt) {
            throw new RuntimeException(
                "Insufficient USDT balance. You need at least {$requiredAvailableUsdt} USDT available to apply.",
                400
            );
        }

        $depositAmount = $settings['merchant_security_deposit_usdt'];
        $lockResponse = null;

        try {
            if ($depositAmount > 0) {
                $lockResponse = $this->ensureSuccessfulProviderResponse(
                    $this->quidaxService->transferToEscrow(
                        $user->quidax_id,
                        $depositAmount,
                        'usdt',
                        'P2P Merchant Security Deposit'
                    ),
                    'Failed to lock the merchant security deposit. Please try again.'
                );
            }

            $application = DB::transaction(function () use ($user, $attributes, $usdtBalance, $settings, $depositAmount, $lockResponse) {
                $userPhone = $user->full_mobile ?? $user->mobile;

                $application = MerchantApplication::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'phone' => $userPhone,
                    'whatsapp' => $attributes['whatsapp'] ?? $userPhone,
                    'business_name' => $attributes['business_name'] ?? null,
                    'quidax_usdt_balance' => $usdtBalance,
                    'min_usdt_required' => $settings['merchant_min_usdt'],
                    'security_deposit_amount' => $depositAmount,
                    'security_deposit_currency' => 'usdt',
                    'security_deposit_status' => $depositAmount > 0 ? 'locked' : 'not_locked',
                    'security_deposit_lock_reference' => $this->responseReference($lockResponse),
                    'security_deposit_locked_at' => $depositAmount > 0 ? now() : null,
                    'balance_verified_at' => now(),
                    'status' => 'pending',
                ]);

                $user->merchant_status = 'pending';
                $user->save();

                return $application;
            });

            return $application;
        } catch (Throwable $e) {
            if ($lockResponse !== null && $depositAmount > 0) {
                $this->attemptCompensatingRefund($user, $depositAmount, 'merchant_apply_failed');
            }

            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('Failed to submit merchant application. Please try again.', 500, $e);
        }
    }

    public function approve(MerchantApplication $application, ?int $reviewedBy = null, ?string $adminNotes = null): MerchantApplication
    {
        if ($application->status !== 'pending') {
            throw new RuntimeException('Application is not pending.', 409);
        }

        $configuredDeposit = $this->getSettings()['merchant_security_deposit_usdt'];
        $requiresLockedDeposit = (float) $application->security_deposit_amount > 0 || $configuredDeposit > 0;

        if ($requiresLockedDeposit && $application->security_deposit_status !== 'locked') {
            throw new RuntimeException(
                'Security deposit is not locked for this application. Ask the user to reapply under the current merchant flow.',
                409
            );
        }

        return DB::transaction(function () use ($application, $reviewedBy, $adminNotes) {
            $user = User::query()->lockForUpdate()->findOrFail($application->user_id);

            $application->update([
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
                'merchant_deactivated_at' => null,
                'merchant_deactivation_reason' => null,
            ]);

            $user->update([
                'merchant_status' => 'approved',
                'merchant_approved_at' => now(),
                'kyc_tier' => max((int) ($user->kyc_tier ?? 0), 3),
            ]);

            return $application->fresh();
        });
    }

    public function reject(MerchantApplication $application, string $reason, ?int $reviewedBy = null): MerchantApplication
    {
        if ($application->status !== 'pending') {
            throw new RuntimeException('Application is not pending.', 409);
        }

        $user = User::findOrFail($application->user_id);
        $releaseResponse = null;

        if ($application->security_deposit_status === 'locked' && (float) $application->security_deposit_amount > 0) {
            $this->assertUserHasQuidaxAccount($user);
            $releaseResponse = $this->ensureSuccessfulProviderResponse(
                $this->quidaxService->fundSubAccount($user->quidax_id, $application->security_deposit_amount, 'usdt'),
                'Failed to release the merchant security deposit back to the user.'
            );
        }

        return DB::transaction(function () use ($application, $user, $reason, $reviewedBy, $releaseResponse) {
            $application->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'admin_notes' => $reason,
                'security_deposit_status' => $application->security_deposit_status === 'locked' ? 'released' : $application->security_deposit_status,
                'security_deposit_release_reference' => $this->responseReference($releaseResponse),
                'security_deposit_released_at' => $application->security_deposit_status === 'locked' ? now() : $application->security_deposit_released_at,
                'security_deposit_release_reason' => $application->security_deposit_status === 'locked' ? 'application_rejected' : $application->security_deposit_release_reason,
            ]);

            $user->update([
                'merchant_status' => 'rejected',
            ]);

            return $application->fresh();
        });
    }

    public function deactivate(User $user, ?string $reason = null, ?int $adminId = null): array
    {
        if ($user->merchant_status !== 'approved') {
            throw new RuntimeException('This account is not currently an active merchant.', 409);
        }

        $application = MerchantApplication::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('merchant_deactivated_at')
            ->latest('reviewed_at')
            ->latest('id')
            ->first();

        if (!$application) {
            throw new RuntimeException('No active merchant approval record was found for this user.', 404);
        }

        $blockers = $this->getDeactivationBlockers($user);
        if (($blockers['active_orders_count'] ?? 0) > 0 || ($blockers['pending_appeals_count'] ?? 0) > 0) {
            throw new RuntimeException(
                'Merchant status cannot be deactivated while there are active P2P orders or disputes.',
                409
            );
        }

        $releaseResponse = null;
        if ($application->security_deposit_status === 'locked' && (float) $application->security_deposit_amount > 0) {
            $this->assertUserHasQuidaxAccount($user);
            $releaseResponse = $this->ensureSuccessfulProviderResponse(
                $this->quidaxService->fundSubAccount($user->quidax_id, $application->security_deposit_amount, 'usdt'),
                'Failed to release the merchant security deposit back to the user.'
            );
        }

        $offlineAdsCount = DB::transaction(function () use ($user, $application, $reason, $releaseResponse, $adminId) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $offlineAdsCount = P2PAd::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'online')
                ->update(['status' => 'offline']);

            $application->update([
                'merchant_deactivated_at' => now(),
                'merchant_deactivation_reason' => $reason,
                'security_deposit_status' => $application->security_deposit_status === 'locked' ? 'released' : $application->security_deposit_status,
                'security_deposit_release_reference' => $this->responseReference($releaseResponse),
                'security_deposit_released_at' => $application->security_deposit_status === 'locked' ? now() : $application->security_deposit_released_at,
                'security_deposit_release_reason' => $application->security_deposit_status === 'locked' ? 'merchant_deactivated' : $application->security_deposit_release_reason,
                'reviewed_by' => $adminId ?? $application->reviewed_by,
            ]);

            $lockedUser->update([
                'merchant_status' => 'none',
                'merchant_approved_at' => null,
                'kyc_tier' => min((int) ($lockedUser->kyc_tier ?? 0), 2),
            ]);

            return $offlineAdsCount;
        });

        return [
            'application' => $application->fresh(),
            'offline_ads_count' => $offlineAdsCount,
            'deposit_release_reference' => $this->responseReference($releaseResponse),
            'blockers' => $blockers,
        ];
    }

    public function getDisplayStatus(?MerchantApplication $application): string
    {
        if ($application === null) {
            return 'none';
        }

        if ($application->status === 'approved' && $application->merchant_deactivated_at !== null) {
            return 'deactivated';
        }

        return (string) $application->status;
    }

    public function getDeactivationBlockers(User $user): array
    {
        $baseOrders = P2POrder::query()
            ->where(function ($query) use ($user) {
                $query->where('maker_id', $user->id)
                    ->orWhere('taker_id', $user->id);
            });

        $activeOrdersCount = (clone $baseOrders)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $pendingAppealsCount = (clone $baseOrders)
            ->where('appeal_status', 'pending')
            ->count();

        return [
            'online_ads_count' => P2PAd::query()
                ->where('user_id', $user->id)
                ->where('status', 'online')
                ->count(),
            'active_orders_count' => $activeOrdersCount,
            'pending_appeals_count' => $pendingAppealsCount,
        ];
    }

    public function findActiveApplication(User $user): ?MerchantApplication
    {
        return MerchantApplication::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->where('status', 'pending')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('status', 'approved')
                            ->whereNull('merchant_deactivated_at');
                    });
            })
            ->latest('id')
            ->first();
    }

    public function getSettings(): array
    {
        return [
            'merchant_min_usdt' => $this->getSettingDecimal('merchant_min_usdt', 100),
            'merchant_security_deposit_usdt' => $this->getSettingDecimal('merchant_security_deposit_usdt', 200),
        ];
    }

    public function fetchUsdtBalance(User $user): float
    {
        $this->assertUserHasQuidaxAccount($user);

        $response = $this->quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        $wallet = $response['data'] ?? null;

        if (!is_array($wallet) || !array_key_exists('balance', $wallet)) {
            throw new RuntimeException('Failed to fetch Quidax balance. Please try again.', 502);
        }

        return round((float) $wallet['balance'], 8);
    }

    protected function getSettingDecimal(string $key, float $default): float
    {
        $value = AdminSetting::query()
            ->where('setting_key', $key)
            ->value('setting_value');

        return is_numeric($value) ? round((float) $value, 8) : $default;
    }

    protected function assertUserHasQuidaxAccount(User $user): void
    {
        if (!$user->quidax_id) {
            throw new RuntimeException('Quidax account not found. Please complete your wallet setup first.', 400);
        }
    }

    protected function ensureSuccessfulProviderResponse(?array $response, string $fallbackMessage): array
    {
        $status = strtolower((string) ($response['status'] ?? 'success'));
        $hasData = isset($response['data']) && is_array($response['data']);

        if ($hasData && !in_array($status, ['error', 'failed'], true)) {
            return $response;
        }

        throw new RuntimeException($response['message'] ?? $fallbackMessage, 502);
    }

    protected function responseReference(?array $response): ?string
    {
        if ($response === null) {
            return null;
        }

        return $response['data']['id']
            ?? $response['data']['reference']
            ?? $response['data']['transaction_id']
            ?? null;
    }

    protected function attemptCompensatingRefund(User $user, float $amount, string $reason): void
    {
        try {
            $this->quidaxService->fundSubAccount($user->quidax_id, $amount, 'usdt');
        } catch (Throwable $e) {
            Log::error('Failed to compensate merchant security deposit after application error.', [
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
