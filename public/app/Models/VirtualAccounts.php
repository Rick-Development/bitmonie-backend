<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccounts extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'customer',
        'status',
        'account_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'currency',
        'account_type',
        'provider',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
