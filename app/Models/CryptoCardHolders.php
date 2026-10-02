<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class CryptoCardHolders extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crypto_card_holders';

    protected $fillable = [
        // Ownership
        'user_id',

        // Core identity (provider-agnostic)
        'type',                     // individual | company
        'status',                   // active | inactive | suspended
        'is_approved',              // overall KYC status on your platform
        'name',
        'first_name',
        'last_name',
        'other_names',
        'email',
        'phone_number',
        'date_of_birth',

        // Identity / KYC
        'identity_type',            // BVN, NIN, PASSPORT, etc.
        'identity_number',
        'identity_country',

        // Billing Address
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',

        // Documents
        'id_front_url',
        'id_back_url',
        'address_verification_url',
        'other_documents',          // JSON

        // Multi-vendor mapping
        // Example structure:
        // [
        //   "sudo" => ["customer_id" => "64a1...", "business_id" => "...", "status" => "active", "is_approved" => true],
        //   "stripe" => ["customer_id" => "ich_...", "status" => "active"]
        // ]
        'provider_customers',

        // Audit
        'metadata',
        'raw_responses',            // store last response per provider if needed
    ];

    protected $casts = [
        'is_approved'        => 'boolean',
        'date_of_birth'      => 'date',
        'other_documents'    => 'array',
        'provider_customers' => 'array',
        'metadata'           => 'array',
        'raw_responses'      => 'array',
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

    public function cards(): HasMany
    {
        return $this->hasMany(CryptoCardsModel::class, 'card_holder_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CryptoCardTransactions::class, 'card_holder_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope a query to only include approved holders.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include active holders.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to holders belonging to a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to active holders for a specific user.
     * Convenience scope used heavily by CryptoCardService.
     */
    public function scopeActiveForUser(Builder $query, int $userId): Builder
    {
        return $query->forUser($userId)->active();
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-vendor Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get provider-specific customer data
     */
    public function getProviderCustomer(string $provider): ?array
    {
        return Arr::get($this->provider_customers ?? [], strtolower($provider));
    }

    /**
     * Get the customer ID for a specific provider
     */
    public function getProviderCustomerId(string $provider): ?string
    {
        return Arr::get($this->getProviderCustomer($provider) ?? [], 'customer_id');
    }

    /**
     * Check if this holder is already registered with a provider
     */
    public function hasProvider(string $provider): bool
    {
        return !empty($this->getProviderCustomerId($provider));
    }

    /**
     * Attach / update a provider customer mapping
     */
    public function setProviderCustomer(string $provider, array $data): self
    {
        $providers = $this->provider_customers ?? [];

        $providers[strtolower($provider)] = array_merge(
            $providers[strtolower($provider)] ?? [],
            $data
        );

        $this->provider_customers = $providers;
        $this->save();

        return $this;
    }

    /**
     * Remove a provider mapping
     */
    public function removeProviderCustomer(string $provider): self
    {
        $providers = $this->provider_customers ?? [];
        unset($providers[strtolower($provider)]);
        $this->provider_customers = $providers;
        $this->save();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Create / Update from any provider
    |--------------------------------------------------------------------------
    */

    /**
     * Create or update a holder from any provider response
     */
    public static function syncFromProvider(
        int $userId,
        string $provider,
        array $response,
        array $extra = []
    ): self {
        $provider = strtolower($provider);
        $data     = $response['data'] ?? $response;

        // Try to find existing holder for this user
        $holder = self::where('user_id', $userId)->first();

        if (!$holder) {
            $holder = new self();
            $holder->user_id = $userId;
        }

        // Map common fields (works for most issuers)
        $holder->fill(array_merge([
            'type'          => $data['type'] ?? $holder->type ?? 'individual',
            'status'        => $data['status'] ?? $holder->status ?? 'active',
            'is_approved'   => $data['isApproved'] ?? $data['is_approved'] ?? $holder->is_approved ?? false,
            'name'          => $data['name'] ?? $holder->name,
            'email'         => $data['emailAddress'] ?? $data['email'] ?? $holder->email,
            'phone_number'  => $data['phoneNumber'] ?? $data['phone'] ?? $holder->phone_number,
        ], $extra));

        // Individual details (Sudo style + generic)
        $individual = $data['individual'] ?? [];
        if (!empty($individual)) {
            $holder->first_name     = $individual['firstName'] ?? $individual['first_name'] ?? $holder->first_name;
            $holder->last_name      = $individual['lastName'] ?? $individual['last_name'] ?? $holder->last_name;
            $holder->other_names    = $individual['otherNames'] ?? $individual['other_names'] ?? $holder->other_names;
            $holder->date_of_birth  = self::normalizeDate($individual['dob'] ?? $individual['date_of_birth'] ?? null) ?? $holder->date_of_birth;

            $identity = $individual['identity'] ?? [];
            $holder->identity_type   = $identity['type'] ?? $holder->identity_type;
            $holder->identity_number = $identity['number'] ?? $holder->identity_number;

            $documents = $individual['documents'] ?? [];
            $holder->id_front_url             = $documents['idFrontUrl'] ?? $holder->id_front_url;
            $holder->id_back_url              = $documents['idBackUrl'] ?? $holder->id_back_url;
            $holder->address_verification_url = $documents['addressVerificationUrl'] ?? $holder->address_verification_url;
        }

        // Address
        $address = $data['billingAddress'] ?? $data['address'] ?? [];
        if (!empty($address)) {
            $holder->address_line1 = $address['line1'] ?? $address['line_1'] ?? $holder->address_line1;
            $holder->address_line2 = $address['line2'] ?? $address['line_2'] ?? $holder->address_line2;
            $holder->city          = $address['city'] ?? $holder->city;
            $holder->state         = $address['state'] ?? $holder->state;
            $holder->postal_code   = $address['postalCode'] ?? $address['postal_code'] ?? $holder->postal_code;
            $holder->country       = $address['country'] ?? $holder->country;
        }

        $holder->save();

        // Store provider-specific mapping
        $holder->setProviderCustomer($provider, [
            'customer_id'  => $data['_id'] ?? $data['id'] ?? null,
            'business_id'  => $data['business'] ?? null,
            'status'       => $data['status'] ?? 'active',
            'is_approved'  => $data['isApproved'] ?? $data['is_approved'] ?? false,
            'synced_at'    => now()->toISOString(),
        ]);

        // Optionally keep last raw response per provider
        $raw = $holder->raw_responses ?? [];
        $raw[$provider] = $response;
        $holder->raw_responses = $raw;
        $holder->save();

        return $holder;
    }

    /**
     * Convenience wrapper for Sudo
     */
    public static function syncFromSudo(int $userId, array $response): self
    {
        return self::syncFromProvider($userId, 'sudo', $response);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal
    |--------------------------------------------------------------------------
    */

    protected static function normalizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = str_replace('/', '-', $date);

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
 * Update holder fields and optionally update/merge a provider mapping.
 */
public function updateHolder(array $payload, ?string $provider = null, array $providerData = []): self
{
    // Only allow known fillable fields
    $allowed = array_intersect_key(
        $payload,
        array_flip($this->getFillable())
    );

    // Never allow changing ownership
    unset($allowed['user_id']);

    if (!empty($allowed)) {
        $this->fill($allowed);
        $this->save();
    }

    // Optionally update / merge provider JSON
    if ($provider !== null) {
        $provider = strtolower(trim($provider));

        $this->setProviderCustomer($provider, array_merge(
            [
                'synced_at' => now()->toISOString(),
            ],
            $providerData
        ));
    }

    return $this->fresh();
}
}