<?php

namespace App\Services\Savings;

use App\Models\RecurringSavingsPlan;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\User;
use App\Notifications\User\SavingsNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Product rules for Flexi Save, Auto Save and Target Savings. Product
 * differences live here (as guards/hooks); all balance changes are delegated
 * to {@see SavingsLedger}, so the three products cannot drift apart.
 */
class SavingsManager
{
    public function __construct(
        private readonly SavingsLedger $ledger,
        private readonly SavingsScheduleService $schedule,
        private readonly SavingsAuditor $audit,
    ) {
    }

    // ------------------------------------------------------------ lookups

    /** Ownership is enforced by always scoping on the authenticated user. */
    public function find(User|int $user, int $id, ?string $product = null): SavingsAccount
    {
        $q = SavingsAccount::where('user_id', $this->uid($user))->whereKey($id);
        if ($product) {
            $q->where('product', $product);
        }

        return $q->first() ?? throw SavingsException::notFound('Savings account');
    }

    public function flexi(User|int $user): SavingsAccount
    {
        $uid = $this->uid($user);
        $existing = SavingsAccount::where('user_id', $uid)->where('product', SavingsAccount::FLEXI)->first();
        if ($existing) {
            return $existing;
        }

        try {
            $account = SavingsAccount::create([
                'user_id' => $uid, 'product' => SavingsAccount::FLEXI, 'title' => 'Flexi Save',
                'currency' => config('savings.currency'), 'status' => SavingsAccount::ACTIVE, 'flexi_key' => $uid,
                'start_date' => now()->toDateString(),
            ]);
            $this->audit->record('account.created', $uid, $account->id, ['product' => 'FLEXI']);

            return $account;
        } catch (QueryException $e) {
            // Concurrent provisioning: the unique flexi_key picked a winner.
            return SavingsAccount::where('user_id', $uid)->where('product', SavingsAccount::FLEXI)->firstOrFail();
        }
    }

    // ------------------------------------------------------------ Auto Save

    public function createAuto(User $user, array $in): array
    {
        $this->assertCount($user, SavingsAccount::AUTO, (int) config('savings.limits.max_auto_plans'));
        $settings = $this->schedule->normalize($in);

        return DB::transaction(function () use ($user, $in, $settings) {
            $account = SavingsAccount::create([
                'user_id' => $user->id, 'product' => SavingsAccount::AUTO,
                'title' => trim((string) ($in['title'] ?? 'Auto Save')) ?: 'Auto Save',
                'description' => $in['description'] ?? null, 'currency' => config('savings.currency'),
                'status' => SavingsAccount::ACTIVE, 'start_date' => $settings['start_date'],
            ]);
            $plan = $this->schedule->createPlan($account, $settings);
            $this->audit->record('account.created', $user->id, $account->id, ['product' => 'AUTO']);

            // Auto-deposit initial amount if provided or requested
            $initialAmount = $in['initial_deposit'] ?? $in['deposit_amount'] ?? $in['amount'] ?? null;
            if ($initialAmount !== null && Money::cmp(Money::parse($initialAmount), '0.00') > 0) {
                $parsedAmount = Money::parse($initialAmount);
                $key = $in['idempotency_key'] ?? ('auto-init-' . Str::uuid());
                $this->contribute($user, $account->id, $parsedAmount, 'wallet', $key);
                $account = $account->refresh();
            }

            return ['account' => $account, 'plan' => $plan];
        });
    }

    public function updateAuto(User $user, int $id, array $in): array
    {
        $account = $this->find($user, $id, SavingsAccount::AUTO);
        $plan = $this->planFor($account);
        $plan = $this->schedule->updatePlan($plan, $in);

        if (array_key_exists('title', $in) || array_key_exists('description', $in)) {
            $account->fill(array_filter(['title' => $in['title'] ?? null, 'description' => $in['description'] ?? null], fn ($v) => $v !== null))->save();
        }

        return ['account' => $account->refresh(), 'plan' => $plan];
    }

    public function pausePlan(User $user, int $accountId): RecurringSavingsPlan
    {
        $account = $this->find($user, $accountId);

        return $this->schedule->pause($this->planFor($account));
    }

    public function resumePlan(User $user, int $accountId): RecurringSavingsPlan
    {
        $account = $this->find($user, $accountId);
        if ($account->status !== SavingsAccount::ACTIVE) {
            throw SavingsException::invalidState('The account must be active to resume its schedule.');
        }

        return $this->schedule->resume($this->planFor($account));
    }

