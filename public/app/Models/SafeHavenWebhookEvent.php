<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafeHavenWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'safehaven_webhook_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'credit_amount' => 'decimal:8',
        'balance_after' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'wallet_id');
    }
}
