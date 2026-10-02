<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEventLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'duplicate' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function reconciliationLogs()
    {
        return $this->hasMany(WebhookReconciliationLog::class);
    }
}
