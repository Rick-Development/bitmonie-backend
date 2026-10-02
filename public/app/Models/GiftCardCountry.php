<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardCountry extends Model
{
    protected $primaryKey = 'iso_name';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['iso_name', 'name', 'currency_code', 'flag_url', 'status'];

    protected $casts = [
        'status' => 'boolean'
    ];
}
