<?php

namespace App\Models;

use App\Models\Admin\Admin;
use Illuminate\Support\Str;
use App\Constants\GlobalConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransactionMethod extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'slug' => 'string',
        'status' => 'integer',
    ];

    public function admin() {
        return $this->belongsTo(Admin::class,"last_edit_by","id");
    }

    public function getEditDataAttribute() {
        $data = [
            'name'      => $this->name,
            'slug'      => $this->slug,
        ];

        return json_encode($data);
    }

    public function scopeActive($query)
    {
        return $query->where('status', GlobalConst::ACTIVE);
    }

    public function isOwnBankTransfer() {
        $wallet_to_wallet_slug = Str::slug(GlobalConst::TRX_OWN_BANK_TRANSFER);
        if($this->slug == $wallet_to_wallet_slug) {
            return true;
        }
        return false;
    }

    public function isOtherBankTransfer() {
        $bank_slug = Str::slug(GlobalConst::TRX_OTHER_BANK_TRANSFER);
        if($this->slug == $bank_slug) {
            return true;
        }
        return false;
    }

    public function getReadableNameAttribute() {
        $divide_with_pipe = explode("|",$this->name);
        $name = $divide_with_pipe[0];
        if(isset($divide_with_pipe[1])) {
            $name = ucwords($divide_with_pipe[0]);
        }
        return $name;
    }
}
