<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardTradeType extends Model
{
    protected $table = 'gift_card_trade_types';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(GiftCardTradeRate::class, 'type_id');
    }
}
