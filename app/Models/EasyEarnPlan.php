<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EasyEarnPlan extends Model
{
    protected $table = 'easyearn_plans';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_MATURED = 'matured';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'user_wallet_id',
        'usdt_amount_deposited',
        'expected_return',
        'return_multiplier',
        'maturity_date',
        'midpoint_notify_at',
        'midpoint_notified_at',
        'matured_at',
        'withdrawn_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'user_wallet_id' => 'integer',
        'usdt_amount_deposited' => 'decimal:18',
        'expected_return' => 'decimal:18',
        'return_multiplier' => 'decimal:8',
        'maturity_date' => 'datetime',
        'midpoint_notify_at' => 'datetime',
        'midpoint_notified_at' => 'datetime',
        'matured_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function transactions()
    {
        return $this->hasMany(EasyEarnTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
