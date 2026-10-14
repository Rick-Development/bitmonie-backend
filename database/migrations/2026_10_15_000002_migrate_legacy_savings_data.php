<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies legacy Flex / Target savings into the unified savings tables.
 *
 * - Idempotent: accounts are keyed by `legacy_ref` / `flexi_key`, opening
 *   balances by a unique entry reference, so re-running never duplicates.
 * - Non-destructive: legacy tables are not modified or dropped (kept as an
 *   archive). Legacy autosave plans that pointed at a legacy flex/target are
 *   PAUSED so that wallet money can never be routed into a dead table.
 * - Balances are truncated to 2dp (kobo). Sub-kobo residue is noted in the
 *   entry meta and is not credited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('savings_accounts')) {
            return;
        }

        $now = now();

        if (Schema::hasTable('flex_savings')) {
            DB::table('flex_savings')->orderBy('id')->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $balance = $this->to2dp((string) $row->balance);
                    DB::transaction(function () use ($row, $balance, $now) {
                        $this->migrateAccount([
                            'user_id' => $row->user_id,
                            'product' => 'FLEXI',
                            'title' => 'Flexi Save',
                            'balance' => $balance,
                            'total_deposited' => $balance,
                            'interest_earned' => $this->to2dp((string) ($row->accrued_interest ?? '0')),
                            'status' => $row->status ? 'ACTIVE' : 'CANCELLED',
                            'flexi_key' => $row->user_id,
                            'legacy_ref' => 'flex:' . $row->id,
                        ], (string) $row->balance, $now);
                    });
                }
            });
        }

        if (Schema::hasTable('target_savings')) {
            DB::table('target_savings')->orderBy('id')->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $balance = $this->to2dp((string) $row->current_balance);
                    $status = match (strtolower((string) $row->status)) {
                        'active' => 'ACTIVE',
                        'completed' => 'COMPLETED',
                        default => 'CANCELLED',
                    };

                    DB::transaction(function () use ($row, $balance, $status, $now) {
                        $accountId = $this->migrateAccount([
                            'user_id' => $row->user_id,
                            'product' => 'TARGET',
                            'title' => mb_substr((string) $row->title, 0, 120),
                            'balance' => $balance,
                            'total_deposited' => $balance,
                            'target_amount' => $this->to2dp((string) $row->target_amount),
                            'start_date' => $row->start_date ? Carbon::parse($row->start_date)->toDateString() : null,
                            'deadline' => $row->target_date ? Carbon::parse($row->target_date)->toDateString() : null,
                            'status' => $status,
                            'legacy_ref' => 'target:' . $row->id,
                        ], (string) $row->current_balance, $now);

                        $amount = $row->auto_save_amount ? $this->to2dp((string) $row->auto_save_amount) : '0.00';
                        $frequency = strtoupper((string) $row->frequency);

                        if ($status === 'ACTIVE'
                            && bccomp($amount, '0.00', 2) > 0
                            && in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY'], true)
                            && ! DB::table('savings_recurring_plans')->where('account_id', $accountId)->exists()
                        ) {
                            $next = $row->next_save_date ? Carbon::parse($row->next_save_date)->utc() : $now->copy()->addDay();
                            $local = $next->copy()->setTimezone('Africa/Lagos');
                            DB::table('savings_recurring_plans')->insert([
                                'account_id' => $accountId,
                                'user_id' => $row->user_id,
                                'amount' => $amount,
                                'frequency' => $frequency,
                                'day_of_week' => $frequency === 'WEEKLY' ? (int) $local->dayOfWeek : null,
                                'day_of_month' => $frequency === 'MONTHLY' ? (int) $local->day : null,
                                'run_time' => $local->format('H:i'),
                                'timezone' => 'Africa/Lagos',
                                'start_date' => $local->toDateString(),
                                'funding_source' => 'WALLET',
                                'status' => 'ACTIVE',
                                'next_run_at' => $next,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    });
                }
            });
        }

        // Pause legacy autosave plans routed into the retired flex/target tables.
        if (Schema::hasTable('autosave_plans')) {
            DB::table('autosave_plans')
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->where('metadata', 'like', '%"target_type":"flex_savings"%')
                        ->orWhere('metadata', 'like', '%"target_type":"target_savings"%');
                })
                ->update(['status' => 'paused', 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: migrated rows are financial records and are
        // removed only when the schema migration is rolled back. Legacy tables
        // were never modified.
    }

    private function migrateAccount(array $attrs, string $rawBalance, Carbon $now): int
    {
        $existing = DB::table('savings_accounts')->where('legacy_ref', $attrs['legacy_ref'])->first();
        if ($existing) {
            return (int) $existing->id;
        }

        // A user may already have a unified flexi account (created post-deploy).
        if (($attrs['product'] ?? null) === 'FLEXI') {
            $current = DB::table('savings_accounts')->where('flexi_key', $attrs['flexi_key'])->first();
            if ($current) {
                return (int) $current->id;
            }
        }

        $id = DB::table('savings_accounts')->insertGetId($attrs + [
            'currency' => 'NGN',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (bccomp($attrs['balance'], '0.00', 2) > 0) {
            DB::table('savings_entries')->insert([
                'reference' => 'OPEN-' . $attrs['legacy_ref'],
                'user_id' => $attrs['user_id'],
                'account_id' => $id,
                'type' => 'OPENING',
                'direction' => 'CREDIT',
                'amount' => $attrs['balance'],
                'balance_after' => $attrs['balance'],
                'status' => 'COMPLETED',
                'source' => 'system',
                'narration' => 'Opening balance migrated from legacy savings',
                'meta' => json_encode(['legacy_ref' => $attrs['legacy_ref'], 'legacy_balance' => $rawBalance]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $id;
    }

    /** Truncate (never round up) a decimal string to 2dp. */
    private function to2dp(string $value): string
    {
        $value = trim($value) === '' ? '0' : $value;

        return bcadd($value, '0', 2);
    }
};
