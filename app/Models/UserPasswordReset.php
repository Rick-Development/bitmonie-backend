<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPasswordReset extends Model
{
    use HasFactory;
    protected $guarded = [
        'id',
    ];

    protected $casts =[
        'email'      => 'string',
        'code'       => 'integer',
        'token '     => 'string',
        'user_id  '  => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class)->select('id','username','email','firstname','lastname');
    }
}
