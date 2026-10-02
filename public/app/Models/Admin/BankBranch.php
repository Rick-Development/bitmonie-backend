<?php

namespace App\Models\Admin;

use App\Models\Admin\BankList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankBranch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'admin_id'     => 'integer',
        'bank_list_id' => 'integer',
        'name'         => 'string',
        'alias'        => 'string',
        'status'       => 'integer',
    ];
    
    protected $appends = [
        'editData',
    ];
    public function getEditDataAttribute() {

        $data = [
            'id'           => $this->id,
            'bank_list_id' => $this->bank_list_id,
            'name'         => $this->name,
            'alias'        => $this->alias,
            'status'       => $this->status,
        ];

        return json_encode($data);
    }

    public function bank(){
        return $this->belongsTo(BankList::class, 'bank_list_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', false);
    }

    public function scopeSearch($query,$text) {
        $query->whereHas('bank', function($q) use ($text){
            $q->where("name","like","%".$text."%");
        })->orWhere("name","like","%".$text."%");
    }
}
