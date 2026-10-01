<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BushaPaymentDetail extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'recipient_id',
        'currency',
        'type',
        'is_default',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
