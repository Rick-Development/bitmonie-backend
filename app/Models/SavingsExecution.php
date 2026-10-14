<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsExecution extends Model
{
    public const PENDING = 'PENDING';
    public const PROCESSING = 'PROCESSING';
    public const COMPLETED = 'COMPLETED';
    public const FAILED = 'FAILED';
    public const SKIPPED = 'SKIPPED';

    protected $table = 'savings_executions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'string',
        'scheduled_for' => 'datetime',
        'next_attempt_at' => 'datetime',
        'locked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RecurringSavingsPlan::class, 'plan_id');
    }
}
