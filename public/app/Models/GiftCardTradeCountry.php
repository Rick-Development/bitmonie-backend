<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardTradeCountry extends Model
{
    protected $table = 'gift_card_trade_countries';

    protected $fillable = [
        'name',
        'code',
        'flag_icon',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(GiftCardTradeRate::class, 'country_id');
    }
}
