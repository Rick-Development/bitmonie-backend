<?php

namespace App\Console\Commands;

use App\Models\WebhookEventLog;
use App\Models\Withdrawals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepairQuidaxWithdrawalHashAddress extends Command
{
    protected $signature = 'withdrawals:repair-quidax-hash
        {--reference= : Repair one withdrawal reference}
        {--currency= : Limit to one currency, e.g. usdt}
        {--dry-run : Show what would change without updating rows}';

    protected $description = 'Backfill Quidax withdrawal transaction hash and recipient address from stored webhook logs.';

    public function handle(): int
    {
        $reference = trim((string) $this->option('reference'));
        $currency = strtolower(trim((string) $this->option('currency')));
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'scanned' => 0,
            'matched_webhook' => 0,
            'updated' => 0,
            'already_complete' => 0,
            'missing_webhook' => 0,
            'missing_hash_and_address' => 0,
            'dry_run' => $dryRun ? 1 : 0,
        ];

        $query = Withdrawals::query()->latest('id');

        if ($reference !== '') {
            $query->where('reference', $reference);
        }

        if ($currency !== '') {
            $query->where('currency', $currency);
        }

        $query->chunkById(100, function ($withdrawals) use (&$stats, $dryRun) {
            foreach ($withdrawals as $withdrawal) {
                $stats['scanned']++;

                $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
                $recipientData = is_array($withdrawal->recipient_data) ? $withdrawal->recipient_data : [];

                if (($walletMeta['reservation_type'] ?? null) !== 'crypto_withdrawal') {
                    continue;
                }

                $existingHash = $this->validStoredTransactionHash($walletMeta['transaction_hash'] ?? null)
                    ?? $this->validStoredTransactionHash($walletMeta['txid'] ?? null)
                    ?? $this->validStoredTransactionHash($withdrawal->trans_id ?? null, [
                        $walletMeta['provider_withdrawal_id'] ?? null,
                        $withdrawal->reference ?? null,
                    ]);
                $existingAddress = $this->extractRecipientAddress($recipientData)
                    ?? $this->extractRecipientAddress($walletMeta);

                if ($existingHash && $existingAddress) {
                    $stats['already_complete']++;
                    continue;
                }

                $event = $this->findMatchingWebhook($withdrawal);
                if (!$event) {
                    $stats['missing_webhook']++;
                    continue;
                }

                $stats['matched_webhook']++;

                $payload = is_array($event->payload) ? $event->payload : json_decode((string) $event->raw_payload, true);
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);
                $hash = $this->extractTransactionHash($data) ?? $existingHash;
                $address = $this->extractRecipientAddress($data) ?? $existingAddress;
                $network = $this->extractNetwork($data)
                    ?? $this->extractNetwork($recipientData)
                    ?? $this->extractNetwork($walletMeta);

                if (!$hash && !$address) {
                    $stats['missing_hash_and_address']++;
                    continue;
                }

                if ($hash) {
                    $walletMeta['transaction_hash'] = $hash;
                    $walletMeta['txid'] = $hash;
                }

                if ($address) {
                    data_set($recipientData, 'details.address', $address);
                    $recipientData['address'] = $recipientData['address'] ?? $address;
                    $walletMeta['recipient_address'] = $address;
                }

                if ($network) {
                    data_set($recipientData, 'details.network', strtolower((string) $network));
                    $walletMeta['network'] = strtolower((string) $network);
                }

                $updates = [
                    'recipient_data' => $recipientData,
                    'wallet' => $walletMeta,
                ];

                if ($hash && strtolower((string) ($walletMeta['status'] ?? '')) === 'completed') {
                    $updates['trans_id'] = $hash;
                }

                if (!$dryRun) {
                    $withdrawal->update($updates);
                }

                $stats['updated']++;

                Log::info('Quidax withdrawal hash/address repaired from webhook log.', [
                    'dry_run' => $dryRun,
                    'withdrawal_id' => $withdrawal->id,
                    'reference' => $withdrawal->reference,
                    'webhook_event_log_id' => $event->id,
                    'transaction_hash' => $hash,
                    'recipient_address' => $address,
                ]);
            }
        });

        $this->info(($dryRun ? 'Dry-run complete.' : 'Repair complete.') . ' Quidax withdrawal hash/address summary:');
        foreach ($stats as $key => $value) {
            $this->line($key . ': ' . $value);
        }

        return self::SUCCESS;
    }

    protected function findMatchingWebhook(Withdrawals $withdrawal): ?WebhookEventLog
    {
        $reference = (string) $withdrawal->reference;
        $transId = (string) $withdrawal->trans_id;

        return WebhookEventLog::query()
            ->where('provider', 'quidax')
            ->where(function ($query) use ($reference, $transId) {
                if ($reference !== '') {
                    $query->orWhere('transaction_reference', $reference)
                        ->orWhere('raw_payload', 'like', '%' . $reference . '%');
                }

                if ($transId !== '' && !str_contains($transId, ':')) {
                    $query->orWhere('transaction_reference', $transId)
                        ->orWhere('raw_payload', 'like', '%' . $transId . '%');
                }
            })
            ->latest('id')
            ->first();
    }

    protected function extractTransactionHash(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'txid',
            'tx_id',
            'transaction_hash',
            'hash',
            'blockchain_transaction_hash',
            'blockchain_txid',
            'transaction.hash',
            'transaction.txid',
            'withdrawal.txid',
            'withdrawal.transaction_hash',
            'blockchain.txid',
            'blockchain.transaction_hash',
            'data.txid',
            'data.transaction_hash',
        ]);
    }

    protected function extractRecipientAddress(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'fund_uid',
            'address',
            'recipient_address',
            'destination_address',
            'to_address',
            'wallet_address',
            'recipient.address',
            'recipient.details.address',
            'recipient.data.address',
            'destination.address',
            'destination.details.address',
            'details.address',
            'data.fund_uid',
            'data.recipient.details.address',
        ]);
    }

    protected function extractNetwork(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'network',
            'blockchain',
            'chain',
            'recipient.network',
            'recipient.details.network',
            'destination.network',
            'details.network',
            'data.network',
        ]);
    }

    protected function firstDataValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '' && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function validStoredTransactionHash($value, array $rejectValues = []): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'unknown') {
            return null;
        }

        foreach ($rejectValues as $rejectValue) {
            if ($rejectValue !== null && strtolower($value) === strtolower(trim((string) $rejectValue))) {
                return null;
            }
        }

        foreach (['failed:', 'pending:', 'processing:', 'initiated:', 'cancelled:', 'canceled:'] as $prefix) {
            if (str_starts_with(strtolower($value), $prefix)) {
                return null;
            }
        }

        return $value;
    }
}
