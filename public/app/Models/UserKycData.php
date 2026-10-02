<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserKycData extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'data'              => 'object',
        'youverify_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
