<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoCardSetup extends Model
{
    use HasFactory;

    protected $table = 'crypto_card_setups';

    protected $fillable = [
        'physical_card_fee',
        'physical_card_funding_fee',
        'physical_monthly_card_maintenance_fee',
        'virtual_card_issuance_fee',
        'virtual_card_funding_fee',
        'virtual_monthly_card_maintenance_fee',
        'card_issuance_fee',
        'card_funding_fee',
        'monthly_card_maintenance_fee',
    ];

    protected $casts = [
        'physical_card_fee' => 'decimal:2',
        'physical_card_funding_fee' => 'decimal:2',
        'physical_monthly_card_maintenance_fee' => 'decimal:2',
        'virtual_card_issuance_fee' => 'decimal:2',
        'virtual_card_funding_fee' => 'decimal:2',
        'virtual_monthly_card_maintenance_fee' => 'decimal:2',
        'card_issuance_fee' => 'decimal:2',
        'card_funding_fee' => 'decimal:2',
        'monthly_card_maintenance_fee' => 'decimal:2',
    ];

    /**
     * Get Virtual Card Issuance Fee (with legacy fallback).
     */
    public function getVirtualIssuanceFeeAttribute(): float
    {
        return (float) ($this->attributes['virtual_card_issuance_fee'] ?? $this->attributes['card_issuance_fee'] ?? 0);
    }

    /**
     * Get Virtual Card Funding Fee (with legacy fallback).
     */
    public function getVirtualFundingFeeAttribute(): float
    {
        return (float) ($this->attributes['virtual_card_funding_fee'] ?? $this->attributes['card_funding_fee'] ?? 0);
    }

    /**
     * Get Virtual Card Monthly Maintenance Fee (with legacy fallback).
     */
    public function getVirtualMaintenanceFeeAttribute(): float
    {
        return (float) ($this->attributes['virtual_monthly_card_maintenance_fee'] ?? $this->attributes['monthly_card_maintenance_fee'] ?? 0);
    }

    /**
     * Get Physical Card Order Fee.
     */
    public function getPhysicalOrderFeeAttribute(): float
    {
        return (float) ($this->attributes['physical_card_fee'] ?? 0);
    }

    /**
     * Get Physical Card Funding Fee.
     */
    public function getPhysicalFundingFeeAttribute(): float
    {
        return (float) ($this->attributes['physical_card_funding_fee'] ?? 0);
    }

    /**
     * Get Physical Card Monthly Maintenance Fee.
     */
    public function getPhysicalMaintenanceFeeAttribute(): float
    {
        return (float) ($this->attributes['physical_monthly_card_maintenance_fee'] ?? 0);
    }
}