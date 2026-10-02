<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EasyEarnSetting extends Model
{
    protected $table = 'easyearn_settings';

    protected $fillable = [
        'return_multiplier',
        'term_days',
        'early_withdrawal_enabled',
        'early_withdrawal_penalty_percent',
        'terms',
    ];

    protected $casts = [
        'return_multiplier' => 'decimal:8',
        'term_days' => 'integer',
        'early_withdrawal_enabled' => 'boolean',
        'early_withdrawal_penalty_percent' => 'decimal:4',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'return_multiplier' => '2.00000000',
            'term_days' => 30,
            'early_withdrawal_enabled' => false,
            'early_withdrawal_penalty_percent' => '100.0000',
            'terms' => 'USDT is locked until maturity. At maturity, the configured PowerBonus return is credited to the user USDT wallet. Early withdrawal is locked by default.',
        ]);
    }
}
