<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PUserStat extends Model
{
    use HasFactory;

    protected $table = 'p2p_user_stats';
    
    protected $fillable = [
        'user_id',
        'total_trades',
        'completed_trades',
        'completion_rate',
        'avg_release_time_minutes',
        'rating',
        'disputes_raised',
        'reviews_count',
        'risk_score',
        'risk_level',
        'cancelled_orders_last_30d',
    ];

    protected $casts = [
        'completion_rate' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
