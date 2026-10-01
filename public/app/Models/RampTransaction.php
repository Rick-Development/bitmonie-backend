<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RampTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'from_amount' => 'decimal:8',
        'to_amount' => 'decimal:8',
        'blockchain_fee' => 'decimal:8',
        'processor_fee' => 'decimal:8',
        'stamp_charge' => 'decimal:8',
        'vat' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
