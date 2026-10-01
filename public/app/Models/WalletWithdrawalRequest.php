<?php

namespace App\Models;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_ledger_id',
        'reviewed_by',
        'reference',
        'amount',
        'currency_code',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'admin_note',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(WalletLedger::class, 'wallet_ledger_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
