<?php

namespace App\Console\Commands;

use App\Constants\PaymentGatewayConst;
use App\Models\OrderTransaction;
use App\Models\RampTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportJune2026Transactions extends Command
{
    protected $signature = 'transactions:export-june-2026
        {--from=2026-06-01 00:00:00}
        {--to=}
        {--dir=}
        {--source=all : all|transactions|orders|ramps}
        {--usd-rates= : Comma-separated currency:rate map, e.g. NGN:0.0006667,USDT:1,USDC:1}';

    protected $description = 'Export successful June 2026 transactions to CSV, SQL, and summary JSON.';

    protected array $summary = [
        'total_successful_transactions' => 0,
        'volume_by_currency' => [],
        'count_by_source' => [],
        'volume_by_source_currency' => [],
    ];

    public function handle(): int
    {
        $from = Carbon::parse((string) $this->option('from'))->startOfSecond();
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->endOfSecond() : now();
        $source = strtolower((string) $this->option('source'));

        if (!in_array($source, ['all', 'transactions', 'orders', 'ramps'], true)) {
            $this->error('Invalid --source. Use all, transactions, orders, or ramps.');
            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: storage_path('app/exports');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd_His');
        $base = $dir . DIRECTORY_SEPARATOR . "june_2026_successful_transactions_{$stamp}";
        $csvPath = $base . '.csv';
        $sqlPath = $base . '.sql';
        $summaryPath = $base . '_summary.json';

        $csv = fopen($csvPath, 'wb');
        $sql = fopen($sqlPath, 'wb');

        if (!$csv || !$sql) {
            $this->error('Unable to open export files for writing.');
            return self::FAILURE;
        }

        $headers = [
            'source_table',
            'source_id',
            'transaction_id_reference',
            'amount',
            'currency',
            'status',
            'transaction_date_time',
            'user_sender_id',
            'recipient_id',
            'transaction_type',
            'direction',
        ];

        fputcsv($csv, $headers);
        fwrite($sql, $this->sqlHeader());

        if (in_array($source, ['all', 'transactions'], true)) {
            $this->exportTransactions($from, $to, $csv, $sql);
        }

        if (in_array($source, ['all', 'orders'], true)) {
            $this->exportOrderTransactions($from, $to, $csv, $sql);
        }

        if (in_array($source, ['all', 'ramps'], true)) {
            $this->exportRampTransactions($from, $to, $csv, $sql);
        }

        fclose($csv);
        fclose($sql);

        $summary = [
            'date_range' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ],
            'files' => [
                'csv' => $csvPath,
                'sql' => $sqlPath,
            ],
        ] + $this->summary + [
            'usd_equivalent' => $this->usdEquivalentSummary((string) $this->option('usd-rates')),
        ];

        file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT));

        $this->info(json_encode([
            'csv' => $csvPath,
            'sql' => $sqlPath,
            'summary' => $summaryPath,
            'total_successful_transactions' => $this->summary['total_successful_transactions'],
            'volume_by_currency' => $this->summary['volume_by_currency'],
        ], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    protected function exportTransactions(Carbon $from, Carbon $to, $csv, $sql): void
    {
        Transaction::where('status', PaymentGatewayConst::STATUSSUCCESS)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use ($csv, $sql) {
                foreach ($transactions as $transaction) {
                    $this->writeRow($csv, $sql, [
                        'source_table' => 'transactions',
                        'source_id' => $transaction->id,
                        'transaction_id_reference' => $transaction->trx_id,
                        'amount' => (string) $transaction->request_amount,
                        'currency' => strtoupper((string) $transaction->request_currency),
                        'status' => 'successful',
                        'transaction_date_time' => optional($transaction->created_at)->toDateTimeString(),
                        'user_sender_id' => $transaction->user_id,
                        'recipient_id' => $transaction->receiver_id,
                        'transaction_type' => $transaction->type,
                        'direction' => $transaction->attribute,
                    ]);
                }
            });
    }

    protected function exportOrderTransactions(Carbon $from, Carbon $to, $csv, $sql): void
    {
        OrderTransaction::with('wallet')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use ($csv, $sql) {
                foreach ($transactions as $transaction) {
                    $metadata = $transaction->metadata ?? [];
                    $status = strtolower((string) data_get($metadata, 'status', 'successful'));

                    if (in_array($status, ['failed', 'failure', 'reversed', 'cancelled', 'canceled', 'rejected'], true)) {
                        continue;
                    }

                    $wallet = $transaction->wallet;

                    $this->writeRow($csv, $sql, [
                        'source_table' => 'order_transactions',
                        'source_id' => $transaction->id,
                        'transaction_id_reference' => $transaction->reference,
                        'amount' => (string) $transaction->amount,
                        'currency' => strtoupper((string) ($wallet?->currency_code ?: data_get($metadata, 'currency', ''))),
                        'status' => $status ?: 'successful',
                        'transaction_date_time' => optional($transaction->created_at)->toDateTimeString(),
                        'user_sender_id' => $wallet?->user_id,
                        'recipient_id' => data_get($metadata, 'recipient_id') ?: data_get($metadata, 'receiver_id'),
                        'transaction_type' => data_get($metadata, 'source') ?: data_get($metadata, 'type') ?: data_get($metadata, 'bill_type'),
                        'direction' => $transaction->type,
                    ]);
                }
            });
    }

    protected function exportRampTransactions(Carbon $from, Carbon $to, $csv, $sql): void
    {
        RampTransaction::whereIn('status', ['completed', 'success', 'successful'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->chunkById(500, function ($transactions) use ($csv, $sql) {
                foreach ($transactions as $transaction) {
                    $isOffRamp = $transaction->type === 'off_ramp';
                    $amount = $isOffRamp ? $transaction->to_amount : $transaction->from_amount;
                    $currency = $isOffRamp ? $transaction->to_currency : $transaction->from_currency;

                    $this->writeRow($csv, $sql, [
                        'source_table' => 'ramp_transactions',
                        'source_id' => $transaction->id,
                        'transaction_id_reference' => $transaction->merchant_reference ?: $transaction->reference ?: $transaction->public_id,
                        'amount' => (string) $amount,
                        'currency' => strtoupper((string) $currency),
                        'status' => 'completed',
                        'transaction_date_time' => optional($transaction->created_at)->toDateTimeString(),
                        'user_sender_id' => $transaction->user_id,
                        'recipient_id' => null,
                        'transaction_type' => $transaction->type,
                        'direction' => $isOffRamp ? 'sell' : 'buy',
                    ]);
                }
            });
    }

    protected function writeRow($csv, $sql, array $row): void
    {
        $row['currency'] = $row['currency'] ?: 'UNKNOWN';

        fputcsv($csv, $row);
        fwrite($sql, $this->insertStatement($row));

        $amount = (float) $row['amount'];
        $currency = $row['currency'];
        $source = $row['source_table'];

        $this->summary['total_successful_transactions']++;
        $this->summary['volume_by_currency'][$currency] = ($this->summary['volume_by_currency'][$currency] ?? 0) + $amount;
        $this->summary['count_by_source'][$source] = ($this->summary['count_by_source'][$source] ?? 0) + 1;
        $this->summary['volume_by_source_currency'][$source][$currency] = ($this->summary['volume_by_source_currency'][$source][$currency] ?? 0) + $amount;
    }

    protected function sqlHeader(): string
    {
        return <<<SQL
CREATE TABLE IF NOT EXISTS june_2026_successful_transactions_export (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_table VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  transaction_id_reference VARCHAR(255) NULL,
  amount DECIMAL(36, 8) NULL,
  currency VARCHAR(20) NULL,
  status VARCHAR(80) NULL,
  transaction_date_time DATETIME NULL,
  user_sender_id BIGINT UNSIGNED NULL,
  recipient_id VARCHAR(255) NULL,
  transaction_type VARCHAR(120) NULL,
  direction VARCHAR(80) NULL
);

SQL;
    }

    protected function insertStatement(array $row): string
    {
        $columns = array_keys($row);
        $values = array_map(fn ($value) => $this->sqlValue($value), array_values($row));

        return 'INSERT INTO june_2026_successful_transactions_export (`'
            . implode('`, `', $columns)
            . '`) VALUES ('
            . implode(', ', $values)
            . ");\n";
    }

    protected function sqlValue($value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string) $value) . "'";
    }

    protected function usdEquivalentSummary(string $ratesOption): array
    {
        $rates = $this->parseUsdRates($ratesOption);
        $byCurrency = [];
        $total = 0.0;
        $missing = [];

        foreach ($this->summary['volume_by_currency'] as $currency => $volume) {
            if (!array_key_exists($currency, $rates)) {
                $missing[] = $currency;
                continue;
            }

            $usd = (float) $volume * (float) $rates[$currency];
            $byCurrency[$currency] = $usd;
            $total += $usd;
        }

        return [
            'rates_used' => $rates,
            'by_currency' => $byCurrency,
            'total_usd_equivalent' => $total,
            'meets_50000_usd_equivalent' => $total >= 50000,
            'missing_currency_rates' => $missing,
            'note' => $missing
                ? 'Provide missing currency rates with --usd-rates to complete the $50,000 equivalent check.'
                : 'USD equivalent computed from supplied rates.',
        ];
    }

    protected function parseUsdRates(string $ratesOption): array
    {
        $rates = [
            'USD' => 1.0,
            'USDT' => 1.0,
            'USDC' => 1.0,
        ];

        foreach (array_filter(array_map('trim', explode(',', $ratesOption))) as $pair) {
            [$currency, $rate] = array_pad(explode(':', $pair, 2), 2, null);

            if ($currency && is_numeric($rate)) {
                $rates[strtoupper(trim($currency))] = (float) $rate;
            }
        }

        return $rates;
    }
}
