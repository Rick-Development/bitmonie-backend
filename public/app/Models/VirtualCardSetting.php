<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VirtualCardSetting extends Model
{
    use HasFactory;

    protected $table = 'virtual_card_settings';

    protected $fillable = [
        // Issuance
        'card_creation_fee',
        'min_card_topup',
        'max_card_topup',
        'min_card_withdrawal',
        'max_card_withdrawal',
        // Rates
        'usdt_to_usd_rate',
        'topup_fee_percent',
        'withdrawal_fee_percent',
        'fx_markup_percent',
        // Limits
        'max_cards_per_user',
        'max_card_balance',
        // Brands
        'visa_enabled',
        'mastercard_enabled',
        // Feature switches
        'card_creation_enabled',
        'card_topup_enabled',
        'card_withdrawal_enabled',
        'card_freeze_enabled',
        'card_terminate_enabled',
        // Notifications
        'email_on_topup',
        'email_on_withdrawal',
        'email_on_decline',
    ];

    protected $casts = [
        'card_creation_fee'       => 'decimal:2',
        'min_card_topup'          => 'decimal:2',
        'max_card_topup'          => 'decimal:2',
        'min_card_withdrawal'     => 'decimal:2',
        'max_card_withdrawal'     => 'decimal:2',
        'usdt_to_usd_rate'        => 'decimal:4',
        'topup_fee_percent'       => 'decimal:2',
        'withdrawal_fee_percent'  => 'decimal:2',
        'fx_markup_percent'       => 'decimal:2',
        'max_card_balance'        => 'decimal:2',
        'visa_enabled'            => 'boolean',
        'mastercard_enabled'      => 'boolean',
        'card_creation_enabled'   => 'boolean',
        'card_topup_enabled'      => 'boolean',
        'card_withdrawal_enabled' => 'boolean',
        'card_freeze_enabled'     => 'boolean',
        'card_terminate_enabled'  => 'boolean',
        'email_on_topup'          => 'boolean',
        'email_on_withdrawal'     => 'boolean',
        'email_on_decline'        => 'boolean',
    ];

    /**
     * Singleton: get or create the single settings row.
     */
    public static function getSettings(): static
    {
        return static::first() ?? static::create([
            'card_creation_fee'       => 3.00,
            'min_card_topup'          => 5.00,
            'max_card_topup'          => 500.00,
            'min_card_withdrawal'     => 1.00,
            'max_card_withdrawal'     => 500.00,
            'usdt_to_usd_rate'        => 1.0000,
            'topup_fee_percent'       => 0.00,
            'withdrawal_fee_percent'  => 0.00,
            'fx_markup_percent'       => 2.00,
            'max_cards_per_user'      => 1,
            'max_card_balance'        => 10000.00,
            'visa_enabled'            => true,
            'mastercard_enabled'      => false,
            'card_creation_enabled'   => true,
            'card_topup_enabled'      => true,
            'card_withdrawal_enabled' => true,
            'card_freeze_enabled'     => true,
            'card_terminate_enabled'  => true,
            'email_on_topup'          => true,
            'email_on_withdrawal'     => true,
            'email_on_decline'        => true,
        ]);
    }
}
