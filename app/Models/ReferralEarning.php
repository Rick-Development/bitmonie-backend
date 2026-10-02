<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralEarning extends Model
{
    protected $fillable = [
        'user_id',
        'referral_id',
        'amount',
        'currency_code',
        'type',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who earned
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related referral
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }
}
