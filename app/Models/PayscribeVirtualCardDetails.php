<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PayscribeVirtualCardDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_id',
        'card_name',
        'card_number',
        'card_type',
        'currency',
        'brand',
        'masked',
        'expiry_date',
        'ccv',
        'billing_address',
        'trans_id',
        'ref',
        'balance',
        'prev_balance',
        'card_status',
        'is_terminated',
        'termination_date',
    ];

    protected $casts = [
        'billing_address' => 'array',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'id');
    // }
}
