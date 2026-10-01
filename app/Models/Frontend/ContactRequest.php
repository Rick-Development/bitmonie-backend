<?php

namespace App\Models\Frontend;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    protected $casts    = [
        'id'            => 'integer',
        'name'          => 'string',
        'email'         => 'string',
        'message'       => 'string',
        'reply'         => 'integer'
    ];
}
