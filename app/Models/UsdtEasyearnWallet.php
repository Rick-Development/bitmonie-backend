<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsdtEasyearnWallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
        'status' => 'boolean',
    ];

    /**
     * Get the user that owns the wallet
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get available balance (not locked in savings)
     */
    public function getAvailableBalanceAttribute()
    {
        return bcsub($this->balance, $this->locked_balance, 8);
    }

    /**
     * Lock amount for savings
     */
    public function lockAmount($amount)
    {
        $this->increment('locked_balance', $amount);
    }

    /**
     * Unlock amount from savings
     */
    public function unlockAmount($amount)
    {
        $this->decrement('locked_balance', $amount);
    }

    /**
     * Add funds to wallet
     */
    public function credit($amount)
    {
        $this->increment('balance', $amount);
    }

    /**
     * Remove funds from wallet
     */
    public function debit($amount)
    {
        $this->decrement('balance', $amount);
    }
}
