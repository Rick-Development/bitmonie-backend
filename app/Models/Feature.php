<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * Promo-feature assignments.
     */
    public function promoFeatures(): HasMany
    {
        return $this->hasMany(
            PromoFeature::class,
            'feature_id'
        );
    }

    /**
     * Promos this feature has been assigned to.
     */
    public function promos(): BelongsToMany
    {
        return $this->belongsToMany(
            Promo::class,
            'promo_features',
            'feature_id',
            'promo_id'
        )->withTimestamps();
    }
}