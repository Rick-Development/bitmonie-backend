<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTradeRate extends Model
{
    protected $table = 'gift_card_trade_rates';

    protected $fillable = [
        'category_id',
        'type_id',
        'country_id',
        'min_amount',
        'max_amount',
        'rate_per_dollar',
        'currency',
        'status',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'rate_per_dollar' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GiftCardTradeCategory::class, 'category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(GiftCardTradeType::class, 'type_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(GiftCardTradeCountry::class, 'country_id');
    }
}
