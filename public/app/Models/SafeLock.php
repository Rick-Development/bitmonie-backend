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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
