<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppOnboardScreens extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    protected $appends = [
        'editData',
    ];

    protected $casts = [
        'heading'        => 'object',
        'title'        => 'object',
        'details'        => 'object',
        'image'        => 'string',
        'status'       => 'integer',
        'last_edit_by' => 'integer',
        'created_at'   => 'date:Y-m-d',
        'updated_at'   => 'date:Y-m-d',
    ];


    public function getEditDataAttribute() {
        $data = [
            'heading'    => $this->heading,
            'title'      => $this->title,
            'details'    => $this->details,
            'image'      => $this->image,
            'status'     => $this->status,
            'target'     => $this->id, 
        ];

        return json_encode($data);
    }
}
