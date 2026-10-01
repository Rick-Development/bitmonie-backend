<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardTradeCategory extends Model
{
    protected $table = 'gift_card_trade_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(GiftCardTradeRate::class, 'category_id');
    }
}
