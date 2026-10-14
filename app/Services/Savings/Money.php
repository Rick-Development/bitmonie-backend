<?php

namespace App\Services\Savings;

use InvalidArgumentException;

/** Decimal-safe money helpers. Amounts are always 2dp strings (NGN). */
final class Money
{
    /**
     * Normalise user input to a positive 2dp decimal string.
     *
     * @throws SavingsException when not a valid positive amount with <= 2 decimals
     */
    public static function parse(mixed $value, string $field = 'amount'): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        } elseif (is_float($value)) {
            if (! is_finite($value) || abs($value - round($value, 2)) > 1e-9) {
                throw new SavingsException("The {$field} must have at most 2 decimal places.", 'INVALID_AMOUNT');
            }
            $value = number_format($value, 2, '.', '');
        }

        if (! is_string($value) || ! preg_match('/^\d{1,16}(\.\d{1,2})?$/', trim($value))) {
            throw new SavingsException("The {$field} must be a valid amount with at most 2 decimal places.", 'INVALID_AMOUNT');
        }

        $amount = bcadd(trim($value), '0', 2);

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new SavingsException("The {$field} must be greater than zero.", 'INVALID_AMOUNT');
        }

        return $amount;
    }

    /** Normalise a stored/computed value to 2dp (truncating). */
    public static function norm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_float($value)) {
            $value = number_format($value, 8, '.', '');
        }
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Non-numeric monetary value.');
        }

        return bcadd((string) $value, '0', 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, 2);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, 2);
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? $a : $b;
    }

    /** Percentage of an amount, rounded DOWN to 2dp (never over-charge). */
    public static function percent(string $amount, string $percent): string
    {
        return bcdiv(bcmul($amount, $percent, 8), '100', 2);
    }

    /** Enforce configured per-transaction limits. */
    public static function assertWithinLimits(string $amount): void
    {
        $min = (string) config('savings.limits.min_transaction');
        $max = (string) config('savings.limits.max_transaction');

        if (self::cmp($amount, $min) < 0) {
            throw new SavingsException("The minimum amount is ₦{$min}.", 'BELOW_MINIMUM');
        }
        if (self::cmp($amount, $max) > 0) {
            throw new SavingsException("The maximum amount per transaction is ₦{$max}.", 'ABOVE_MAXIMUM');
        }
    }
}
