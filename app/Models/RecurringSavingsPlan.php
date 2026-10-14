<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringSavingsPlan extends Model
{
    public const DAILY = 'DAILY';
    public const WEEKLY = 'WEEKLY';
    public const MONTHLY = 'MONTHLY';

    public const ACTIVE = 'ACTIVE';
    public const PAUSED = 'PAUSED';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';

    protected $table = 'savings_recurring_plans';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'string',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'next_run_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'account_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(SavingsExecution::class, 'plan_id');
    }
}
