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
        'lock_date',
        'maturity_date',
        'is_redeemed',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'lock_date' => 'datetime',
        'maturity_date' => 'datetime',
        'is_redeemed' => 'boolean',
    ];

    protected $appends = [
        'current_balance',
        'is_active',
        'expected_interest',
        'total_payout',
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

        return number_format((float) ($this->amount ?? 0), 2, '.', '');
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
}
