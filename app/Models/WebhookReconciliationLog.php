<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookReconciliationLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'duplicate' => 'boolean',
        'changes' => 'array',
    ];

    public function eventLog()
    {
        return $this->belongsTo(WebhookEventLog::class, 'webhook_event_log_id');
    }
}
