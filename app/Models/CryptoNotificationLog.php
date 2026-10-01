<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoNotificationLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'duplicate' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
