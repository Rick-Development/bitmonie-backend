<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promo extends Model
{
    use HasFactory;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'expiry_payment_percentage',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'expiry_payment_percentage' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Promo-feature assignments.
     */
    public function promoFeatures(): HasMany
    {
        return $this->hasMany(
            PromoFeature::class,
            'promo_id'
        );
    }

    /**
     * Features assigned to this promo.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            Feature::class,
            'promo_features',
            'promo_id',
            'feature_id'
        )->withTimestamps();
    }

    /**
     * Administrator who created the promo.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Administrator who last updated the promo.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /**
     * Administrator who deleted the promo.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'deleted_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the promo is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Determine whether the promo is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Determine whether the promo is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Determine whether the promo is currently within its
     * configured date range.
     */
    public function isWithinDateRange(): bool
    {
        $now = now();

        return $this->start_date->lte($now)
            && $this->end_date->gte($now);
    }

    /**
     * Determine whether the promo is currently valid.
     */
    public function isCurrentlyValid(): bool
    {
        return $this->isActive()
            && $this->isWithinDateRange();
    }

    /**
     * Determine whether the promo has expired.
     */
    public function hasExpired(): bool
    {
        return $this->end_date->lt(now());
    }

    /**
     * Determine whether the promo has not started yet.
     */
    public function hasNotStarted(): bool
    {
        return $this->start_date->gt(now());
    }
}