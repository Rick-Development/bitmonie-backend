<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDevice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $casts        = [
        'id'                => 'integer',
        'transaction_id'    => 'integer',
        'city'              => 'string',
        'country'           => 'string',
        'longitude'         => 'string',
        'latitude'          => 'string',
        'browser'           => 'string',
        'os'                => 'string',
        'timezone'          => 'string',
    ];
}
