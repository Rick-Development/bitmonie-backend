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
        'card_issuance_fee',
        'card_funding_fee',
        'monthly_card_maintenance_fee',
    ];

    protected $casts = [
        'physical_card_fee' => 'decimal:2',
        'card_issuance_fee' => 'decimal:2',
        'card_funding_fee' => 'decimal:2',
        'monthly_card_maintenance_fee' => 'decimal:2',
    ];
}