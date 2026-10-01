<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoCardWebhookLog extends Model
{
    protected $table = 'crypto_card_webhook_logs';

    protected $fillable = [
        'provider',                     // sudo, stripe, etc.
        'event_type',                   // authorization.request, card.balance, etc.
        'event_id',                     // Sudo's _id
        'provider_card_id',
        'provider_customer_id',
        'crypto_card_id',               // local FK
        'user_id',
        'direction',                    // inbound | outbound
        'status',                       // received, processed, failed, timed_out
        'http_status',
        'response_code',                // 00, 51, 96...
        'decision',                     // approved | declined
        'amount',
        'currency',
        'processing_time_ms',
        'ip_address',
        'payload',                      // full request body
        'response_body',                // what we sent back
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'payload'        => 'array',
        'response_body'  => 'array',
        'metadata'       => 'array',
        'amount'         => 'decimal:2',
        'processing_time_ms' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(CryptoCardsModel::class, 'crypto_card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}