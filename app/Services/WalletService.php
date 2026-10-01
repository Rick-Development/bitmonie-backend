<?php

namespace App\Services;

use App\Models\OrderTransaction;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * debitToReserve: Move from balance -> reserved (for escrow space).
     * Fully idempotent using reference lookup.
     */
    public static function debitToReserve(int $walletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function() use($walletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference)
                    ->where('user_wallet_id', $walletId)
                    ->first();
                if ($existingTx) {
                    return UserWallet::findOrFail($walletId);
                }
            }

            $wallet = UserWallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            
            if (bccomp($wallet->balance, $amount, 18) < 0) {
                throw new \RuntimeException('Insufficient funds');
            }
            
            $wallet->balance = bcsub($wallet->balance, $amount, 18);
            $wallet->reserved = bcadd($wallet->reserved, $amount, 18);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_after'  => $wallet->balance,
                'reference'      => $reference,
                'metadata'       => $meta,
            ]);
            
            return $wallet;
        });
    }

    /**
     * releaseReservedTo: Move escrow reserved funds from one wallet to another's balance.
     * Fully idempotent using double ledger entry matching.
     */
    public static function releaseReservedTo(int $fromWalletId, int $toWalletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function() use($fromWalletId, $toWalletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference . ':from')
                    ->where('user_wallet_id', $fromWalletId)
                    ->first();
                if ($existingTx) {
                    return true;
                }
            }

            $from = UserWallet::where('id', $fromWalletId)->lockForUpdate()->firstOrFail();
            $to = UserWallet::where('id', $toWalletId)->lockForUpdate()->firstOrFail();

            if (bccomp($from->reserved, $amount, 18) < 0) {
                throw new \RuntimeException('Escrow reserved insufficient');
            }

            $from->reserved = bcsub($from->reserved, $amount, 18);
            $from->save();

            $to->balance = bcadd($to->balance, $amount, 18);
            $to->save();

            OrderTransaction::create([
                'user_wallet_id' => $from->id,
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_after'  => $from->balance,
                'reference'      => $reference ? $reference . ':from' : null,
                'metadata'       => $meta,
            ]);
            
            OrderTransaction::create([
                'user_wallet_id' => $to->id,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_after'  => $to->balance,
                'reference'      => $reference ? $reference . ':to' : null,
                'metadata'       => $meta,
            ]);

            return true;
        });
    }

    /**
     * debit: Standard straight debit from active balance.
     * Fully idempotent using reference lookup.
     */
    public static function debit(int $walletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function() use($walletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference)
                    ->where('user_wallet_id', $walletId)
                    ->first();
                if ($existingTx) {
                    return UserWallet::findOrFail($walletId);
                }
            }

            $wallet = UserWallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            
            if (bccomp($wallet->balance, $amount, 18) < 0) {
                throw new \RuntimeException('Insufficient funds');
            }
            
            $wallet->balance = bcsub($wallet->balance, $amount, 18);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_after'  => $wallet->balance,
                'reference'      => $reference,
                'metadata'       => $meta,
            ]);
            
            return $wallet;
        });
    }

    /**
     * credit: Standard inbound adjustment adding straight to balance.
     * Fully idempotent using reference lookup.
     */
    public static function credit(int $walletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function() use($walletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference)
                    ->where('user_wallet_id', $walletId)
                    ->first();
                if ($existingTx) {
                    return UserWallet::findOrFail($walletId);
                }
            }

            $wallet = UserWallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            
            $wallet->balance = bcadd($wallet->balance, $amount, 18);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_after'  => $wallet->balance,
                'reference'      => $reference,
                'metadata'       => $meta,
            ]);
            
            return $wallet;
        });
    }

    /**
     * releaseReservedToBalance: Return reserved funds back to the original balance (Cancellations).
     * Fully idempotent using reference lookup.
     */
    public static function releaseReservedToBalance(int $walletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function () use ($walletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference)
                    ->where('user_wallet_id', $walletId)
                    ->first();
                if ($existingTx) {
                    return UserWallet::findOrFail($walletId);
                }
            }

            $wallet = UserWallet::where('id', $walletId)->lockForUpdate()->firstOrFail();

            if (bccomp($wallet->reserved, $amount, 18) < 0) {
                throw new \RuntimeException('Reserved funds insufficient');
            }

            $wallet->reserved = bcsub($wallet->reserved, $amount, 18);
            $wallet->balance = bcadd($wallet->balance, $amount, 18);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_after'  => $wallet->balance,
                'reference'      => $reference,
                'metadata'       => $meta,
            ]);

            return $wallet;
        });
    }

    /**
     * commitReservedDebit: Permanently wipe funds out of escrow space (Settled payments).
     * Fully idempotent using reference lookup.
     */
    public static function commitReservedDebit(int $walletId, string $amount, ?string $reference = null, array $meta = [])
    {
        return DB::transaction(function () use ($walletId, $amount, $reference, $meta) {
            if ($reference) {
                $existingTx = OrderTransaction::where('reference', $reference)
                    ->where('user_wallet_id', $walletId)
                    ->first();
                if ($existingTx) {
                    return UserWallet::findOrFail($walletId);
                }
            }

            $wallet = UserWallet::where('id', $walletId)->lockForUpdate()->firstOrFail();

            if (bccomp($wallet->reserved, $amount, 18) < 0) {
                throw new \RuntimeException('Reserved funds insufficient');
            }

            $wallet->reserved = bcsub($wallet->reserved, $amount, 18);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_after'  => $wallet->balance,
                'reference'      => $reference,
                'metadata'       => $meta,
            ]);

            return $wallet;
        });
    }
}