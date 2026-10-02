<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PAd extends Model
{
    use HasFactory;

    protected $table = 'p2p_ads';
    
    protected $fillable = [
        'user_id',
        'type',
        'asset',
        'fiat',
        'price_type',
        'price',
        'margin',
        'total_amount',
        'available_amount',
        'min_limit',
        'max_limit',
        'payment_method_ids',
        'terms',
        'auto_reply',
        'time_limit',
        'status',
    ];

    protected $casts = [
        'payment_method_ids' => 'array',
        'price' => 'decimal:8',
        'margin' => 'decimal:2',
        'total_amount' => 'decimal:8',
        'available_amount' => 'decimal:8',
        'min_limit' => 'decimal:8',
        'max_limit' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(P2POrder::class, 'ad_id');
    }

    public function paymentMethods()
    {
        return P2PPaymentMethod::whereIn('id', $this->payment_method_ids ?? [])->get();
    }
}
