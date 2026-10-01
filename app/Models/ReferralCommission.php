<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCommission extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_FLAGGED = 'flagged';

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'transaction_id',
        'transaction_type',
        'currency_code',
        'platform_fee_amount',
        'commission_rate_percent',
        'commission_amount',
        'status',
        'metadata',
        'credited_at',
        'flagged_at',
    ];

    protected $casts = [
        'referrer_user_id' => 'integer',
        'referred_user_id' => 'integer',
        'platform_fee_amount' => 'decimal:18',
        'commission_rate_percent' => 'decimal:4',
        'commission_amount' => 'decimal:18',
        'metadata' => 'array',
        'credited_at' => 'datetime',
        'flagged_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
