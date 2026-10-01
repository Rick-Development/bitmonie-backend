<?php

namespace App\Console\Commands;

use App\Constants\PaymentGatewayConst;
use App\Models\GraphTransaction;
use App\Models\OrderTransaction;
use App\Models\RampTransaction;
use App\Models\Transaction;
use App\Services\SharedReferralCommissionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillSharedReferralCommissions extends Command
{
    protected $signature = 'referral:backfill-shared-commissions
        {--from= : Optional start date, e.g. 2026-06-01}
        {--to= : Optional end date, e.g. 2026-06-22}
        {--limit=1000 : Max records to scan per source}';

    protected $description = 'Backfill shared referral commission records from successful fee-generating transactions.';

    public function handle(SharedReferralCommissionService $service): int
    {
        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->endOfDay() : null;
        $limit = max(1, (int) $this->option('limit'));

        $summary = [
            'transactions_scanned' => 0,
            'order_transactions_scanned' => 0,
            'ramp_transactions_scanned' => 0,
            'graph_transactions_scanned' => 0,
            'commissions_created_or_existing' => 0,
        ];

        Transaction::where('status', PaymentGatewayConst::STATUSSUCCESS)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Transaction $transaction) use ($service, &$summary) {
                $summary['transactions_scanned']++;
                if ($service->captureFromTransaction($transaction)) {
                    $summary['commissions_created_or_existing']++;
                }
            });

        OrderTransaction::with('wallet.user')
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (OrderTransaction $transaction) use ($service, &$summary) {
                $summary['order_transactions_scanned']++;
                if ($service->captureFromOrderTransaction($transaction)) {
                    $summary['commissions_created_or_existing']++;
                }
            });

        RampTransaction::whereIn('status', ['completed', 'success', 'successful'])
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (RampTransaction $transaction) use ($service, &$summary) {
                $summary['ramp_transactions_scanned']++;
                if ($service->captureFromRampTransaction($transaction)) {
                    $summary['commissions_created_or_existing']++;
                }
            });

        GraphTransaction::whereIn('status', ['completed', 'success', 'successful'])
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (GraphTransaction $transaction) use ($service, &$summary) {
                $summary['graph_transactions_scanned']++;
                if ($service->captureFromGraphTransaction($transaction)) {
                    $summary['commissions_created_or_existing']++;
                }
            });

        $this->line(json_encode($summary, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
