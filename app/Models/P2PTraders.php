<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class P2PTraders extends Model
{
    use HasFactory;

    protected $table = 'p2p_traders';

    protected $fillable = [
        'trader_name',
        'trader_email',
        'supported_currencies',
        'type',
        'amount',
        'price',
        'total',
        'status',
        'is_blocked',
        'reason_blocked',
        'notes',
    ];

    protected $casts = [
        'supported_currencies' => 'array',
        'is_blocked' => 'boolean',
    ];

}