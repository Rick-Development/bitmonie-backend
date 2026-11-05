<?php

namespace App\Services\Savings;

use App\Models\SavingsAccount;

/** Product display names and marketing blurbs for API / statements / ledger. */
final class SavingsCatalog
{
    public static function label(?string $product): string
    {
        return match (strtoupper((string) $product)) {
            SavingsAccount::FLEXI, 'FLEX', 'FLEX_SAVINGS' => 'Flexi Save',
            SavingsAccount::AUTO, 'AUTOSAVE' => 'Auto Save',
            SavingsAccount::TARGET, 'TARGET_SAVINGS' => 'Target Savings',
            'SAFELOCK', 'SAFE_LOCK' => 'SafeLock',
            'EDUSAVE', 'EDU_SAVE' => 'EduSave',
            'USDT_EASYEARN', 'USDT', 'EASYEARN' => 'USDT EasyEarn',
            default => 'Savings',
        };
    }

    public static function description(?string $product): string
    {
        return match (strtoupper((string) $product)) {
            SavingsAccount::FLEXI, 'FLEX', 'FLEX_SAVINGS' =>
                'Flexible savings with instant deposits, free withdrawals to your wallet, and daily interest.',
            SavingsAccount::AUTO, 'AUTOSAVE' =>
                'Automated recurring savings with daily, weekly, or monthly contributions from your wallet.',
            SavingsAccount::TARGET, 'TARGET_SAVINGS' =>
                'Goal-based savings with a target amount, deadline, progress tracking, and lock until completion.',
            'SAFELOCK', 'SAFE_LOCK' =>
                'Fixed-term locked vault. Funds stay locked until maturity for a higher interest rate.',
            'EDUSAVE', 'EDU_SAVE' =>
                'Education savings with termly or yearly payouts toward school goals.',
            'USDT_EASYEARN', 'USDT', 'EASYEARN' =>
                'Lock USDT for a fixed duration and earn interest in USDT.',
            default => 'Bitmonie savings product.',
        };
    }

    /** Human label for wallet statement rows (deposit / withdrawal / interest). */
    public static function statementType(?string $product, ?string $kind = null): string
    {
        $name = self::label($product);
        $kind = strtolower((string) $kind);

        return match (true) {
            str_contains($kind, 'interest') => "{$name} Interest",
            str_contains($kind, 'withdraw') => "{$name} Withdrawal",
            str_contains($kind, 'deposit'), str_contains($kind, 'topup'), str_contains($kind, 'contribute') => "{$name} Deposit",
            str_contains($kind, 'transfer') => "{$name} Transfer",
            str_contains($kind, 'fee') => "{$name} Fee",
            default => $name,
        };
    }

    public static function narration(?string $product, string $action, ?string $title = null): string
    {
        $name = self::label($product);
        $suffix = $title ? ": {$title}" : '';

        return "{$name} {$action}{$suffix}";
    }
}
