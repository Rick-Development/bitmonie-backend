<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class P2PDisclaimer extends Model
{
    use HasFactory;

    protected $table = 'p2p_disclaimers';
    
    protected $fillable = [
        'key',
        'title',
        'content',
        'type',
        'requires_acceptance',
        'is_active',
    ];

    protected $casts = [
        'requires_acceptance' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function acceptances()
    {
        return $this->hasMany(P2PDisclaimerAcceptance::class, 'disclaimer_id');
    }
}
