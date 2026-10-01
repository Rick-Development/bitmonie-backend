<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsdtEasyearnInterestCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_id',
        'amount',
        'credit_date',
        'credited_by',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'credit_date' => 'date',
    ];

    // Relationships
    
    public function investment()
    {
        return $this->belongsTo(UsdtEasyearnInvestment::class, 'investment_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'credited_by');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
