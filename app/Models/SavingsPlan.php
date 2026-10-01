<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'duration_days',
        'interest_rate',
        'min_amount',
        'max_amount',
        'status',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'status' => 'boolean',
    ];
}
