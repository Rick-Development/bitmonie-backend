<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'firebase_token_id',
        'template_key',
        'title',
        'body',
        'status',
        'message_id',
        'error',
        'response',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Notification recipient.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Firebase token used to send the notification.
     */
    public function firebaseToken()
    {
        return $this->belongsTo(FireBaseToken::class, 'firebase_token_id');
    }

    /**
     * Scope: Sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Delivered notifications.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope: Read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    /**
     * Scope: Pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}