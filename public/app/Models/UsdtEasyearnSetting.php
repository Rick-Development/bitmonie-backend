<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsdtEasyearnSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'current_monthly_rate',
        'min_investment',
        'max_investment',
        'is_active',
        'payout_day',
        'auto_credit_enabled',
    ];

    protected $casts = [
        'current_monthly_rate' => 'decimal:2',
        'min_investment' => 'decimal:8',
        'max_investment' => 'decimal:8',
        'is_active' => 'boolean',
        'auto_credit_enabled' => 'boolean',
    ];

    public $timestamps = false;

    /**
     * Get the singleton settings instance
     */
    public static function getSettings()
    {
        return static::first() ?? static::create([
            'current_monthly_rate' => 10.00,
            'min_investment' => 10.00,
            'is_active' => true,
            'payout_day' => 10,
            'auto_credit_enabled' => false,
        ]);
    }

    /**
     * Check if new investments are allowed
     */
    public function investmentsEnabled()
    {
        return $this->is_active;
    }

    /**
     * Validate investment amount
     */
    public function isValidAmount($amount)
    {
        if (bccomp($amount, $this->min_investment, 8) < 0) {
            return false;
        }

        if ($this->max_investment && bccomp($amount, $this->max_investment, 8) > 0) {
            return false;
        }

        return true;
    }
}
