<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EasyEarnTransaction extends Model
{
    protected $table = 'easyearn_transactions';

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_MATURITY_PAYOUT = 'maturity_payout';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_CANCEL = 'cancel';
    public const TYPE_NOTIFICATION = 'notification';

    protected $fillable = [
        'easyearn_plan_id',
        'user_id',
        'user_wallet_id',
        'type',
        'status',
        'amount',
        'wallet_balance_after',
        'reference',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'easyearn_plan_id' => 'integer',
        'user_id' => 'integer',
        'user_wallet_id' => 'integer',
        'amount' => 'decimal:18',
        'wallet_balance_after' => 'decimal:18',
        'metadata' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(EasyEarnPlan::class, 'easyearn_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }
}
