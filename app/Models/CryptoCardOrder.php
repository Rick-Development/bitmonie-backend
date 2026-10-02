<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoCardOrder extends Model
{
    use HasFactory;

    protected $table = 'crypto_card_orders';

    protected $fillable = [
        'user_id',
        'card_holder_id',
        'card_id',
        'provider',
        'reference',
        'provider_order_id',
        'order_type',
        'brand',
        'currency',
        'allocation',
        'expedite',
        'shipping_method',
        'shipping_address',
        'design',
        'name_on_cards',
        'amount',
        'fee',
        'total_amount',
        'status',
        'metadata',
        'provider_data',
        'ordered_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'allocation' => 'integer',
        'expedite' => 'boolean',
        'shipping_address' => 'array',
        'name_on_cards' => 'array',
        'metadata' => 'array',
        'provider_data' => 'array',
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardHolder(): BelongsTo
    {
        return $this->belongsTo(
            CryptoCardHolder::class,
            'card_holder_id'
        );
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(
            CryptoCardsModel::class,
            'card_id'
        );
    }
}