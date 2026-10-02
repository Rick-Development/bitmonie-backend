<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTransaction extends Model
{
    use HasFactory;
    protected $fillable = ['user_wallet_id','type','amount','balance_after','reference','metadata'];
    protected $casts = ['metadata'=>'array','amount'=>'string','balance_after'=>'string'];
    public function wallet() { return $this->belongsTo(UserWallet::class, 'user_wallet_id'); }
}
