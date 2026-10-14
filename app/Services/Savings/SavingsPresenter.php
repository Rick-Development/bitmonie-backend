<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\SavingsExecution;

/** API representations. Never exposes internal columns (request hashes, legacy refs). */
final class SavingsPresenter
{
    public static function account(SavingsAccount $a, ?SavingsManager $manager = null): array
    {
        $base = [
            'id' => $a->id,
            'product' => $a->product,
            'title' => $a->title,
            'description' => $a->description,
            'category' => $a->category,
            'currency' => $a->currency,
            'status' => $a->status,
            'balance' => Money::norm($a->balance),
            'available_balance' => $a->availableBalance(),
            'total_deposited' => Money::norm($a->total_deposited),
            'total_withdrawn' => Money::norm($a->total_withdrawn),
            'interest_earned' => Money::norm($a->interest_earned),
            'created_at' => $a->created_at?->toIso8601String(),
        ];

        if ($a->product === SavingsAccount::TARGET) {
            $base += [
                'target_amount' => Money::norm($a->target_amount),
                'start_date' => $a->start_date?->toDateString(),
                'deadline' => $a->deadline?->toDateString(),
                'completed_at' => $a->completed_at?->toIso8601String(),
            ];
            if ($manager) {
                $base['progress'] = $manager->progress($a);
            }
        }

        if ($a->relationLoaded('plan') && $a->plan) {
            $base['plan'] = self::plan($a->plan);
        }

        return $base;
    }

    public static function plan(RecurringSavingsPlan $p): array
    {
        return [
            'id' => $p->id,
            'account_id' => $p->account_id,
            'amount' => Money::norm($p->amount),
            'frequency' => $p->frequency,
            'day_of_week' => $p->day_of_week,
            'day_of_month' => $p->day_of_month,
            'run_time' => $p->run_time,
            'timezone' => $p->timezone,
            'start_date' => $p->start_date?->toDateString(),
            'end_date' => $p->end_date?->toDateString(),
            'funding_source' => $p->funding_source,
            'status' => $p->status,
            'next_run_at' => $p->next_run_at?->toIso8601String(),
            'last_success_at' => $p->last_success_at?->toIso8601String(),
        ];
    }

    public static function entry(SavingsEntry $e): array
    {
        return [
            'reference' => $e->reference,
            'account_id' => $e->account_id,
            'type' => $e->type,
            'direction' => $e->direction,
            'amount' => Money::norm($e->amount),
            'balance_after' => $e->balance_after !== null ? Money::norm($e->balance_after) : null,
            'status' => $e->status,
            'source' => $e->source,
            'narration' => $e->narration,
            'related_reference' => $e->group_ref,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    public static function execution(SavingsExecution $x): array
    {
        return [
            'id' => $x->id,
            'plan_id' => $x->plan_id,
            'amount' => Money::norm($x->amount),
            'scheduled_for' => $x->scheduled_for?->toIso8601String(),
            'status' => $x->status,
            'attempts' => $x->attempts,
            'next_attempt_at' => $x->next_attempt_at?->toIso8601String(),
            'failure_code' => $x->failure_code,
            'failure_message' => $x->failure_message,
            'completed_at' => $x->completed_at?->toIso8601String(),
        ];
    }
}
