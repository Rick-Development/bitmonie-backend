<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardTradeImage extends Model
{
    protected $table = 'gift_card_trade_images';

    protected $fillable = [
        'trade_id',
        'image_path',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(GiftCardTradeSubmission::class, 'trade_id');
    }
}
