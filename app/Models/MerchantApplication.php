<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantApplication extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'phone',
        'whatsapp',
        'business_name',
        'quidax_usdt_balance',
        'min_usdt_required',
        'security_deposit_amount',
        'security_deposit_currency',
        'security_deposit_status',
        'security_deposit_lock_reference',
        'security_deposit_locked_at',
        'security_deposit_release_reference',
        'security_deposit_released_at',
        'security_deposit_release_reason',
        'balance_verified_at',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'merchant_deactivated_at',
        'merchant_deactivation_reason',
    ];

    protected $casts = [
        'quidax_usdt_balance' => 'decimal:8',
        'min_usdt_required' => 'decimal:8',
        'security_deposit_amount' => 'decimal:8',
        'balance_verified_at' => 'datetime',
        'security_deposit_locked_at' => 'datetime',
        'security_deposit_released_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'merchant_deactivated_at' => 'datetime',
    ];

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'approved' && $this->merchant_deactivated_at !== null) {
            return 'deactivated';
        }

        return (string) $this->status;
    }

    public function hasLockedSecurityDeposit(): bool
    {
        return $this->security_deposit_status === 'locked'
            && (float) $this->security_deposit_amount > 0;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'reviewed_by');
    }
}
