<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryDisbursementUser extends Model
{
    use HasFactory;

    protected $guarded      = ['id'];

    protected $casts        = [
        'id'                => 'integer',
        'company_name'      => 'string',
        'company_email'     => 'string',
        'company_username'  => 'string',
        'user_name'         => 'string',
        'user_email'        => 'string',
        'user_username'     => 'string',
        'amount'            => 'decimal:8'
    ];
}
