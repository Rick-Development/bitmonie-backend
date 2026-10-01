<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CryptoCardTransactions extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Table name
     */
    protected $table = 'crypto_card_transactions';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        // Relations
        'user_id',
        'crypto_card_id',                       // local card reference

        // Provider identifiers
        'card_provider',                        // 'sudo'
        'provider_transaction_id',              // Sudo transaction _id
        'provider_authorization_id',            // Sudo authorization _id (if linked)
        'provider_card_id',                     // Sudo card _id
        'provider_customer_id',

        // Core transaction data
        'transaction_type',                     // purchase, withdrawal, refund, reversal, balance_enquiry, fee, etc.
        'transaction_status',                   // pending, approved, declined, reversed, settled, failed
        'entry_type',                           // debit | credit

        // Amounts
        'amount',                               // major units (e.g. 1500.50) or minor units depending on your convention
        'fee',
        'currency',                             // NGN, USD
        'amount_in_minor',                      // optional: store in kobo/cents for precision

        // Merchant / Channel info
        'merchant_name',
        'merchant_id',
        'merchant_category_code',               // MCC
        'merchant_city',
        'merchant_country',
        'channel',                              // atm, pos, web, mobile

        // Card snapshot at time of transaction
        'masked_pan',
        'card_last_four',

        // Dates from provider
        'transaction_date',                     // when the transaction occurred
        'settled_at',
        'authorized_at',

        // Full payload for audit / future fields
        'metadata',
        'raw_response',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'amount'            => 'decimal:2',
        'fee'               => 'decimal:2',
        'amount_in_minor'   => 'integer',
        'metadata'          => 'array',
        'raw_response'      => 'array',
        'transaction_date'  => 'datetime',
        'settled_at'        => 'datetime',
        'authorized_at'     => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CryptoCardsModel::class, 'crypto_card_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSuccessful($query)
    {
        return $query->whereIn('transaction_status', ['approved', 'settled']);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('transaction_status', ['declined', 'failed']);
    }

    public function scopeDebits($query)
    {
        return $query->where('entry_type', 'debit');
    }

    public function scopeCredits($query)
    {
        return $query->where('entry_type', 'credit');
    }

    public function scopeForCard($query, $cardId)
    {
        return $query->where('crypto_card_id', $cardId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers – Create from Sudo responses
    |--------------------------------------------------------------------------
    */

    /**
     * Create a transaction record from a Sudo Transaction object
     */
    public static function createFromSudoTransaction(
        int $userId,
        int $cryptoCardId,
        array $sudoResponse,
        string $provider = 'sudo'
    ): self {
        $data = $sudoResponse['data'] ?? $sudoResponse;

        return self::create([
            'user_id'                   => $userId,
            'crypto_card_id'            => $cryptoCardId,
            'card_provider'             => $provider,
            'provider_transaction_id'   => $data['_id'] ?? null,
            'provider_authorization_id' => $data['authorization'] ?? $data['authorizationId'] ?? null,
            'provider_card_id'          => is_array($data['card'] ?? null)
                                            ? ($data['card']['_id'] ?? null)
                                            : ($data['card'] ?? null),
            'provider_customer_id'      => is_array($data['customer'] ?? null)
                                            ? ($data['customer']['_id'] ?? null)
                                            : ($data['customer'] ?? null),

            'transaction_type'          => $data['type'] ?? $data['transactionType'] ?? 'purchase',
            'transaction_status'        => $data['status'] ?? 'pending',
            'entry_type'                => self::resolveEntryType($data),

            'amount'                    => self::toMajorUnits($data['amount'] ?? 0, $data['currency'] ?? 'NGN'),
            'fee'                       => self::toMajorUnits($data['fee'] ?? 0, $data['currency'] ?? 'NGN'),
            'currency'                  => $data['currency'] ?? 'NGN',
            'amount_in_minor'           => $data['amount'] ?? null,

            'merchant_name'             => data_get($data, 'merchant.name') ?? data_get($data, 'merchantName'),
            'merchant_id'               => data_get($data, 'merchant.merchantId') ?? data_get($data, 'merchantId'),
            'merchant_category_code'    => data_get($data, 'merchant.category') ?? data_get($data, 'merchantCategoryCode'),
            'merchant_city'             => data_get($data, 'merchant.city'),
            'merchant_country'          => data_get($data, 'merchant.country'),
            'channel'                   => $data['channel'] ?? null,

            'masked_pan'                => data_get($data, 'card.maskedPan') ?? $data['maskedPan'] ?? null,
            'card_last_four'            => self::extractLastFour(
                data_get($data, 'card.maskedPan') ?? $data['maskedPan'] ?? null
            ),

            'transaction_date'          => $data['transactionDate'] ?? $data['createdAt'] ?? now(),
            'settled_at'                => $data['settledAt'] ?? null,
            'authorized_at'             => $data['authorizedAt'] ?? $data['createdAt'] ?? null,

            'metadata'                  => $data['metadata'] ?? null,
            'raw_response'              => $sudoResponse,
        ]);
    }

    /**
     * Create from a Sudo Authorization object (before it becomes a transaction)
     */
    public static function createFromSudoAuthorization(
        int $userId,
        int $cryptoCardId,
        array $sudoResponse,
        string $provider = 'sudo'
    ): self {
        $data = $sudoResponse['data'] ?? $sudoResponse;

        return self::create([
            'user_id'                   => $userId,
            'crypto_card_id'            => $cryptoCardId,
            'card_provider'             => $provider,
            'provider_authorization_id' => $data['_id'] ?? null,
            'provider_card_id'          => is_array($data['card'] ?? null)
                                            ? ($data['card']['_id'] ?? null)
                                            : ($data['card'] ?? null),
            'provider_customer_id'      => is_array($data['customer'] ?? null)
                                            ? ($data['customer']['_id'] ?? null)
                                            : ($data['customer'] ?? null),

            'transaction_type'          => $data['type'] ?? 'authorization',
            'transaction_status'        => $data['status'] ?? 'pending',
            'entry_type'                => 'debit',

            'amount'                    => self::toMajorUnits($data['amount'] ?? 0, $data['currency'] ?? 'NGN'),
            'currency'                  => $data['currency'] ?? 'NGN',
            'amount_in_minor'           => $data['amount'] ?? null,

            'merchant_name'             => data_get($data, 'merchant.name'),
            'merchant_id'               => data_get($data, 'merchant.merchantId'),
            'merchant_category_code'    => data_get($data, 'merchant.category'),
            'merchant_city'             => data_get($data, 'merchant.city'),
            'merchant_country'          => data_get($data, 'merchant.country'),
            'channel'                   => $data['channel'] ?? null,

            'transaction_date'          => $data['createdAt'] ?? now(),
            'authorized_at'             => $data['createdAt'] ?? now(),

            'metadata'                  => $data['metadata'] ?? null,
            'raw_response'              => $sudoResponse,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    protected static function resolveEntryType(array $data): string
    {
        $type = strtolower($data['type'] ?? $data['transactionType'] ?? '');

        return match (true) {
            in_array($type, ['refund', 'reversal', 'credit']) => 'credit',
            default => 'debit',
        };
    }

    protected static function toMajorUnits($amount, string $currency = 'NGN'): float
    {
        // Sudo usually returns amounts in major units already for most endpoints.
        // Adjust this if your integration receives minor units.
        return (float) $amount;
    }

    protected static function extractLastFour(?string $maskedPan): ?string
    {
        if (!$maskedPan) {
            return null;
        }

        return substr($maskedPan, -4);
    }
}