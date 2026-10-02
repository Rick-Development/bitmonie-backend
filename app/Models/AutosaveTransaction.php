<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutosaveTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'autosave_plan_id',
        'user_id',
        'user_wallet_id',
        'source_order_transaction_id',
        'type',
        'status',
        'amount',
        'source_amount',
        'percentage',
        'reference',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'autosave_plan_id' => 'integer',
        'user_id' => 'integer',
        'user_wallet_id' => 'integer',
        'source_order_transaction_id' => 'integer',
        'amount' => 'decimal:8',
        'source_amount' => 'decimal:8',
        'percentage' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(AutosavePlan::class, 'autosave_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function sourceOrderTransaction()
    {
        return $this->belongsTo(OrderTransaction::class, 'source_order_transaction_id');
    }
}
