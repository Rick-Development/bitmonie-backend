<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoFeature extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'promo_features';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'promo_id',
        'feature_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'promo_id' => 'integer',
        'feature_id' => 'integer',
    ];

    /**
     * Promo associated with this assignment.
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(
            Promo::class,
            'promo_id'
        );
    }

    /**
     * Feature associated with this assignment.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(
            Feature::class,
            'feature_id'
        );
    }
}