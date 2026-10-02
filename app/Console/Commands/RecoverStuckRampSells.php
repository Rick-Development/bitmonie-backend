<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRampSell;
use App\Models\RampTransaction;
use App\Services\QuidaxRampService;
use App\Services\RampTransactionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecoverStuckRampSells extends Command
{
    protected $signature = 'ramp:recover-stuck-sells
        {--minutes=5 : Minimum transaction age in minutes before recovery}
        {--limit=50 : Maximum transactions to inspect}
        {--dry-run : Log what would be recovered without dispatching jobs}';

    protected $description = 'Recover off-ramp sell transactions stuck between crypto movement and fiat payout.';

    public function handle(QuidaxRampService $rampService, RampTransactionSyncService $syncService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($minutes);

        $summary = [
            'scanned' => 0,
            'synced' => 0,
            'completed' => 0,
            'failed' => 0,
            'redispatched' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
        ];

        RampTransaction::with('user')
            ->where('type', 'off_ramp')
            ->whereIn('status', ['processing', 'awaiting_payout'])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (RampTransaction $transaction) use ($rampService, $syncService, $dryRun, &$summary) {
                $summary['scanned']++;

                $providerResponse = $rampService->offRampTransaction($transaction->merchant_reference);
                $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : null;

                if ($providerData) {
                    $transaction = $syncService->syncOffRamp($transaction, $providerData);
                    $summary['synced']++;

                    if ($transaction->status === 'completed') {
                        $summary['completed']++;
                        return;
                    }

                    if ($transaction->status === 'failed') {
                        $summary['failed']++;
                        return;
                    }
                }

                if (!$this->needsRampWithdrawalRetry($transaction)) {
                    $summary['skipped']++;
                    return;
                }

                if ($dryRun) {
                    $summary['redispatched']++;
                    return;
                }

                $this->dispatchRampSellRetry($transaction);
                $summary['redispatched']++;
            });

        Log::info('Stuck ramp sell recovery completed.', $summary);
        $this->info(json_encode($summary));

        return Command::SUCCESS;
    }

    protected function needsRampWithdrawalRetry(RampTransaction $transaction): bool
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $job = is_array($metadata['job'] ?? null) ? $metadata['job'] : [];
        $mainWithdrawalStatus = strtolower((string) data_get($job, 'main_account_withdrawal.data.status'));
        $hasRampWithdrawal = is_array($job['ramp_withdrawal'] ?? null);

        return !$hasRampWithdrawal
            && in_array(strtolower((string) $transaction->status), ['processing', 'awaiting_payout'], true)
            && in_array($mainWithdrawalStatus, ['done', 'completed', 'success', 'successful'], true)
            && $transaction->user !== null
            && !empty($transaction->wallet_address);
    }

    protected function dispatchRampSellRetry(RampTransaction $transaction): void
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $job = is_array($metadata['job'] ?? null) ? $metadata['job'] : [];
        $fees = is_array($metadata['fees'] ?? null) ? $metadata['fees'] : [];

        $mainAccountData = [
            'currency' => strtolower((string) $transaction->from_currency),
            'network' => strtolower((string) $transaction->network),
            'amount' => (string) data_get($job, 'main_account_withdrawal.data.amount', $fees['total'] ?? $transaction->from_amount),
            'fund_uid' => 'me',
            'transaction_note' => 'Retry off-ramp move to main account: ' . $transaction->merchant_reference,
            'narration' => 'Retry off-ramp move to main account: ' . $transaction->merchant_reference,
        ];

        ProcessRampSell::dispatch(
            $transaction->user,
            $transaction->merchant_reference,
            $mainAccountData,
            strtolower((string) $transaction->from_currency),
            (string) $transaction->from_amount,
            (string) $transaction->wallet_address,
            strtolower((string) $transaction->network)
        );
    }
}
