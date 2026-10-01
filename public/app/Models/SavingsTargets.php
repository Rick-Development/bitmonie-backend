<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsTargets extends Model
{
    use HasFactory;

    protected $table = 'savings_targets';

    protected $fillable = [
        'user_id',
        'savings_id',
        'plan_id',
        'balance',
        'target_title',
        'target_amount',
        'interest_rate',
        'locked_until',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        // 'goal_target' => 'decimal:2',
        // 'interest_rate' => 'decimal:2',
        // 'locked_until' => 'datetime',
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function savings()
    {
        return $this->belongsTo(Savings::class, 'savings_id');
    }
}
