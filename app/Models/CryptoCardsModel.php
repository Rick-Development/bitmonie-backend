<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoCardsModel extends Model
{
    use HasFactory;

    protected $table = 'crypto_cards';

    protected $fillable = [
        // Local ownership
        'user_id',

        // Provider identification
        'card_provider',
        'card_provider_id',
        'card_provider_customer_id',
        'card_provider_account_id',
        'card_provider_funding_source_id',

        // Card details
        'card_type',
        'card_brand',
        'card_currency',
        'card_status',
        'masked_pan',
        'card_last_four',
        'expiry_month',
        'expiry_year',

        // Balance fields
        'card_balance',
        'pending_holds',
        'ledger_balance',
        'last_balance_synced_at',

        // Spending controls
        'spending_limits_amount',
        'spending_limits_interval',
        'spending_controls',

        // Flags
        'is_2fa_enrolled',
        'is_default_pin_changed',
        'is_disposable',
        'is_deleted',

        // Metadata
        'metadata',
        'raw_response',
    ];

    protected $casts = [
        'spending_controls'      => 'array',
        'metadata'               => 'array',
        'raw_response'           => 'array',

        'is_2fa_enrolled'        => 'boolean',
        'is_default_pin_changed' => 'boolean',
        'is_disposable'          => 'boolean',
        'is_deleted'             => 'boolean',

        'spending_limits_amount' => 'decimal:2',
        'card_balance'           => 'decimal:2',
        'pending_holds'          => 'decimal:2',
        'ledger_balance'         => 'decimal:2',

        'last_balance_synced_at' => 'datetime',

        'expiry_month'           => 'string',
        'expiry_year'            => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CryptoCardTransactions::class, 'crypto_card_id');
    }

    public function escrows(): HasMany
    {
        return $this->hasMany(CryptoCardEscrow::class, 'crypto_card_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Balance Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Available balance the card can still spend
     */
    public function getAvailableBalanceAttribute(): float
    {
        return max(0, (float) $this->card_balance - (float) $this->pending_holds);
    }

    /**
     * Place a hold (used during authorization.request)
     */
    public function placeHold(float $amount): void
    {
        $this->increment('pending_holds', $amount);
        $this->updateLedgerBalance();
    }

    /**
     * Release a hold (authorization declined or expired)
     */
    public function releaseHold(float $amount): void
    {
        $this->decrement('pending_holds', min($amount, $this->pending_holds));
        $this->updateLedgerBalance();
    }

    /**
     * Capture a hold (transaction settled)
     */
    public function captureHold(float $amount): void
    {
        $this->decrement('pending_holds', min($amount, $this->pending_holds));
        $this->decrement('card_balance', $amount);
        $this->updateLedgerBalance();
    }

    /**
     * Credit the card (refund / reversal / top-up)
     */
    public function credit(float $amount): void
    {
        $this->increment('card_balance', $amount);
        $this->updateLedgerBalance();
    }

    /**
     * Recalculate ledger_balance
     */
    public function updateLedgerBalance(): void
    {
        $this->ledger_balance = (float) $this->card_balance + (float) $this->pending_holds;
        $this->last_balance_synced_at = now();
        $this->saveQuietly();
    }

    /*
    |--------------------------------------------------------------------------
    | Sudo Helpers
    |--------------------------------------------------------------------------
    */

    public static function createFromSudoResponse(
        int $userId,
        array $sudoResponse,
        string $provider = 'sudo'
    ): self {
        $data = $sudoResponse['data'] ?? $sudoResponse;

        $maskedPan = $data['maskedPan'] ?? null;
        $lastFour  = $maskedPan ? substr($maskedPan, -4) : null;

        $spendingLimits = $data['spendingControls']['spendingLimits'][0] ?? [];

        return self::create([
            'user_id'                         => $userId,
            'card_provider'                   => $provider,
            'card_provider_id'                => $data['_id'] ?? null,
            'card_provider_customer_id'       => is_array($data['customer'] ?? null)
                ? ($data['customer']['_id'] ?? null)
                : ($data['customer'] ?? null),
            'card_provider_account_id'        => is_array($data['account'] ?? null)
                ? ($data['account']['_id'] ?? null)
                : ($data['account'] ?? null),
            'card_provider_funding_source_id' => is_array($data['fundingSource'] ?? null)
                ? ($data['fundingSource']['_id'] ?? null)
                : ($data['fundingSource'] ?? null),

            'card_type'                       => $data['type'] ?? null,
            'card_brand'                      => $data['brand'] ?? null,
            'card_currency'                   => $data['currency'] ?? null,
            'card_status'                     => $data['status'] ?? 'active',
            'masked_pan'                      => $maskedPan,
            'card_last_four'                  => $lastFour,
            'expiry_month'                    => $data['expiryMonth'] ?? null,
            'expiry_year'                     => $data['expiryYear'] ?? null,

            'card_balance'                    => 0,
            'pending_holds'                   => 0,
            'ledger_balance'                  => 0,

            'spending_limits_amount'          => $spendingLimits['amount'] ?? null,
            'spending_limits_interval'        => $spendingLimits['interval'] ?? null,
            'spending_controls'               => $data['spendingControls'] ?? null,

            'is_2fa_enrolled'                 => $data['is2FAEnrolled'] ?? false,
            'is_default_pin_changed'          => $data['isDefaultPINChanged'] ?? false,
            'is_disposable'                   => $data['disposable'] ?? false,
            'is_deleted'                      => $data['isDeleted'] ?? false,

            'metadata'                        => $data['metadata'] ?? null,
            'raw_response'                    => $sudoResponse,
        ]);
    }

    public function updateFromSudoResponse(array $sudoResponse): self
    {
        $data = $sudoResponse['data'] ?? $sudoResponse;

        $maskedPan = $data['maskedPan'] ?? $this->masked_pan;
        $lastFour  = $maskedPan ? substr($maskedPan, -4) : $this->card_last_four;

        $spendingLimits = $data['spendingControls']['spendingLimits'][0] ?? [];

        $this->update([
            'card_status'              => $data['status'] ?? $this->card_status,
            'masked_pan'               => $maskedPan,
            'card_last_four'           => $lastFour,
            'expiry_month'             => $data['expiryMonth'] ?? $this->expiry_month,
            'expiry_year'              => $data['expiryYear'] ?? $this->expiry_year,
            'spending_limits_amount'   => $spendingLimits['amount'] ?? $this->spending_limits_amount,
            'spending_limits_interval' => $spendingLimits['interval'] ?? $this->spending_limits_interval,
            'spending_controls'        => $data['spendingControls'] ?? $this->spending_controls,
            'is_2fa_enrolled'          => $data['is2FAEnrolled'] ?? $this->is_2fa_enrolled,
            'is_default_pin_changed'   => $data['isDefaultPINChanged'] ?? $this->is_default_pin_changed,
            'is_disposable'            => $data['disposable'] ?? $this->is_disposable,
            'is_deleted'               => $data['isDeleted'] ?? $this->is_deleted,
            'metadata'                 => $data['metadata'] ?? $this->metadata,
            'raw_response'             => $sudoResponse,
        ]);

        return $this;
    }
}