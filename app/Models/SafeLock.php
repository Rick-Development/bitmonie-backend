<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafeLock extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'amount',
        'interest_rate',
        'interest_accrued',
        'last_interest_date',
        'lock_date',
        'maturity_date',
        'is_redeemed',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'last_interest_date' => 'datetime',
        'lock_date' => 'datetime',
        'maturity_date' => 'datetime',
        'is_redeemed' => 'boolean',
    ];

    protected $appends = [
        'current_balance',
        'is_active',
        'expected_interest',
        'total_payout',
        'interest_earned_today',
        'daily_interest',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCurrentBalanceAttribute(): string
    {
        if ($this->is_redeemed || in_array($this->status, ['completed', 'broken', 'matured'], true)) {
            return '0.00';
        }

        // Principal + accrued interest so daily interest is visible like Flex/USDT earn.
        return number_format(
            (float) bcadd((string) ($this->amount ?? '0'), (string) ($this->interest_accrued ?? '0'), 8),
            2,
            '.',
            ''
        );
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && !$this->is_redeemed;
    }

    public function getExpectedInterestAttribute(): string
    {
        $amount = (string) ($this->amount ?? '0');
        $rate = (string) ($this->interest_rate ?? '0');

        $decimalRate = bcdiv($rate, '100', 8);
        return number_format((float) bcmul($amount, $decimalRate, 8), 2, '.', '');
    }

    public function getTotalPayoutAttribute(): string
    {
        $accrued = (float) ($this->interest_accrued ?? 0);
        $interest = $accrued > 0 ? (string) $this->interest_accrued : $this->expected_interest;

        return number_format((float) bcadd((string) ($this->amount ?? '0'), (string) $interest, 8), 2, '.', '');
    }

    public function getDailyInterestAttribute(): string
    {
        if (! $this->lock_date || ! $this->maturity_date) {
            return '0.00';
        }

        $durationDays = max(
            1,
            $this->lock_date->copy()->startOfDay()->diffInDays($this->maturity_date->copy()->startOfDay())
        );
        $flatRate = bcdiv((string) ($this->interest_rate ?? '0'), '100', 8);
        $dailyRate = bcdiv($flatRate, (string) $durationDays, 8);

        return number_format((float) bcmul((string) ($this->amount ?? '0'), $dailyRate, 8), 2, '.', '');
    }

    public function getInterestEarnedTodayAttribute(): string
    {
        if (! $this->last_interest_date || ! $this->is_active) {
            return '0.00';
        }

        $tz = (string) config('app.timezone', 'Africa/Lagos');
        $last = $this->last_interest_date->copy()->timezone($tz)->toDateString();
        $today = now($tz)->toDateString();

        return $last === $today ? $this->daily_interest : '0.00';
    }
}
