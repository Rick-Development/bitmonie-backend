<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PTakers extends Model
{
    use HasFactory;

    protected $table = 'p2p_takers';

    protected $fillable = [
        'taker_id',
        'maker_id',
        'type',
        'quote_currency',
        'amount',
        'price',
        'total',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taker_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(P2POrder::class, 'maker_id');
    }
}
