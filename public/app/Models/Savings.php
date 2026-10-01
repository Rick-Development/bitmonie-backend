<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Savings extends Model
{
    use HasFactory;

    protected $table = 'savings';

    protected $fillable = [
        'user_id',
        // 'plan_id',
        // 'title',
        'balance',
        // 'goal_target',
        // 'interest_rate',
        // 'locked_until',
        // 'status',
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

    public function savings_targets()
    {
        return $this->hasMany(SavingsTargets::class);
    }
}
