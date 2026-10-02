<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivedInterest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'interest_id',
        'principal_amount',
        'interest_rate',
        'accrued_interest',
        // 'start_date',
        // 'end_date',
        // 'is_matured',
        // 'is_paid',
    ];
}
