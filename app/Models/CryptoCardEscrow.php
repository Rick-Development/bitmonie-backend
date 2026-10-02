<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CryptoCardEscrow extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crypto_card_escrows';

    protected $fillable = [
        // Ownership
        'user_id',
        'crypto_card_id',
        'crypto_card_transaction_id',

        // Provider references
        'card_provider',                    // sudo, etc.
        'provider_authorization_id',
        'provider_transaction_id',

        // Movement classification
        'type',                             // hold | capture | release | reversal | refund | fee | topup
        'direction',                        // debit | credit
        'status',                           // pending | completed | reversed | failed | cancelled

        // Amounts
        'amount',
        'fee',
        'currency',

        // Master account tracking
        'master_account_impact',            // amount moved from/to your master funding account
        'master_account_reference',         // internal reference of the master ledger entry

        // Context
        'description',
        'merchant_name',
        'merchant_id',
        'merchant_category_code',
        'channel',                          // atm, pos, web, mobile

        // Audit
        'metadata',
        'raw_response',
    ];

    protected $casts = [
        'amount'                => 'decimal:2',
        'fee'                   => 'decimal:2',
        'master_account_impact' => 'decimal:2',
        'metadata'              => 'array',
        'raw_response'          => 'array',
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

    public function card(): BelongsTo
    {
        return $this->belongsTo(CryptoCardsModel::class, 'crypto_card_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CryptoCardTransactions::class, 'crypto_card_transaction_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeHolds($query)
    {
        return $query->where('type', 'hold');
    }

    public function scopeCaptures($query)
    {
        return $query->where('type', 'capture');
    }

    public function scopeReversals($query)
    {
        return $query->whereIn('type', ['reversal', 'refund']);
    }

    public function scopeForCard($query, int $cardId)
    {
        return $query->where('crypto_card_id', $cardId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a pending hold (called during authorization.request)
     */
    public static function createHold(
        CryptoCardsModel $card,
        float $amount,
        array $context = []
    ): self {
        return self::create([
            'user_id'                   => $card->user_id,
            'crypto_card_id'            => $card->id,
            'card_provider'             => $card->card_provider,
            'provider_authorization_id' => $context['provider_authorization_id'] ?? null,
            'type'                      => 'hold',
            'direction'                 => 'debit',
            'status'                    => 'pending',
            'amount'                    => $amount,
            'currency'                  => $card->card_currency ?? 'NGN',
            'description'               => $context['description'] ?? 'Authorization hold',
            'merchant_name'             => $context['merchant_name'] ?? null,
            'merchant_id'               => $context['merchant_id'] ?? null,
            'merchant_category_code'    => $context['merchant_category_code'] ?? null,
            'channel'                   => $context['channel'] ?? null,
            'metadata'                  => $context['metadata'] ?? null,
            'raw_response'              => $context['raw_response'] ?? null,
        ]);
    }

    /**
     * Mark this hold as captured (transaction settled)
     */
    public function markAsCaptured(?string $providerTransactionId = null): self
    {
        $this->update([
            'type'                   => 'capture',
            'status'                 => 'completed',
            'provider_transaction_id'=> $providerTransactionId ?? $this->provider_transaction_id,
        ]);

        return $this;
    }

    /**
     * Release a pending hold (authorization declined or expired)
     */
    public function release(): self
    {
        $this->update([
            'type'   => 'release',
            'status' => 'cancelled',
        ]);

        return $this;
    }

    /**
     * Create a reversal / refund entry
     */
    public static function createReversal(
        CryptoCardsModel $card,
        float $amount,
        array $context = []
    ): self {
        return self::create([
            'user_id'                => $card->user_id,
            'crypto_card_id'         => $card->id,
            'card_provider'          => $card->card_provider,
            'provider_transaction_id'=> $context['provider_transaction_id'] ?? null,
            'type'                   => $context['type'] ?? 'reversal',
            'direction'              => 'credit',
            'status'                 => 'completed',
            'amount'                 => $amount,
            'currency'               => $card->card_currency ?? 'NGN',
            'description'            => $context['description'] ?? 'Transaction reversal',
            'merchant_name'          => $context['merchant_name'] ?? null,
            'channel'                => $context['channel'] ?? null,
            'metadata'               => $context['metadata'] ?? null,
            'raw_response'           => $context['raw_response'] ?? null,
        ]);
    }
}