<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutosavePlan extends Model
{
    use HasFactory;

    public const MODE_SCHEDULED = 'scheduled';
    public const MODE_PERCENTAGE = 'percentage';
    public const MODE_BOTH = 'both';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'name',
        'mode',
        'amount',
        'percentage',
        'frequency',
        'goal_amount',
        'maturity_date',
        'status',
        'balance',
        'next_due_at',
        'last_deducted_at',
        'metadata',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'decimal:8',
        'percentage' => 'decimal:4',
        'goal_amount' => 'decimal:8',
        'balance' => 'decimal:8',
        'maturity_date' => 'date',
        'next_due_at' => 'datetime',
        'last_deducted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(AutosaveTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function usesScheduledMode(): bool
    {
        return in_array($this->mode, [self::MODE_SCHEDULED, self::MODE_BOTH], true);
    }

    public function usesPercentageMode(): bool
    {
        return in_array($this->mode, [self::MODE_PERCENTAGE, self::MODE_BOTH], true);
    }
}
