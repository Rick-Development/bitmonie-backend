<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'reloadly_transaction_id',
        'custom_identifier',
        'status',
        'amount',
        'currency',
        'fee',
        'discount',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'product_currency',
        'recipient_email',
        'recipient_phone',
        'card_number',
        'pin_code',
        'redemption_url',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'discount' => 'decimal:8',
        'unit_price' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'wallet_id');
    }
}
