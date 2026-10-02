<?php

namespace App\Console\Commands;

use App\Models\RampTransaction;
use App\Models\Withdrawals;
use App\Services\QuidaxSpendableBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepairReservedBalance extends Command
{
    protected $signature = 'wallet:repair-reserved
        {--user_id= : Limit repair to one user ID}
        {--currency= : Limit repair to one currency, for example usdt}
        {--dry-run : Show what would be repaired without changing records}';

    protected $description = 'Repair stale, duplicate, or terminal crypto/off-ramp reservations so sellable balances are not falsely locked.';

    public function handle(QuidaxSpendableBalanceService $spendableBalanceService): int
    {
        $userId = $this->option('user_id');
        $currency = strtolower(trim((string) $this->option('currency')));
        $dryRun = (bool) $this->option('dry-run');
        $seenReservationKeys = [];
        $summary = [
            'scanned' => 0,
            'active' => 0,
            'released' => 0,
            'duplicates' => 0,
            'stale' => 0,
            'terminal' => 0,
            'invalid' => 0,
            'ramp_scanned' => 0,
            'ramp_active' => 0,
            'ramp_released' => 0,
            'ramp_awaiting_payout' => 0,
            'ramp_stale' => 0,
            'ramp_terminal' => 0,
            'ramp_invalid' => 0,
            'dry_run' => $dryRun ? 1 : 0,
        ];

        $query = Withdrawals::query()->orderBy('id');

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', $userId);
        }

        if ($currency !== '') {
            $query->whereRaw('LOWER(currency) = ?', [$currency]);
        }

        $query->chunkById(200, function ($withdrawals) use (
            $spendableBalanceService,
            $currency,
            $dryRun,
            &$seenReservationKeys,
            &$summary
        ) {
            foreach ($withdrawals as $withdrawal) {
                $decision = $spendableBalanceService->inspectCryptoWithdrawalReservation(
                    $withdrawal,
                    $currency !== '' ? $currency : null
                );

                if (!$decision['is_crypto_reservation']) {
                    continue;
                }

                $summary['scanned']++;

                if ($decision['active']) {
                    $reservationKey = $decision['reservation_key'];

                    if (isset($seenReservationKeys[$reservationKey])) {
                        $summary['duplicates']++;
                        $this->releaseReservation($withdrawal, 'duplicate_reservation', $dryRun);
                        $summary['released']++;
                        continue;
                    }

                    $seenReservationKeys[$reservationKey] = $withdrawal->id;
                    $summary['active']++;
                    continue;
                }

                if ($decision['reason'] === 'stale_active_reservation') {
                    $summary['stale']++;
                    $this->releaseReservation($withdrawal, $decision['reason'], $dryRun);
                    $summary['released']++;
                    continue;
                }

                if (in_array($decision['reason'], ['terminal_status', 'terminal_provider_status', 'terminal_user_status', 'terminal_trans_id', 'failure_marker'], true)) {
                    $summary['terminal']++;
                    $this->releaseReservation($withdrawal, $decision['reason'], $dryRun);
                    $summary['released']++;
                    continue;
                }

                if ($decision['reason'] === 'invalid_reservation_amount') {
                    $summary['invalid']++;
                    $this->releaseReservation($withdrawal, $decision['reason'], $dryRun);
                    $summary['released']++;
                }
            }
        });

        $rampQuery = RampTransaction::query()
            ->where('type', 'off_ramp')
            ->orderBy('id');

        if ($userId !== null && $userId !== '') {
            $rampQuery->where('user_id', $userId);
        }

        if ($currency !== '') {
            $rampQuery->whereRaw('LOWER(from_currency) = ?', [$currency]);
        }

        $rampQuery->chunkById(200, function ($transactions) use (
            $spendableBalanceService,
            $currency,
            $dryRun,
            &$summary
        ) {
            foreach ($transactions as $transaction) {
                $decision = $spendableBalanceService->inspectOffRampReservation(
                    $transaction,
                    $currency !== '' ? $currency : null
                );

                if (!$decision['is_off_ramp_reservation']) {
                    continue;
                }

                $summary['ramp_scanned']++;

                if ($decision['active']) {
                    $summary['ramp_active']++;
                    continue;
                }

                if ($decision['reason'] === 'awaiting_payout_released') {
                    $summary['ramp_awaiting_payout']++;
                    $this->releaseOffRampReservation($transaction, $decision['reason'], $dryRun);
                    $summary['ramp_released']++;
                    continue;
                }

                if ($decision['reason'] === 'stale_active_reservation') {
                    $summary['ramp_stale']++;
                    $this->releaseOffRampReservation($transaction, $decision['reason'], $dryRun);
                    $summary['ramp_released']++;
                    continue;
                }

                if (in_array($decision['reason'], ['terminal_status', 'terminal_provider_status'], true)) {
                    $summary['ramp_terminal']++;
                    $this->releaseOffRampReservation($transaction, $decision['reason'], $dryRun);
                    $summary['ramp_released']++;
                    continue;
                }

                if ($decision['reason'] === 'invalid_reservation_amount') {
                    $summary['ramp_invalid']++;
                    $this->releaseOffRampReservation($transaction, $decision['reason'], $dryRun);
                    $summary['ramp_released']++;
                }
            }
        });

        $this->info(($dryRun ? 'Dry-run complete.' : 'Repair complete.') . ' Reserved balance summary:');
        foreach ($summary as $key => $value) {
            $this->line("{$key}: {$value}");
        }

        Log::info('wallet:repair-reserved completed.', $summary + [
            'user_id' => $userId,
            'currency' => $currency !== '' ? $currency : null,
        ]);

        return self::SUCCESS;
    }

    protected function releaseReservation(Withdrawals $withdrawal, string $reason, bool $dryRun): void
    {
        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $userMeta = is_array($withdrawal->user) ? $withdrawal->user : [];
        $status = strtolower(trim((string) ($walletMeta['status'] ?? '')));
        $releaseStatus = $this->terminalStatus($status) ? $status : 'failed';
        $reference = (string) ($withdrawal->reference ?? $withdrawal->id);

        $walletMeta['status'] = $releaseStatus;
        $walletMeta['reservation_released_at'] = now()->toDateTimeString();
        $walletMeta['reservation_release_reason'] = $reason;

        if ($releaseStatus === 'failed' && empty($walletMeta['failure_reason'])) {
            $walletMeta['failure_reason'] = "Reservation released by repair command: {$reason}";
        }

        $userMeta['status'] = $releaseStatus;
        if ($releaseStatus === 'failed' && empty($userMeta['failure_reason'])) {
            $userMeta['failure_reason'] = "Reservation released by repair command: {$reason}";
        }

        $updates = [
            'wallet' => $walletMeta,
            'user' => $userMeta,
        ];

        $transId = strtolower(trim((string) $withdrawal->trans_id));
        if ($transId === '' || str_starts_with($transId, 'pending:') || str_starts_with($transId, 'processing:') || str_starts_with($transId, 'initiated:')) {
            $updates['trans_id'] = "{$releaseStatus}:{$reference}";
        }

        Log::warning('Crypto withdrawal reservation repaired.', [
            'dry_run' => $dryRun,
            'withdrawal_id' => $withdrawal->id,
            'user_id' => $withdrawal->user_id,
            'reference' => $withdrawal->reference,
            'currency' => $withdrawal->currency,
            'amount' => $withdrawal->amount,
            'total' => $withdrawal->total,
            'reason' => $reason,
            'new_status' => $releaseStatus,
        ]);

        if (!$dryRun) {
            $withdrawal->update($updates);
        }
    }

    protected function releaseOffRampReservation(RampTransaction $transaction, string $reason, bool $dryRun): void
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        if (!empty($metadata['reservation_released_at']) || !empty($metadata['reservation_release_reason'])) {
            return;
        }

        $metadata['reservation_released_at'] = now()->toDateTimeString();
        $metadata['reservation_release_reason'] = $reason;
        $metadata['reservation_repair_command_at'] = now()->toIso8601String();

        Log::warning('Off-ramp reservation repaired.', [
            'dry_run' => $dryRun,
            'ramp_transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'merchant_reference' => $transaction->merchant_reference,
            'currency' => $transaction->from_currency,
            'network' => $transaction->network,
            'amount' => $transaction->from_amount,
            'status' => $transaction->status,
            'reason' => $reason,
        ]);

        if (!$dryRun) {
            $transaction->update([
                'metadata' => $metadata,
            ]);
        }
    }

    protected function terminalStatus(string $status): bool
    {
        return in_array($status, [
            'completed',
            'complete',
            'done',
            'success',
            'successful',
            'failed',
            'rejected',
            'cancelled',
            'canceled',
            'reversed',
            'refunded',
            'expired',
            'error',
        ], true);
    }
}
