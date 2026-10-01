<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'p2p_payment_methods';
    
    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'details',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
