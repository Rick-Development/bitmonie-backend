<?php

namespace App\Services;

use App\Models\LockedFund;
use App\Models\UserWallet;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LockedFundsService
{
    public function lock(UserWallet $wallet, float $amount, ?string $reason = null, ?string $lockedUntil = null): LockedFund
    {
        return DB::transaction(function () use ($wallet, $amount, $reason, $lockedUntil) {
            $wallet = UserWallet::lockForUpdate()->find($wallet->id);

            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient available balance to lock.');
            }

            $wallet->balance -= $amount;
            $wallet->save();

            return LockedFund::create([
                'user_wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'amount' => $amount,
                'reason' => $reason,
                'locked_until' => $lockedUntil ? Carbon::parse($lockedUntil) : null,
            ]);
        });
    }

    public function release(LockedFund $lockedFund): LockedFund
    {
        return DB::transaction(function () use ($lockedFund) {
            if ($lockedFund->status !== 'locked') {
                return $lockedFund;
            }

            $wallet = UserWallet::lockForUpdate()->find($lockedFund->wallet_id);

            $wallet->available_balance += $lockedFund->amount;
            $wallet->save();

            $lockedFund->update(['status' => 'released']);

            return $lockedFund;
        });
    }

    public function finalize(LockedFund $lockedFund): LockedFund
    {
        return DB::transaction(function () use ($lockedFund) {
            if ($lockedFund->status !== 'locked') {
                throw new \Exception('Only locked funds can be finalized.');
            }

            $wallet = UserWallet::lockForUpdate()->find($lockedFund->wallet_id);
            $wallet->total_balance -= $lockedFund->amount;
            $wallet->save();

            $lockedFund->update(['status' => 'deducted']);

            return $lockedFund;
        });
    }

    public function releaseExpired(): void
    {
        $expired = LockedFund::where('status', 'locked')
            ->whereNotNull('locked_until')
            ->where('locked_until', '<', now())
            ->get();

        foreach ($expired as $lock) {
            $this->release($lock);
            $lock->update(['status' => 'expired']);
        }
    }
}
