<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class P2PEscrow extends Model
{
    protected $table = 'p2p_escrows';

    protected $fillable = [
        'user_id',
        'ad_id',
        'order_id',
        'type',
        'asset',
        'amount',
        'currency_type',
        'refunded_at',
        'notes',
        'fee',
        'status',
        'transaction_ref',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'refunded_at' => 'datetime',
    ];
}