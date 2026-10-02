<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlexSavings extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'accrued_interest',
        'auto_save',
        'status',
        'last_interest_date',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'accrued_interest' => 'decimal:2',
        'auto_save' => 'boolean',
        'status' => 'boolean',
        'last_interest_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
