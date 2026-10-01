<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockedFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_wallet_id',
        'user_id',
        'amount',
        'reason',
        // 'pin',
        'status',
        'locked_until',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'locked_until' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}