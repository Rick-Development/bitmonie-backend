<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderWallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'currency', 'balance', 'reserved'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
