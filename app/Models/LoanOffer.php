<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset',
        'amount',
        'remaining_amount',
        'min_interest_rate',
        'duration_days',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'remaining_amount' => 'decimal:8',
        'min_interest_rate' => 'decimal:2',
        'duration_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
