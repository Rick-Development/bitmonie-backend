<?php

namespace App\Console\Commands;

use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;

class AuditCardFundingAttempts extends Command
{
    protected $signature = 'card:audit-funding-attempts';
    protected $description = 'Audit recent virtual card top-up and funding attempts to check for debits and credits';

    public function handle()
    {
        $this->info('Auditing virtual card funding attempts...');

        // 1. Payscribe card topups in transactions
        $transactions = Transaction::where(function ($q) {
            $q->where('transactional_type', 'like', '%Card%')
              ->orWhere('remarks', 'like', '%card%')
              ->orWhere('remarks', 'like', '%topup%');
        })
        ->orderByDesc('id')
        ->limit(100)
        ->get();

        $this->info("Found {$transactions->count()} card transaction records in local database.");

        $this->table(
            ['ID', 'User ID', 'Amount', 'Currency', 'Type', 'Status', 'Ref ID', 'Created At'],
            $transactions->map(function ($t) {
                return [
                    $t->id,
                    $t->user_id,
                    $t->amount,
                    $t->currency,
                    $t->trx_type,
                    $t->transaction_status ?? $t->status,
                    $t->ref_id ?? $t->trx_id,
                    $t->created_at,
                ];
            })
        );

        // 2. Payscribe Virtual Card Transactions
        $cardTrx = PayscribeVirtualCardTransaction::orderByDesc('id')->limit(50)->get();
        $this->info("Found {$cardTrx->count()} card provider transaction logs.");
        $this->table(
            ['ID', 'User ID', 'Card ID', 'Amount', 'Trx Type', 'Ref', 'Created At'],
            $cardTrx->map(function ($c) {
                return [
                    $c->id,
                    $c->user_id,
                    $c->card_id,
                    $c->amount,
                    $c->trx_type,
                    $c->ref,
                    $c->created_at,
                ];
            })
        );

        $this->info('Audit completed.');
        return 0;
    }
}
