<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2POrder extends Model
{
    use HasFactory;

    protected $table = 'p2p_orders';
    protected $fillable = [
        'ad_id',
        'maker_id',
        'taker_id',
        'type',
        'asset',
        'quote_currency',
        'amount',
        'price',
        'locked_price',
        'total',
        'escrow_enabled',
        'status',
        'payment_deadline',
        'appeal_status',
        'appeal_reason',
        'evidence',
        'expires_at',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'evidence' => 'array',
        'escrow_enabled' => 'boolean',
        'expires_at' => 'datetime',
        'payment_deadline' => 'datetime',
        'locked_price' => 'decimal:8',
    ];

    // Link to trader who created the order
    public function maker()
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function ad()
    {
        return $this->belongsTo(P2PAd::class, 'ad_id');
    }

    public function taker()
    {
        return $this->belongsTo(User::class, 'taker_id');
    }

    public function chats()
    {
        return $this->hasMany(P2PChat::class, 'order_id');
    }
}