    /** Cancel an Auto Save plan: schedule stops, history and balance remain (withdrawable). */
    public function cancelAuto(User $user, int $id): SavingsAccount
    {
        $account = $this->find($user, $id, SavingsAccount::AUTO);

        return DB::transaction(function () use ($account) {
            $this->schedule->cancel($this->planFor($account));
            $locked = SavingsAccount::whereKey($account->id)->lockForUpdate()->first();
            if ($locked->status !== SavingsAccount::CANCELLED) {
                SavingsStateMachine::account($locked->status, SavingsAccount::CANCELLED);
                $locked->update(['status' => SavingsAccount::CANCELLED, 'closed_at' => now()]);
            }
            $this->audit->record('account.cancelled', $locked->user_id, $locked->id, ['product' => 'AUTO']);

            return $locked;
        });
    }

    // ------------------------------------------------------------ Targets

    public function createTarget(User $user, array $in): array
    {
        $this->assertCount($user, SavingsAccount::TARGET, (int) config('savings.limits.max_active_targets'));

        $target = Money::parse($in['target_amount'] ?? null, 'target_amount');
        if (Money::cmp($target, (string) config('savings.limits.max_target_amount')) > 0) {
            throw new SavingsException('The target amount exceeds the maximum allowed.', 'TARGET_TOO_LARGE');
        }

        $tz = (string) config('savings.scheduler.default_timezone');
        $today = Carbon::now($tz)->startOfDay();
        $start = isset($in['start_date']) ? Carbon::parse($in['start_date'], $tz)->startOfDay() : $today->copy();
        $deadline = ! empty($in['deadline']) ? Carbon::parse($in['deadline'], $tz)->startOfDay() : null;
        if ($start->lt($today)) {
            throw new SavingsException('The start date cannot be in the past.', 'INVALID_START_DATE');
        }
        if ($deadline && $deadline->lte($start)) {
            throw new SavingsException('The deadline must be after the start date.', 'INVALID_DEADLINE');
        }

        $settings = null;
        if (! empty($in['schedule'])) {
            $settings = $this->schedule->normalize($in['schedule'] + ['start_date' => $in['schedule']['start_date'] ?? $start->toDateString(), 'timezone' => $in['schedule']['timezone'] ?? $tz]);
        }

        return DB::transaction(function () use ($user, $in, $target, $start, $deadline, $settings) {
            $account = SavingsAccount::create([
                'user_id' => $user->id, 'product' => SavingsAccount::TARGET,
                'title' => trim((string) $in['title']), 'description' => $in['description'] ?? null,
                'category' => $in['category'] ?? null, 'currency' => config('savings.currency'),
                'target_amount' => $target, 'start_date' => $start->toDateString(), 'deadline' => $deadline?->toDateString(),
                'status' => SavingsAccount::ACTIVE,
            ]);
            $plan = $settings ? $this->schedule->createPlan($account, $settings) : null;
            $this->audit->record('account.created', $user->id, $account->id, ['product' => 'TARGET', 'target_amount' => $target]);

            // Auto-deposit initial amount if provided or requested
            $initialAmount = $in['initial_deposit'] ?? $in['deposit_amount'] ?? $in['amount'] ?? null;
            if ($initialAmount !== null && Money::cmp(Money::parse($initialAmount), '0.00') > 0) {
                $parsedAmount = Money::parse($initialAmount);
                $key = $in['idempotency_key'] ?? ('target-init-' . Str::uuid());
                $this->contribute($user, $account->id, $parsedAmount, 'wallet', $key);
                $account = $account->refresh();
            }

            return ['account' => $account, 'plan' => $plan];
        });
    }

