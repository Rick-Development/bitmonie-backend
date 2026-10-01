<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VasServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'vas_service_id',
        'name',
        'identifier',
        'status',
    ];

    public function service()
    {
        return $this->belongsTo(VasService::class, 'vas_service_id');
    }
}
