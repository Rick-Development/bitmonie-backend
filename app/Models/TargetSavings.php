<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetSavings extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'target_amount',
        'current_balance',
        'interest_accrued',
        'frequency',
        'auto_save_amount',
        'next_save_date',
        'start_date',
        'target_date',
        'status',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'auto_save_amount' => 'decimal:2',
        'next_save_date' => 'datetime',
        'start_date' => 'datetime',
        'target_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
