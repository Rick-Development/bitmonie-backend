<?php

namespace App\Services\Savings;

use App\Models\SavingsEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/** Paginated, filterable ledger history. Always scoped to the owner. */
final class SavingsHistoryQuery
{
    public static function paginate(Request $request, int $userId, ?int $accountId = null, int $perPage = 20): LengthAwarePaginator
    {
        $q = SavingsEntry::where('user_id', $userId);

        if ($accountId !== null) {
            $q->where('account_id', $accountId);
        } elseif ($request->filled('account_id')) {
            $q->where('account_id', (int) $request->query('account_id'));
        }

        foreach (['type' => 'type', 'status' => 'status', 'direction' => 'direction'] as $param => $column) {
            if ($request->filled($param)) {
                $q->where($column, strtoupper((string) $request->query($param)));
            }
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', \Carbon\Carbon::parse($request->query('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', \Carbon\Carbon::parse($request->query('to'))->endOfDay());
        }

        return $q->orderByDesc('id')->paginate($perPage);
    }
}
