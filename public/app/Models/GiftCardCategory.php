<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardCategory extends Model
{
    protected $fillable = ['id', 'name', 'status'];
    
    protected $casts = [
        'status' => 'boolean'
    ];
}
