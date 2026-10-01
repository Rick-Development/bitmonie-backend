<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Admin\Admin;

class GiftCardTradeSubmission extends Model
{
    protected $table = 'gift_card_trade_submissions';

    protected $fillable = [
        'user_id',
        'category_id',
        'type_id',
        'country_id',
        'rate_id',
        'card_amount',
        'card_currency',
        'ngn_amount',
        'status',
        'admin_id',
        'admin_note',
        'rejection_reason',
        'card_code',
    ];

    protected $casts = [
        'card_amount' => 'decimal:2',
        'ngn_amount' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    public function rate(): BelongsTo
    {
        return $this->belongsTo(GiftCardTradeRate::class, 'rate_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GiftCardTradeImage::class, 'trade_id');
    }
}
