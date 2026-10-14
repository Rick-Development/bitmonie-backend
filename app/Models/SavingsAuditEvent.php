<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsAuditEvent extends Model
{
    public $timestamps = false;

    protected $table = 'savings_audit_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
