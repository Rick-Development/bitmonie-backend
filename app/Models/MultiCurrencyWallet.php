<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class MultiCurrencyWallet extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'multi_currency_wallets';

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'user_id',

        'currency',
        'account_type',
        'provider',

        'provider_business_id',

        'virtual_account_number',
        'account_name',
        'bank_name',
        'bank_code',

        'provider_wallet_number',

        'provider_reference',
        'provider_account_id',

        'status',

        'can_receive',
        'can_send',

        'provider_metadata',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'can_receive' => 'boolean',
        'can_send' => 'boolean',

        'provider_metadata' => 'array',
    ];

    /**
     * The user who owns this wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to active wallets.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by currency.
     */
    public function scopeCurrency($query, string $currency)
    {
        return $query->where(
            'currency',
            strtoupper($currency)
        );
    }

    /**
     * Scope by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where(
            'provider',
            strtolower($provider)
        );
    }

    /**
     * Determine whether the wallet can receive funds.
     */
    public function canReceive(): bool
    {
        return $this->status === 'active'
            && $this->can_receive === true;
    }

    /**
     * Determine whether the wallet can send funds.
     */
    public function canSend(): bool
    {
        return $this->status === 'active'
            && $this->can_send === true;
    }

    /**
     * Determine whether the wallet is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether this is a Fincra wallet.
     */
    public function isFincra(): bool
    {
        return strtolower($this->provider) === 'fincra';
    }

    /**
     * Get the wallet's display account number.
     *
     * For local currency accounts, the virtual account number
     * is normally the primary account identifier.
     */
    public function getAccountNumberAttribute(): ?string
    {
        return $this->virtual_account_number
            ?: $this->provider_wallet_number;
    }

    /**
     * Get provider metadata value.
     */
    public function providerData(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $this->provider_metadata ?? [];
        }

        return data_get(
            $this->provider_metadata,
            $key,
            $default
        );
    }
}