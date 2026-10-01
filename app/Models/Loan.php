<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'borrow_request_id',
        'loan_offer_id',
        'borrower_id',
        'lender_id',
        'asset',
        'amount',
        'collateral_asset',
        'collateral_amount',
        'interest_rate',
        'total_interest',
        'start_date',
        'due_date',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'collateral_amount' => 'decimal:8',
        'interest_rate' => 'decimal:2',
        'total_interest' => 'decimal:8',
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function lender()
    {
        return $this->belongsTo(User::class, 'lender_id');
    }

    public function offer()
    {
        return $this->belongsTo(LoanOffer::class, 'loan_offer_id');
    }

    public function request()
    {
        return $this->belongsTo(LoanBorrowRequest::class, 'borrow_request_id');
    }
}
