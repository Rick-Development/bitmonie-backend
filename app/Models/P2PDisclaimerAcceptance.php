<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PDisclaimerAcceptance extends Model
{
    use HasFactory;

    protected $table = 'p2p_disclaimer_acceptances';
    
    protected $fillable = [
        'user_id',
        'disclaimer_id',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function disclaimer()
    {
        return $this->belongsTo(P2PDisclaimer::class, 'disclaimer_id');
    }
}
