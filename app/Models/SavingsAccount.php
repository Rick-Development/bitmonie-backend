<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single savings pot: the user's Flexi Save account, an Auto Save plan
 * account or a Target Savings goal. The balance is a denormalised
 * projection of {@see SavingsEntry}; only SavingsLedger may mutate it.
 */
class SavingsAccount extends Model
{
    public const FLEXI = 'FLEXI';
    public const AUTO = 'AUTO';
    public const TARGET = 'TARGET';

    public const ACTIVE = 'ACTIVE';
    public const PAUSED = 'PAUSED';
    public const COMPLETED = 'COMPLETED';
    public const EXPIRED = 'EXPIRED';
    public const CANCELLED = 'CANCELLED';

    protected $table = 'savings_accounts';

    protected $guarded = ['id'];

    protected $casts = [
        'balance' => 'string',
        'held' => 'string',
        'total_deposited' => 'string',
        'total_withdrawn' => 'string',
        'interest_earned' => 'string',
        'target_amount' => 'string',
        'start_date' => 'date:Y-m-d',
        'deadline' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $hidden = ['flexi_key', 'legacy_ref'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SavingsEntry::class, 'account_id');
    }

    public function plan(): HasOne
    {
        return $this->hasOne(RecurringSavingsPlan::class, 'account_id');
    }

    public function availableBalance(): string
    {
        return bcsub((string) $this->balance, (string) $this->held, 2);
    }
}
