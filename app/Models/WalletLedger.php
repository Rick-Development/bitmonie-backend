<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';

    protected $fillable = [
        'user_id',
        'referral_id',
        'amount',
        'currency_code',
        'transaction_type',
        'status',
        'balance_before',
        'balance_after',
        'pending_before',
        'pending_after',
        'reference',
        'description',
        'metadata',
        'transaction_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'pending_before' => 'decimal:8',
        'pending_after' => 'decimal:8',
        'metadata' => 'array',
        'transaction_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function withdrawalRequest(): HasOne
    {
        return $this->hasOne(WalletWithdrawalRequest::class, 'wallet_ledger_id');
    }
}
