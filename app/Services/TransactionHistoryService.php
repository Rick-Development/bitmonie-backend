<?php

namespace App\Services;

use App\Constants\PaymentGatewayConst;
use App\Models\AutosaveTransaction;
use App\Models\GraphTransaction;
use App\Models\OrderTransaction;
use App\Models\RampTransaction;
use App\Models\SavingsTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

class TransactionHistoryService
{
    protected array $successfulStatuses = ['1', 'success', 'successful', 'completed', 'complete', 'done'];

    public function paginatedForUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->paginateRows($this->forUser($user, $filters), $filters, $perPage);
    }

    public function paginateRows(Collection $rows, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $page = max(1, (int) ($filters['page'] ?? Request::query('page', 1)));
        $perPage = max(1, min(100, $perPage));
        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => Request::url(),
                'query' => Request::query(),
            ]
        );
    }

    public function forUser(User $user, array $filters = []): Collection
    {
        $sources = $this->requestedSources($filters['source'] ?? 'all');
        $rows = collect();

        if (in_array('order_transactions', $sources, true)) {
            $rows = $rows->merge($this->orderTransactions($user, $filters));
        }

        if (in_array('transactions', $sources, true)) {
            $rows = $rows->merge($this->legacyTransactions($user, $filters));
        }

        if (in_array('ramp_transactions', $sources, true)) {
            $rows = $rows->merge($this->rampTransactions($user, $filters));
        }

        if (in_array('graph_transactions', $sources, true)) {
            $rows = $rows->merge($this->graphTransactions($user, $filters));
        }

        if (in_array('savings_transactions', $sources, true)) {
            $rows = $rows->merge($this->savingsTransactions($user, $filters));
        }

        if (in_array('autosave_transactions', $sources, true)) {
            $rows = $rows->merge($this->autosaveTransactions($user, $filters));
        }

        return $rows
            ->filter(fn (array $row) => $this->matchesFilters($row, $filters))
            ->sortByDesc('sort_timestamp')
            ->values()
            ->map(function (array $row) {
                unset($row['sort_timestamp']);
                return $row;
            });
    }

    public function summary(Collection $rows): array
    {
        return [
            'total_transactions' => $rows->count(),
            'successful_transactions' => $rows->filter(fn ($row) => $this->isSuccessful($row['status'] ?? null))->count(),
            'volume_by_currency' => $rows
                ->groupBy('currency')
                ->map(fn ($currencyRows) => number_format((float) $currencyRows->sum('amount'), 8, '.', ''))
                ->all(),
            'count_by_type' => $rows->groupBy('transaction_type')->map->count()->all(),
        ];
    }

    public function findForReceipt(User $user, string $source, string $identifier): ?array
    {
        $identifier = trim($identifier);
        $source = $this->normalizeSource($source);

        return $this->forUser($user, ['source' => $source])
            ->first(function (array $row) use ($identifier) {
                return (string) $row['source_id'] === $identifier
                    || (string) $row['transaction_id_reference'] === $identifier
                    || (string) ($row['transaction_hash'] ?? '') === $identifier;
            });
    }

    protected function orderTransactions(User $user, array $filters): Collection
    {
        $walletIds = $user->wallets()->pluck('id');

        if ($walletIds->isEmpty()) {
            return collect();
        }

        return OrderTransaction::with('wallet.currency')
            ->whereIn('user_wallet_id', $walletIds)
            ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
            ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
            ->latest()
            ->get()
            ->map(function (OrderTransaction $transaction) use ($user) {
                $metadata = $transaction->metadata ?? [];
                $wallet = $transaction->wallet;
                $currency = strtoupper((string) (
                    data_get($metadata, 'currency')
                    ?? data_get($metadata, 'currency_code')
                    ?? $wallet?->currency_code
                    ?? $wallet?->currency?->code
                    ?? 'NGN'
                ));

                return $this->row([
                    'source_table' => 'order_transactions',
                    'source_id' => $transaction->id,
                    'transaction_id_reference' => $transaction->reference ?: 'ORDER-' . $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => $currency,
                    'status' => data_get($metadata, 'status', 'successful'),
                    'transaction_date_time' => $transaction->created_at,
                    'user_sender_id' => $user->id,
                    'recipient_id' => data_get($metadata, 'recipient_id'),
                    'transaction_type' => data_get($metadata, 'savings_type') ?: data_get($metadata, 'bill_type') ?: data_get($metadata, 'source') ?: 'wallet',
                    'direction' => $transaction->type,
                    'fees' => data_get($metadata, 'fees.total') ?? data_get($metadata, 'total_fee') ?? data_get($metadata, 'fee') ?? data_get($metadata, 'platform_fee') ?? 0,
                    'balance_after' => $transaction->balance_after,
                    'wallet_address' => data_get($metadata, 'wallet_address') ?? data_get($metadata, 'recipient_address'),
                    'transaction_hash' => data_get($metadata, 'transaction_hash') ?? data_get($metadata, 'txid'),
                    'description' => data_get($metadata, 'narration') ?: data_get($metadata, 'description') ?: data_get($metadata, 'title'),
                    'details' => $metadata,
                ]);
            });
    }

    protected function legacyTransactions(User $user, array $filters): Collection
    {
        return Transaction::with(['user_wallet.currency'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
            ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
            ->latest()
            ->get()
            ->map(function (Transaction $transaction) use ($user) {
                $currency = strtoupper((string) (
                    $transaction->request_currency
                    ?: $transaction->payment_currency
                    ?: $transaction->user_wallet?->currency_code
                    ?: $transaction->user_wallet?->currency?->code
                    ?: 'NGN'
                ));

                return $this->row([
                    'source_table' => 'transactions',
                    'source_id' => $transaction->id,
                    'transaction_id_reference' => $transaction->trx_id ?: 'TRX-' . $transaction->id,
                    'amount' => $transaction->request_amount,
                    'currency' => $currency,
                    'status' => $transaction->stringStatus->value ?? $transaction->status,
                    'transaction_date_time' => $transaction->created_at,
                    'user_sender_id' => $transaction->user_id,
                    'recipient_id' => $transaction->receiver_id,
                    'transaction_type' => $transaction->type ?: 'transaction',
                    'direction' => (int) $transaction->user_id === (int) $user->id ? 'debit' : 'credit',
                    'fees' => $transaction->total_charge ?? 0,
                    'balance_after' => $transaction->available_balance,
                    'wallet_address' => data_get((array) $transaction->details, 'wallet_address'),
                    'transaction_hash' => data_get((array) $transaction->details, 'transaction_hash') ?? data_get((array) $transaction->details, 'txid'),
                    'description' => $transaction->remark,
                    'details' => $transaction->details,
                ]);
            });
    }

    protected function rampTransactions(User $user, array $filters): Collection
    {
        return RampTransaction::where('user_id', $user->id)
            ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
            ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
            ->latest()
            ->get()
            ->map(function (RampTransaction $transaction) use ($user) {
                $isOffRamp = $transaction->type === 'off_ramp';
                $metadata = $transaction->metadata ?? [];

                return $this->row([
                    'source_table' => 'ramp_transactions',
                    'source_id' => $transaction->id,
                    'transaction_id_reference' => $transaction->merchant_reference ?: $transaction->reference ?: 'RAMP-' . $transaction->id,
                    'amount' => $isOffRamp ? ($transaction->to_amount ?: $transaction->from_amount) : ($transaction->from_amount ?: $transaction->to_amount),
                    'currency' => strtoupper((string) ($isOffRamp ? ($transaction->to_currency ?: $transaction->from_currency) : ($transaction->from_currency ?: $transaction->to_currency))),
                    'status' => $transaction->status,
                    'transaction_date_time' => $transaction->created_at,
                    'user_sender_id' => $user->id,
                    'recipient_id' => null,
                    'transaction_type' => $transaction->type,
                    'direction' => $isOffRamp ? 'credit' : 'debit',
                    'fees' => bcadd((string) ($transaction->blockchain_fee ?? 0), bcadd((string) ($transaction->processor_fee ?? 0), (string) ($transaction->vat ?? 0), 8), 8),
                    'balance_after' => data_get($metadata, 'wallet_update.balance_after'),
                    'wallet_address' => $transaction->wallet_address,
                    'transaction_hash' => $transaction->transaction_hash ?? data_get($metadata, 'settlement.crypto_deposit.txid'),
                    'description' => $isOffRamp ? 'Crypto sell/off-ramp' : 'Crypto buy/on-ramp',
                    'details' => $metadata,
                ]);
            });
    }

    protected function graphTransactions(User $user, array $filters): Collection
    {
        return GraphTransaction::with('wallet')
            ->where('user_id', $user->id)
            ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
            ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
            ->latest()
            ->get()
            ->map(function (GraphTransaction $transaction) use ($user) {
                return $this->row([
                    'source_table' => 'graph_transactions',
                    'source_id' => $transaction->id,
                    'transaction_id_reference' => $transaction->reference ?: $transaction->transaction_id ?: 'GRAPH-' . $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => strtoupper((string) ($transaction->currency ?: $transaction->wallet?->currency ?: 'USD')),
                    'status' => $transaction->status,
                    'transaction_date_time' => $transaction->created_at,
                    'user_sender_id' => $user->id,
                    'recipient_id' => null,
                    'transaction_type' => 'usd_' . $transaction->type,
                    'direction' => $transaction->type === 'deposit' ? 'credit' : 'debit',
                    'fees' => data_get($transaction->metadata, 'fee') ?? data_get($transaction->metadata, 'fees.total') ?? 0,
                    'balance_after' => data_get($transaction->metadata, 'balance_after'),
                    'wallet_address' => data_get($transaction->metadata, 'address'),
                    'transaction_hash' => data_get($transaction->metadata, 'transaction_hash') ?? data_get($transaction->metadata, 'txid'),
                    'description' => $transaction->description,
                    'details' => $transaction->metadata,
                ]);
            });
    }

    protected function savingsTransactions(User $user, array $filters): Collection
    {
        return SavingsTransaction::where('user_id', $user->id)
            ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
            ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
            ->latest()
            ->get()
            ->map(function (SavingsTransaction $transaction) use ($user) {
                return $this->row([
                    'source_table' => 'savings_transactions',
                    'source_id' => $transaction->id,
                    'transaction_id_reference' => 'SAVINGS-' . $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => 'NGN',
                    'status' => $transaction->status ?: 'success',
                    'transaction_date_time' => $transaction->created_at,
                    'user_sender_id' => $user->id,
                    'recipient_id' => null,
                    'transaction_type' => $this->savingsType($transaction->savingsable_type, $transaction->type),
                    'direction' => in_array($transaction->type, ['withdrawal', 'interest'], true) ? 'credit' : 'debit',
                    'fees' => 0,
                    'balance_after' => $transaction->balance_after,
                    'wallet_address' => null,
                    'transaction_hash' => null,
                    'description' => $transaction->narration,
                    'details' => [
    'savings_type' => $this->savingsName($transaction->savingsable_type),
    'savings_id' => $transaction->savings_id,
    'source' => $transaction->source,
    'savingsable_id' => $transaction->savingsable_id,
    'type' => $transaction->type,
],
                ]);
            });
    }

    // protected function autosaveTransactions(User $user, array $filters): Collection
    // {
    //     return AutosaveTransaction::where('user_id', $user->id)
    //         ->when($this->from($filters), fn ($query, $date) => $query->where('created_at', '>=', $date))
    //         ->when($this->to($filters), fn ($query, $date) => $query->where('created_at', '<=', $date))
    //         ->latest()
    //         ->get()
    //         ->map(function (AutosaveTransaction $transaction) use ($user) {
    //             return $this->row([
    //                 'source_table' => 'autosave_transactions',
    //                 'source_id' => $transaction->id,
    //                 'transaction_id_reference' => $transaction->reference ?: 'AUTOSAVE-' . $transaction->id,
    //                 'amount' => $transaction->amount,
    //                 'currency' => 'NGN',
    //                 'status' => $transaction->status,
    //                 'transaction_date_time' => $transaction->created_at,
    //                 'user_sender_id' => $user->id,
    //                 'recipient_id' => null,
    //                 'transaction_type' => 'autosave_' . $transaction->type,
    //                 'direction' => 'debit',
    //                 'fees' => 0,
    //                 'balance_after' => data_get($transaction->metadata, 'target.balance_after'),
    //                 'wallet_address' => null,
    //                 'transaction_hash' => null,
    //                 'description' => $transaction->failure_reason ?: 'AutoSave deduction',
    //                 'details' => $transaction->metadata,
    //             ]);
    //         });
    // }
   

protected function autosaveTransactions(User $user, array $filters): Collection
{
    return AutosaveTransaction::where('user_id', $user->id)
        ->when(
            $this->from($filters),
            fn ($query, $date) => $query->where('created_at', '>=', $date)
        )
        ->when(
            $this->to($filters),
            fn ($query, $date) => $query->where('created_at', '<=', $date)
        )
        ->latest()
        ->get()
        ->map(function (AutosaveTransaction $transaction) use ($user) {
            $metadata = $transaction->metadata ?? [];

            $target = data_get($metadata, 'autosave_target');

            return $this->row([
                'source_table' => 'autosave_transactions',
                'source_id' => $transaction->id,
                'transaction_id_reference' => $transaction->reference
                    ?: 'AUTOSAVE-' . $transaction->id,

                'amount' => $transaction->amount,
                'currency' => 'NGN',
                'status' => $transaction->status,
                'transaction_date_time' => $transaction->created_at,

                'user_sender_id' => $user->id,
                'recipient_id' => null,

                'transaction_type' => 'autosave_' . $transaction->type,
                'direction' => 'debit',
                'fees' => 0,

                'balance_after' => data_get(
                    $metadata,
                    'autosave_target.balance_after'
                ),

                'wallet_address' => null,
                'transaction_hash' => null,

                'description' => $transaction->failure_reason ?: 'AutoSave',

                'details' => [
                    'source' => data_get($metadata, 'source'),
                    'autosave_plan_id' => data_get(
                        $metadata,
                        'autosave_plan_id'
                    ),
                    'autosave_type' => data_get(
                        $metadata,
                        'autosave_type'
                    ),

                    'autosave_target' => $target
    ? [
        'status' => data_get($target, 'status'),
      
        'target_id' => data_get($target, 'target_id'),
        'target_type' => data_get($target, 'target_type'),
        'balance_after' => data_get($target, 'balance_after'),
    ]
    : null,
                ],
            ]);
        });
}



    protected function row(array $data): array
    {
        $date = $data['transaction_date_time'] instanceof Carbon
            ? $data['transaction_date_time']
            : Carbon::parse($data['transaction_date_time']);

        $source = $this->normalizeSource((string) $data['source_table']);
        $sourceId = (string) $data['source_id'];

        return [
            'source_table' => $source,
            'source_id' => $sourceId,
            'transaction_id_reference' => (string) ($data['transaction_id_reference'] ?? $source . '-' . $sourceId),
            'amount' => number_format((float) ($data['amount'] ?? 0), 8, '.', ''),
            'currency' => strtoupper((string) ($data['currency'] ?? 'NGN')),
            'status' => $this->normalizeStatus($data['status'] ?? null),
            'transaction_date_time' => $date->toDateTimeString(),
            'user_sender_id' => $data['user_sender_id'] ?? null,
            'recipient_id' => $data['recipient_id'] ?? null,
            'transaction_type' => (string) ($data['transaction_type'] ?? 'transaction'),
            'direction' => strtolower((string) ($data['direction'] ?? 'debit')),
            'fees' => number_format((float) ($data['fees'] ?? 0), 8, '.', ''),
            'balance_after' => isset($data['balance_after']) ? number_format((float) $data['balance_after'], 8, '.', '') : null,
            'wallet_address' => $data['wallet_address'] ?? null,
            'transaction_hash' => $data['transaction_hash'] ?? null,
            'description' => $data['description'] ?? null,
            'receipt_endpoint' => url('/api/user/transactions/receipt/' . $source . '/' . rawurlencode($sourceId)),
            'details' => $data['details'] ?? null,
            'sort_timestamp' => $date->getTimestamp(),
        ];
    }

    protected function matchesFilters(array $row, array $filters): bool
    {
        $status = $filters['status'] ?? null;
        if ($status && $status !== '*' && $this->normalizeStatus($status) !== $row['status']) {
            return false;
        }

        $type = strtolower((string) ($filters['type'] ?? $filters['transaction_type'] ?? ''));
        if ($type !== '' && $type !== '*' && $type !== 'all') {
            $haystack = strtolower($row['transaction_type'] . ' ' . ($row['details']['source'] ?? '') . ' ' . ($row['details']['savings_type'] ?? ''));
            if (!str_contains($haystack, $type)) {
                return false;
            }
        }

        $currency = strtoupper((string) ($filters['currency'] ?? $filters['currency_code'] ?? ''));
        if ($currency !== '' && strtoupper($row['currency']) !== $currency) {
            return false;
        }

        $direction = strtolower((string) ($filters['direction'] ?? ''));
        if ($direction !== '' && $direction !== $row['direction']) {
            return false;
        }

        $search = strtolower(trim((string) ($filters['search'] ?? $filters['trx_id'] ?? $filters['reference'] ?? '')));
        if ($search !== '') {
            $target = strtolower(json_encode([
                $row['transaction_id_reference'],
                $row['source_id'],
                $row['transaction_hash'],
                $row['description'],
            ]));

            if (!str_contains($target, $search)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeStatus($status): string
    {
        if ((string) $status === (string) PaymentGatewayConst::STATUSSUCCESS) {
            return 'successful';
        }

        $status = strtolower(trim((string) $status));

        return match ($status) {
            'success', 'successful', 'completed', 'complete', 'done' => 'successful',
            'processing', 'queued', 'initiated', 'in_progress' => 'processing',
            'pending', '2', '' => 'pending',
            'failed', 'failure', 'error', 'rejected', '4' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            default => $status,
        };
    }

    protected function isSuccessful($status): bool
    {
        return in_array(strtolower((string) $this->normalizeStatus($status)), ['successful'], true);
    }

    protected function requestedSources($source): array
    {
        $source = $this->normalizeSource((string) $source);

        $all = [
            'order_transactions',
            'transactions',
            'ramp_transactions',
            'graph_transactions',
            'savings_transactions',
            'autosave_transactions',
        ];

        return in_array($source, ['all', '*', ''], true) ? $all : [$source];
    }

    protected function normalizeSource(string $source): string
    {
        return match (strtolower(trim($source))) {
            'orders', 'order', 'wallet', 'wallet_ledger' => 'order_transactions',
            'legacy', 'transaction' => 'transactions',
            'ramps', 'ramp' => 'ramp_transactions',
            'graph', 'usd' => 'graph_transactions',
            'savings', 'saving' => 'savings_transactions',
            'autosave' => 'autosave_transactions',
            default => strtolower(trim($source)) ?: 'all',
        };
    }

    protected function savingsType(?string $class, ?string $type): string
    {
        $base = match ($class) {
            'App\\Models\\FlexSavings' => 'flex_savings',
            'App\\Models\\SafeLock' => 'safe_lock',
            'App\\Models\\TargetSavings' => 'target_savings',
            'App\\Models\\EduSave' => 'edusave',
            default => 'savings',
        };

        return $base . '_' . ($type ?: 'transaction');
    }

    protected function from(array $filters): ?Carbon
    {
        $value = $filters['from_date'] ?? $filters['date_from'] ?? $filters['from'] ?? null;

        return $value ? Carbon::parse($value)->startOfDay() : null;
    }

    protected function to(array $filters): ?Carbon
    {
        $value = $filters['to_date'] ?? $filters['date_to'] ?? $filters['to'] ?? null;

        return $value ? Carbon::parse($value)->endOfDay() : null;
    }
    protected function savingsName(?string $class): string
{
    if (!$class) {
        return 'Savings';
    }

    $model = class_basename($class);

    return ucwords(
        preg_replace('/(?<!^)([A-Z])/', ' $1', $model)
    );
}
}
