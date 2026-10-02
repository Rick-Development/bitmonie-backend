<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FireBaseToken extends Model
{
    use HasFactory;

    protected $table = 'firebase_tokens';

    protected $fillable = [
        'tokenable_id',
        'tokenable_type',
        'token',
        'device_id',
    ];

    public function tokenable()
    {
        return $this->morphTo();
    }
}
