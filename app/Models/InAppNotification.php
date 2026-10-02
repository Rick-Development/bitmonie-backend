<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InAppNotification extends Model
{
    use SoftDeletes;


    protected $fillable = [

        'user_id',

        'template_key',

        'type',

        'title',

        'message',

        'action',

        'reference_type',

        'reference_id',

        'read_at',

        'delivered_at',

        'priority',

    ];



    protected $casts = [

        'action' => 'array',

        'read_at' => 'datetime',

        'delivered_at' => 'datetime',

    ];



    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */


    public function user()
    {
        return $this->belongsTo(User::class);
    }



    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */


    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }



    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }



    public function scopeLatestFirst($query)
    {
        return $query->latest();
    }



    public function scopeHighPriority($query)
    {
        return $query->whereIn(
            'priority',
            [
                'high',
                'urgent'
            ]
        );
    }



    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */


    public function markAsRead(): bool
    {
        return $this->update([
            'read_at'=>now()
        ]);
    }



    public function markAsDelivered(): bool
    {
        return $this->update([
            'delivered_at'=>now()
        ]);
    }
   
}