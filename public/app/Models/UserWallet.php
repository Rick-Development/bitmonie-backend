<?php

namespace App\Models;

use App\Constants\GlobalConst;
use App\Models\Admin\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserWallet extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $fillable = ['balance', 'reserved', 'status','user_id','currency_id','currency_code', 'quote_currency_code','created_at','updated_at'];

    protected $casts = [
        'id'                    => 'integer',
        'user_id'               => 'integer',
        'currency_id'           => 'integer',
        'quote_currency_code'   => 'integer',
        'balance'               => 'decimal:8',
        'reserved'              => 'decimal:8',
        'profit_balance'        => 'decimal:8',
        'status'                => 'boolean',
    ];

    public function scopeAuth($query) {
        return $query->where('user_id',auth()->user()->id);
    }

    public function scopeActive($query) {
        return $query->where("status",true);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function currency() {
        return $this->belongsTo(Currency::class);
    }

    public function scopeSender($query) {
        return $query->whereHas('currency',function($q) {
            $q->where("sender",GlobalConst::ACTIVE);
        });
    }

}
