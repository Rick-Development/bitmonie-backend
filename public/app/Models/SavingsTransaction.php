<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsTransaction extends Model
{
    use HasFactory;

    protected $table = 'savings_transactions';

    protected $fillable = [
        'user_id',
        'savingsable_id',
        'savingsable_type',
        'amount',
        'balance_after',
        'type',
        'status',
        'source',
        'narration'
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Polymorphic relationship
    public function savingsable()
    {
        return $this->morphTo();
    }

    // Relationship with user
    public function savings()
    {
        return $this->belongsTo(Savings::class);
    }
}