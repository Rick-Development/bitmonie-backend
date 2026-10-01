<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionWalletTransaction extends Model
{
    protected $fillable = [
        'commission_wallet_id',
        'user_id',
        'referral_commission_id',
        'type',
        'status',
        'currency_code',
        'amount',
        'balance_after',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'commission_wallet_id' => 'integer',
        'user_id' => 'integer',
        'referral_commission_id' => 'integer',
        'amount' => 'decimal:18',
        'balance_after' => 'decimal:18',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(CommissionWallet::class, 'commission_wallet_id');
    }

    public function commission()
    {
        return $this->belongsTo(ReferralCommission::class, 'referral_commission_id');
    }
}
