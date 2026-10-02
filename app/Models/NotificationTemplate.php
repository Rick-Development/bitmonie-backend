<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [

        'template_key',
        'notify_for',
        'language_id',

        'name',
        'subject',

        'email',
        'sms',
        'push',
        'in_app',

        'status',

        'email_from',

        'is_transactional',
        'is_security',
    ];


    protected $casts = [

        'status' => 'array',

        'is_transactional' => 'boolean',

        'is_security' => 'boolean',

    ];
}