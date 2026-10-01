<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawals extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'currency',
        'amount',
        'fee',
        'total',
        'trans_id',
        'transaction_note',
        'recipient_data',
        'wallet',
        'user',
    ];

    protected $casts = [
        'recipient_data' => 'array',
        'wallet' => 'array',
        'user' => 'array',
    ];
}
