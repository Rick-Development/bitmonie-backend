<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'name',
        'description',
        'requirements',
        'vform_id',
        'status'
    ];

    protected $casts = [
        'level' => 'integer',
        'status' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
