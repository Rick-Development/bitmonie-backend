<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EduSave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'amount',
        'period',
        'start_date',
        'graduation_date',
        'next_payout_date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'start_date' => 'date',
        'graduation_date' => 'date',
        'next_payout_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
