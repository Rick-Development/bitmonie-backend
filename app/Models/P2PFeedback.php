<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PFeedback extends Model
{
    use HasFactory;

    protected $table = 'p2p_feedbacks';

    protected $fillable = [
        'order_id',
        'from_user_id',
        'to_user_id',
        'rating',
        'comment',
    ];

    public function order()
    {
        return $this->belongsTo(P2POrder::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
