<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YellowCardCoverage extends Model
{
    protected $fillable = [
        'channel_id',
        'country_code',
        'country_name',
        'currency_code',
        'channel_type',
        'ramp_type',
        'payment_method',
        'payment_type',
        'settlement_time',
        'status',
        'min_amount',
        'max_amount',
        'raw',
        'last_synced_at',
    ];

    protected $casts = [
        'raw' => 'array',
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',
        'last_synced_at' => 'datetime',
    ];
}
