<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCommissionSetting extends Model
{
    protected $fillable = ['commission_rate_percent', 'auto_credit'];

    protected $casts = [
        'commission_rate_percent' => 'decimal:4',
        'auto_credit' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'commission_rate_percent' => '20.0000',
            'auto_credit' => true,
        ]);
    }
}