    public function updateTarget(User $user, int $id, array $in): SavingsAccount
    {
        return DB::transaction(function () use ($user, $id, $in) {
            $account = SavingsAccount::where('user_id', $user->id)->whereKey($id)->where('product', SavingsAccount::TARGET)->lockForUpdate()->first()
                ?? throw SavingsException::notFound('Target');
            if (! in_array($account->status, [SavingsAccount::ACTIVE, SavingsAccount::PAUSED], true)) {
                throw SavingsException::invalidState('Only active or paused targets can be edited.');
            }

            $changes = [];
            foreach (['title', 'description', 'category'] as $f) {
                if (array_key_exists($f, $in)) {
                    $changes[$f] = $in[$f];
                }
            }
            if (array_key_exists('target_amount', $in)) {
                $amount = Money::parse($in['target_amount'], 'target_amount');
                if (Money::cmp($amount, (string) config('savings.limits.max_target_amount')) > 0) {
                    throw new SavingsException('The target amount exceeds the maximum allowed.', 'TARGET_TOO_LARGE');
                }
                if (Money::cmp($amount, Money::norm($account->balance)) <= 0) {
                    throw new SavingsException('The target amount must be greater than the amount already saved.', 'TARGET_BELOW_SAVED');
                }
                $changes['target_amount'] = $amount;
            }
            if (array_key_exists('deadline', $in)) {
                $deadline = $in['deadline'] ? Carbon::parse($in['deadline'], config('savings.scheduler.default_timezone'))->startOfDay() : null;
                if ($deadline && $deadline->lte(Carbon::parse($account->start_date->toDateString()))) {
                    throw new SavingsException('The deadline must be after the start date.', 'INVALID_DEADLINE');
                }
                if ($deadline && $deadline->lt(Carbon::now(config('savings.scheduler.default_timezone'))->startOfDay())) {
                    throw new SavingsException('The deadline cannot be in the past.', 'INVALID_DEADLINE');
                }
                $changes['deadline'] = $deadline?->toDateString();
            }

            $account->update($changes);
            $this->audit->record('account.updated', $user->id, $account->id, ['changed' => array_keys($changes)]);

            return $account->refresh();
        });
    }

