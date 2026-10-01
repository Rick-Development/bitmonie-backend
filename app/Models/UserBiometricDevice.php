<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBiometricDevice extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'device_name',
        'device_id',
        'sign_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
