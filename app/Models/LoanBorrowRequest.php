<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanBorrowRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset',
        'amount',
        'duration_days',
        'collateral_asset',
        'collateral_amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'duration_days' => 'integer',
        'collateral_amount' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loan()
    {
        return $this->hasOne(Loan::class, 'borrow_request_id');
    }
}