    /** Pause or resume the whole target (contributions and schedule). */
    public function setTargetPaused(User $user, int $id, bool $paused): SavingsAccount
    {
        return DB::transaction(function () use ($user, $id, $paused) {
            $account = SavingsAccount::where('user_id', $user->id)->whereKey($id)->where('product', SavingsAccount::TARGET)->lockForUpdate()->first()
                ?? throw SavingsException::notFound('Target');
            $to = $paused ? SavingsAccount::PAUSED : SavingsAccount::ACTIVE;
            if ($account->status === $to) {
                return $account;
            }
            SavingsStateMachine::account($account->status, $to);
            $account->update(['status' => $to]);

            $plan = $account->plan;
            if ($plan && in_array($plan->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
                $paused ? $this->schedule->pause($plan) : $this->schedule->resume($plan);
            }
            $this->audit->record($paused ? 'account.paused' : 'account.resumed', $user->id, $account->id);

            return $account->refresh();
        });
    }

    /** Create or replace a target's recurring contribution schedule. */
    public function setTargetSchedule(User $user, int $id, array $in): RecurringSavingsPlan
    {
        $account = $this->find($user, $id, SavingsAccount::TARGET);
        if (! in_array($account->status, [SavingsAccount::ACTIVE, SavingsAccount::PAUSED], true)) {
            throw SavingsException::invalidState('Schedules can only be set on active or paused targets.');
        }

        $existing = $account->plan;
        if ($existing && in_array($existing->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
            return $this->schedule->updatePlan($existing, $in);
        }

        $tz = (string) config('savings.scheduler.default_timezone');
        $settings = $this->schedule->normalize($in + ['timezone' => $tz]);

        return DB::transaction(function () use ($account, $existing, $settings) {
            if (! $existing) {
                return $this->schedule->createPlan($account, $settings);
            }

            // One plan row per account: a closed schedule is re-armed in place so
            // its execution history is preserved (no deletes of financial records).
            $locked = RecurringSavingsPlan::whereKey($existing->id)->lockForUpdate()->firstOrFail();
            $locked->fill($settings + [
                'status' => RecurringSavingsPlan::ACTIVE,
                'next_run_at' => $this->schedule->nextRunAfter($settings, Carbon::now('UTC')),
            ])->save();
            $this->audit->record('plan.recreated', $account->user_id, $account->id, ['plan_id' => $locked->id]);

            return $locked;
        });
    }

    public function removeTargetSchedule(User $user, int $id): RecurringSavingsPlan
    {
        $account = $this->find($user, $id, SavingsAccount::TARGET);

        return $this->schedule->cancel($this->planFor($account));
    }

    /** Mark a target as completed. Only possible once the credited balance reaches the goal. */
    public function completeTarget(User $user, int $id): SavingsAccount
    {
        return DB::transaction(function () use ($user, $id) {
            $account = SavingsAccount::where('user_id', $user->id)->whereKey($id)->where('product', SavingsAccount::TARGET)->lockForUpdate()->first()
                ?? throw SavingsException::notFound('Target');
            if ($account->status === SavingsAccount::COMPLETED) {
                return $account; // idempotent: no duplicate completion
            }
            if (Money::cmp(Money::norm($account->balance), Money::norm($account->target_amount)) < 0) {
                throw new SavingsException('The target amount has not been reached yet.', 'TARGET_NOT_REACHED');
            }
            $this->markCompleted($account);

            return $account->refresh();
        });
    }

    /** Preview the cost of closing a target now (no side effects). */
    public function closureQuote(SavingsAccount $account): array
    {
        $balance = Money::norm($account->balance);
        $fee = $this->earlyClosureFee($account, $balance);

        return [
            'balance' => $balance, 'fee' => $fee, 'net_amount' => Money::sub($balance, $fee),
            'fee_percent' => (string) config('savings.target.early_closure_fee_percent'),
            'requires_acknowledgement' => Money::cmp($fee, '0.00') > 0,
        ];
    }

    /** Close a target: pays the net balance to the wallet; fee only with explicit acknowledgement. */
    public function closeTarget(User $user, int $id, bool $acknowledgeFee, ?string $key): array
    {
        $account = $this->find($user, $id, SavingsAccount::TARGET);
        if ($account->status === SavingsAccount::CANCELLED) {
            throw SavingsException::invalidState('This target is already closed.');
        }

        $quote = $this->closureQuote($account);
        if ($quote['requires_acknowledgement'] && ! $acknowledgeFee) {
            throw new SavingsException(
                "Closing before the deadline incurs a fee of ₦{$quote['fee']}. Resubmit with acknowledge_fee=true to proceed.",
                'FEE_ACKNOWLEDGEMENT_REQUIRED', 422, false, ['quote' => $quote]
            );
        }

        if (Money::cmp($quote['balance'], '0.00') === 0) {
            DB::transaction(function () use ($account) {
                $locked = SavingsAccount::whereKey($account->id)->lockForUpdate()->first();
                $this->closeAccountRow($locked);
            });

            return ['entry' => null, 'quote' => $quote, 'replayed' => false];
        }

        $result = $this->ledger->withdraw($user->id, $account->id, $quote['net_amount'], [
            'idempotency_key' => $key, 'source' => 'wallet', 'narration' => "Closed target: {$account->title}", 'fee' => $quote['fee'],
            'guard' => function (SavingsAccount $locked) use ($quote) {
                if ($locked->status === SavingsAccount::CANCELLED) {
                    throw SavingsException::invalidState('This target is already closed.');
                }
                // Balance may have changed since the quote; fee/net must still cover it exactly.
                $total = Money::add($quote['net_amount'], $quote['fee']);
                if (Money::cmp(Money::norm($locked->balance), $total) !== 0) {
                    throw new SavingsException('The balance changed; please request a new quote.', 'BALANCE_CHANGED', 409);
                }

                return $quote['net_amount'];
            },
            'after' => fn (SavingsAccount $a) => $this->closeAccountRow($a),
        ]);

        if (! $result['replayed']) {
            $this->notify($user, 'Target Savings', 'Withdrawn', $quote['net_amount'], $result['entry']->reference);
        }

        return $result + ['quote' => $quote];
    }

    // ------------------------------------------------------------ money movement

    /** Fund Flexi from the user's wallet. */
    public function depositFlexi(User $user, string $amount, ?string $key): array
    {
        Money::assertWithinLimits($amount);
        $flexi = $this->flexi($user);

        $result = $this->ledger->deposit($user->id, $flexi->id, $amount, [
            'idempotency_key' => $key, 'source' => 'wallet', 'narration' => 'Flexi Save top-up',
            'guard' => fn (SavingsAccount $a) => $this->assertCanReceive($a, $amount),
        ]);
        $this->afterCredit($user, 'Flexi Save', 'Topup', $amount, $result);

        return $result;
    }

    /**
     * Manual contribution to an Auto Save or Target account from the wallet
     * or from Flexi Save. Independent of the schedule.
     */
    public function contribute(User $user, int $accountId, string $amount, string $source, ?string $key): array
    {
        Money::assertWithinLimits($amount);
        $account = $this->find($user, $accountId);
        if ($account->product === SavingsAccount::FLEXI) {
            throw new SavingsException('Use the Flexi Save deposit endpoint.', 'WRONG_PRODUCT');
        }

        $label = $account->product === SavingsAccount::TARGET ? 'Target Savings' : 'Auto Save';

        if ($source === 'flexi') {
            $result = $this->transfer($user, $this->flexi($user)->id, $account->id, $amount, $key, 'Contribution from Flexi Save');
        } else {
            $result = $this->ledger->deposit($user->id, $account->id, $amount, [
                'idempotency_key' => $key, 'source' => 'manual', 'narration' => "Contribution to {$account->title}",
                'guard' => fn (SavingsAccount $a) => $this->assertCanReceive($a, $amount),
                'after' => fn (SavingsAccount $a) => $this->afterCreditHook($a),
            ]);
        }
        $this->afterCredit($user, $label, 'Topup', $amount, $result);

        return $result;
    }

    public function withdraw(User $user, int $accountId, string $amount, ?string $key): array
    {
        Money::assertWithinLimits($amount);
        $account = $this->find($user, $accountId);
        $this->assertCanSendFrom($account);

        $result = $this->ledger->withdraw($user->id, $account->id, $amount, [
            'idempotency_key' => $key, 'source' => 'wallet', 'narration' => "Withdrawal from {$account->title}",
            'guard' => function (SavingsAccount $a) use ($amount) {
                $this->assertCanSendFrom($a);
                if (Money::cmp($a->availableBalance(), $amount) < 0) {
                    throw SavingsException::insufficientFunds('available balance');
                }
            },
        ]);

        if (! $result['replayed']) {
            $this->notify($user, $this->label($account), 'Withdrawn', $amount, $result['entry']->reference);
        }

        return $result;
    }

    public function transfer(User $user, int $fromId, int $toId, string $amount, ?string $key, ?string $narration = null): array
    {
        Money::assertWithinLimits($amount);
        $from = $this->find($user, $fromId);
        $to = $this->find($user, $toId); // both must belong to the caller

        $result = $this->ledger->transfer($user->id, $from->id, $to->id, $amount, [
            'idempotency_key' => $key, 'narration' => $narration ?? "Transfer to {$to->title}",
            'guard' => function (SavingsAccount $a) use ($amount) {
                $this->assertCanSendFrom($a);
                if (Money::cmp($a->availableBalance(), $amount) < 0) {
                    throw SavingsException::insufficientFunds('available balance');
                }
            },
            'destination_guard' => fn (SavingsAccount $a, string $amt) => $this->assertCanReceive($a, $amt),
            'after' => fn (SavingsAccount $a) => $this->afterCreditHook($a),
        ]);

        return $result;
    }

    // ------------------------------------------------------------ reads

    public function progress(SavingsAccount $account): array
    {
        $balance = Money::norm($account->balance);
        $out = [
            'account_id' => $account->id, 'product' => $account->product, 'status' => $account->status,
            'balance' => $balance, 'available_balance' => $account->availableBalance(),
            'total_deposited' => Money::norm($account->total_deposited), 'total_withdrawn' => Money::norm($account->total_withdrawn),
            'interest_earned' => Money::norm($account->interest_earned), 'currency' => $account->currency,
        ];

        if ($account->product === SavingsAccount::TARGET) {
            $target = Money::norm($account->target_amount);
            $pct = Money::cmp($target, '0.00') > 0 ? bcdiv(bcmul(Money::min($balance, $target), '100', 6), $target, 2) : '0.00';
            $out += [
                'target_amount' => $target, 'saved_amount' => $balance,
                'remaining_amount' => Money::cmp($target, $balance) > 0 ? Money::sub($target, $balance) : '0.00',
                'percentage_completed' => $pct, 'deadline' => $account->deadline?->toDateString(),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------ rules

    /** Called under the destination's row lock. */
    public function assertCanReceive(SavingsAccount $a, string $amount): void
    {
        if ($a->status !== SavingsAccount::ACTIVE) {
            throw SavingsException::invalidState("This {$this->label($a)} account is {$a->status} and cannot receive funds.");
        }

        if ($a->product === SavingsAccount::TARGET) {
            if ($a->deadline && Carbon::now(config('savings.scheduler.default_timezone'))->startOfDay()->gt(Carbon::parse($a->deadline->toDateString(), config('savings.scheduler.default_timezone')))) {
                throw SavingsException::invalidState('The deadline for this target has passed.');
            }
            $remaining = Money::sub(Money::norm($a->target_amount), Money::norm($a->balance));
            if (Money::cmp($amount, $remaining) > 0) {
                throw new SavingsException("This would exceed the target. You can contribute at most ₦{$remaining}.", 'EXCEEDS_TARGET', 422, false, ['remaining' => $remaining]);
            }
        }
    }

    private function assertCanSendFrom(SavingsAccount $a): void
    {
        $ok = match ($a->product) {
            SavingsAccount::FLEXI => $a->status === SavingsAccount::ACTIVE,
            SavingsAccount::AUTO => in_array($a->status, [SavingsAccount::ACTIVE, SavingsAccount::CANCELLED], true),
            // Active targets must be closed through closeTarget() (fee policy).
            SavingsAccount::TARGET => in_array($a->status, [SavingsAccount::COMPLETED, SavingsAccount::EXPIRED], true),
            default => false,
        };

        if (! $ok) {
            throw SavingsException::invalidState($a->product === SavingsAccount::TARGET
                ? 'Funds in an active target can only be accessed by closing it.'
                : "Funds cannot be withdrawn while the account is {$a->status}.");
        }
    }

    private function earlyClosureFee(SavingsAccount $a, string $balance): string
    {
        $percent = (string) config('savings.target.early_closure_fee_percent');
        $early = in_array($a->status, [SavingsAccount::ACTIVE, SavingsAccount::PAUSED], true)
            && $a->deadline
            && Carbon::now(config('savings.scheduler.default_timezone'))->startOfDay()->lte(Carbon::parse($a->deadline->toDateString(), config('savings.scheduler.default_timezone')))
            && Money::cmp($balance, Money::norm($a->target_amount)) < 0;

        return ($early && bccomp($percent, '0', 4) > 0) ? Money::percent($balance, $percent) : '0.00';
    }

    /** Hook (inside the lock): complete a target the moment its credited balance reaches the goal. */
    public function afterCreditHook(SavingsAccount $a): void
    {
        if ($a->product === SavingsAccount::TARGET
            && $a->status === SavingsAccount::ACTIVE
            && Money::cmp(Money::norm($a->balance), Money::norm($a->target_amount)) >= 0) {
            $this->markCompleted($a);
        }
    }

    private function markCompleted(SavingsAccount $a): void
    {
        SavingsStateMachine::account($a->status, SavingsAccount::COMPLETED);
        $a->update(['status' => SavingsAccount::COMPLETED, 'completed_at' => now()]);

        $plan = $a->plan;
        if ($plan && in_array($plan->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
            $this->schedule->transition($plan, RecurringSavingsPlan::COMPLETED, 'plan.completed', fn ($p) => $p->next_run_at = null);
        }
        $this->audit->record('target.completed', $a->user_id, $a->id, ['balance' => Money::norm($a->balance)]);
    }

    private function closeAccountRow(SavingsAccount $a): void
    {
        SavingsStateMachine::account($a->status, SavingsAccount::CANCELLED);
        $a->update(['status' => SavingsAccount::CANCELLED, 'closed_at' => now()]);

        $plan = $a->plan;
        if ($plan && in_array($plan->status, [RecurringSavingsPlan::ACTIVE, RecurringSavingsPlan::PAUSED], true)) {
            $this->schedule->cancel($plan);
        }
        $this->audit->record('account.closed', $a->user_id, $a->id, ['product' => $a->product]);
    }

    // ------------------------------------------------------------ helpers

    public function planFor(SavingsAccount $account): RecurringSavingsPlan
    {
        return $account->plan ?? throw SavingsException::notFound('Recurring plan');
    }

    private function assertCount(User $user, string $product, int $max): void
    {
        $count = SavingsAccount::where('user_id', $user->id)->where('product', $product)
            ->whereIn('status', [SavingsAccount::ACTIVE, SavingsAccount::PAUSED])->count();
        if ($count >= $max) {
            throw new SavingsException("You have reached the maximum number of active {$product} savings.", 'LIMIT_REACHED');
        }
    }

    private function afterCredit(User $user, string $label, string $action, string $amount, array $result): void
    {
        if (! $result['replayed']) {
            $this->notify($user, $label, $action, $amount, $result['entry']->reference);
        }
    }

    private function label(SavingsAccount $a): string
    {
        return match ($a->product) {
            SavingsAccount::FLEXI => 'Flexi Save',
            SavingsAccount::TARGET => 'Target Savings',
            default => 'Auto Save',
        };
    }

    /** Fire-and-forget; called only after the money movement has committed. */
    public function notify(User|int $user, string $label, string $action, string $amount, ?string $reference = null): void
    {
        try {
            $u = $user instanceof User ? $user : User::find($user);
            $u?->notify(new SavingsNotification($label, $action, (float) $amount, config('savings.currency'), $reference));
        } catch (\Throwable $e) {
            Log::warning('savings.notification_failed', ['error' => $e->getMessage()]);
        }
    }

    private function uid(User|int $user): int
    {
        return $user instanceof User ? (int) $user->id : $user;
    }
}
