<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only ledger row. Only the status may change (COMPLETED -> REVERSED). */
class SavingsEntry extends Model
{
    public const CREDIT = 'CREDIT';
    public const DEBIT = 'DEBIT';

    public const DEPOSIT = 'DEPOSIT';
    public const WITHDRAWAL = 'WITHDRAWAL';
    public const TRANSFER_IN = 'TRANSFER_IN';
    public const TRANSFER_OUT = 'TRANSFER_OUT';
    public const INTEREST = 'INTEREST';
    public const FEE = 'FEE';
    public const REVERSAL = 'REVERSAL';
    public const OPENING = 'OPENING';

    protected $table = 'savings_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'string',
        'balance_after' => 'string',
        'meta' => 'array',
    ];

    protected $hidden = ['request_hash', 'idempotency_key'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'account_id');
    }
}
