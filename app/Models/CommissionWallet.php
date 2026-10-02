<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionWallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency_code',
        'available_balance',
        'pending_balance',
        'withdrawn_balance',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'available_balance' => 'decimal:18',
        'pending_balance' => 'decimal:18',
        'withdrawn_balance' => 'decimal:18',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(CommissionWalletTransaction::class);
    }
}
