<?php

namespace App\Services\Savings;

use App\Models\OrderTransaction;
use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\User;
use App\Models\UserWallet;
use App\Services\SafeHavenService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The ONLY component allowed to change a savings balance or wallet balance
 * on behalf of savings. Every movement is a single local DB transaction that
 * writes balanced records:
 *
 *   deposit      wallet  -> account   (wallet debit + DEPOSIT credit)
 *   withdraw     account -> wallet    (WITHDRAWAL debit + wallet credit)
 *   transfer     account -> account   (TRANSFER_OUT debit + TRANSFER_IN credit)
 *   interest/fee/reversal             (explicit, referenced entries)
 *
 * Locks are always taken accounts-first (ordered by id), wallet last, so
 * concurrent operations cannot deadlock on opposite orderings. Idempotency
 * is enforced by unique (user_id, idempotency_key), unique execution_id and
 * unique reverses_entry_id constraints, so a race resolves to one winner.
 *
 * Options (all optional): idempotency_key, source, narration, meta,
 * execution_id, guard(SavingsAccount, string $amount): string  (runs under
 * the row lock; may throw or return an adjusted amount), after(SavingsAccount).
 */
class SavingsLedger
{
    /** Request hash of the idempotent operation currently being executed. */
    private ?string $currentHash = null;

    public function __construct(
        private readonly SavingsAuditor $audit,
        private readonly ?SavingsFundingProviderInterface $fundingProvider = null,
        private readonly ?SafeHavenService $safeHavenService = null
    ) {
    }

    private function getFundingProvider(): ?SavingsFundingProviderInterface
    {
        return $this->fundingProvider ?? app(SavingsFundingProviderInterface::class);
    }

    private function getSafeHavenService(): ?SafeHavenService
    {
        return $this->safeHavenService ?? app(SafeHavenService::class);
    }

    public function hasSafeHavenAccount(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            if (! Schema::hasTable('virtual_accounts')) {
                return false;
            }

            $service = $this->getSafeHavenService();
            if (! $service) {
                return false;
            }

            $virtualAccount = $service->getUserSubAccount($user);

            return $virtualAccount !== null && ! empty($virtualAccount->account_number);
        } catch (\Throwable) {
            return false;
        }
    }

    public function syncWalletBeforeOperation(int $userId): void
    {
        try {
            if (! Schema::hasTable('virtual_accounts')) {
                return;
            }

            $user = User::find($userId);
            if ($user && $this->hasSafeHavenAccount($user)) {
                $service = $this->getSafeHavenService();
                $service?->syncUserWalletBalance($user, config('savings.currency', 'NGN'));
            }
        } catch (\Throwable $e) {
            Log::warning('savings.wallet_sync_skipped', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /** Wallet -> savings account. @return array{entry: SavingsEntry, replayed: bool} */
    public function deposit(int $userId, int $accountId, string $amount, array $o = []): array
    {
        $fingerprint = ['op' => 'deposit', 'account' => $accountId, 'amount' => $amount];

        // Ensure wallet balance is refreshed from SafeHaven before verifying sufficiency
        $this->syncWalletBeforeOperation($userId);

        $providerFundedReference = null;
        $user = null;

        try {
            return $this->run($userId, $o, $fingerprint, function () use ($userId, $accountId, $amount, $o, &$providerFundedReference, &$user) {
                $user = User::find($userId);
                $account = $this->lockAccount($accountId, $userId);
                $amount = $this->applyGuard($o, $account, $amount);
                $reference = $this->newReference('DEP');

                $wallet = $this->lockWallet($userId);
                $this->debitWallet($wallet, $amount, $reference, 'savings_deposit', $account);

                $providerMeta = [];
                if ($this->hasSafeHavenAccount($user)) {
                    $providerRef = 'savings:dep:' . $reference;
                    $narration = $o['narration'] ?? "Savings Deposit: {$account->title}";
                    $provider = $this->getFundingProvider();
                    if ($provider) {
                        $provider->deposit($user, $amount, $providerRef, $narration);
                        $providerFundedReference = $providerRef;
                        $providerMeta = [
                            'provider' => 'safehaven',
                            'provider_reference' => $providerRef,
                        ];
                    }
                }

                $meta = array_merge($o['meta'] ?? [], $providerMeta);
                $entry = $this->credit($account, $amount, SavingsEntry::DEPOSIT, $reference, array_merge($o, ['source' => 'wallet', 'meta' => $meta]));
                $this->applyAfter($o, $account);

                return ['entry' => $entry];
            }, 'deposit.completed');
        } catch (\Throwable $e) {
            if ($providerFundedReference !== null && $user !== null) {
                try {
                    $provider = $this->getFundingProvider();
                    $provider?->withdraw(
                        $user,
                        $amount,
                        $providerFundedReference . ':reversal',
                        'Savings deposit reversal'
                    );
                    Log::info('savings.deposit_reversal_succeeded', [
                        'user_id' => $user->id,
                        'reference' => $providerFundedReference,
                    ]);
                } catch (\Throwable $revErr) {
                    Log::critical('savings.deposit_reversal_failed', [
                        'user_id' => $user->id,
                        'reference' => $providerFundedReference,
                        'error' => $revErr->getMessage(),
                    ]);
                }
            }
            throw $e;
        }
    }

    /**
     * Savings account -> wallet. Optional 'fee' is debited from the account
     * as a separate, visible FEE entry (and is never sent to the wallet).
     */
    public function withdraw(int $userId, int $accountId, string $amount, array $o = []): array
    {
        $fee = $o['fee'] ?? '0.00';
        $fingerprint = ['op' => 'withdraw', 'account' => $accountId, 'amount' => $amount, 'fee' => $fee];

        return $this->run($userId, $o, $fingerprint, function () use ($userId, $accountId, $amount, $fee, $o) {
            $user = User::find($userId);
            $account = $this->lockAccount($accountId, $userId);
            $amount = $this->applyGuard($o, $account, $amount);
            $reference = $this->newReference('WDR');
            $group = Money::cmp($fee, '0.00') > 0 ? $this->newReference('GRP') : null;

            if (Money::cmp($fee, '0.00') > 0) {
                $this->debit($account, $fee, SavingsEntry::FEE, $this->newReference('FEE'), [
                    'narration' => 'Early closure fee',
                    'source' => 'system',
                ] + ['group_ref' => $group]);
            }

            $providerMeta = [];
            if ($this->hasSafeHavenAccount($user)) {
                $providerRef = 'savings:wdr:' . $reference;
                $narration = $o['narration'] ?? "Savings Withdrawal: {$account->title}";
                $provider = $this->getFundingProvider();
                if ($provider) {
                    $provider->withdraw($user, $amount, $providerRef, $narration);
                    $providerMeta = [
                        'provider' => 'safehaven',
                        'provider_reference' => $providerRef,
                    ];
                }
            }

            $meta = array_merge($o['meta'] ?? [], $providerMeta);
            $entry = $this->debit($account, $amount, SavingsEntry::WITHDRAWAL, $reference, array_merge($o, ['source' => 'wallet', 'group_ref' => $group, 'meta' => $meta]));

            $wallet = $this->lockWallet($userId);
            $this->creditWallet($wallet, $amount, $reference, 'savings_withdrawal', $account);
            $this->applyAfter($o, $account);

            return ['entry' => $entry];
        }, 'withdrawal.completed');
    }

    /** Account -> account (same user). Both legs commit or neither does. */
    public function transfer(int $userId, int $fromId, int $toId, string $amount, array $o = []): array
    {
        if ($fromId === $toId) {
            throw new SavingsException('Source and destination must be different accounts.', 'SAME_ACCOUNT');
        }

        $fingerprint = ['op' => 'transfer', 'from' => $fromId, 'to' => $toId, 'amount' => $amount];

        return $this->run($userId, $o, $fingerprint, function () use ($userId, $fromId, $toId, $amount, $o) {
            // Lock in id order to avoid deadlocks between opposite transfers.
            $ids = [$fromId, $toId];
            sort($ids);
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = $this->lockAccount($id, $userId);
            }
            $from = $locked[$fromId];
            $to = $locked[$toId];

            $amount = $this->applyGuard($o, $from, $amount);
            if (isset($o['destination_guard'])) {
                ($o['destination_guard'])($to, $amount);
            }

            $group = $this->newReference('TRF');
            $out = $this->debit($from, $amount, SavingsEntry::TRANSFER_OUT, $this->newReference('TFO'), array_merge([
                'source' => 'transfer',
            ], $o, [
                'group_ref' => $group, 'meta' => array_merge($o['meta'] ?? [], ['to_account_id' => $toId]),
            ]));
            $this->credit($to, $amount, SavingsEntry::TRANSFER_IN, $this->newReference('TFI'), [
                'source' => 'transfer', 'group_ref' => $group, 'narration' => $o['narration'] ?? null,
                'meta' => ['from_account_id' => $fromId],
            ]);

            $this->applyAfter($o, $to);

            return ['entry' => $out];
        }, 'transfer.completed');
    }

    /** Credit interest. Idempotent on $reference (unique). Platform-funded. */
    public function interest(int $userId, int $accountId, string $amount, string $reference, string $narration): array
    {
        return $this->run($userId, [], ['op' => 'interest', 'ref' => $reference], function () use ($userId, $accountId, $amount, $reference, $narration) {
            if (SavingsEntry::where('reference', $reference)->exists()) {
                throw new DuplicateEntry($reference);
            }
            $account = $this->lockAccount($accountId, $userId);
            if ($account->status !== SavingsAccount::ACTIVE) {
                throw SavingsException::invalidState('Interest can only be credited to an active account.');
            }

            return ['entry' => $this->credit($account, $amount, SavingsEntry::INTEREST, $reference, [
                'source' => 'system', 'narration' => $narration,
            ])];
        }, 'interest.credited');
    }

    /**
     * Explicit, auditable reversal of a COMPLETED entry (or both legs of a
     * transfer / withdrawal+fee group). Refuses to reverse twice.
     *
     * @return SavingsEntry[] the reversal entries
     */
    public function reverse(int $entryId, string $reason, string $actor = 'operator'): array
    {
        $result = DB::transaction(function () use ($entryId, $reason) {
            $original = SavingsEntry::whereKey($entryId)->lockForUpdate()->first();
            if (! $original) {
                throw SavingsException::notFound('Entry');
            }

            $entries = $original->group_ref
                ? SavingsEntry::where('group_ref', $original->group_ref)->orderBy('id')->lockForUpdate()->get()
                : collect([$original]);

            $accountIds = $entries->pluck('account_id')->unique()->sort()->values();
            $accounts = [];
            foreach ($accountIds as $id) {
                $accounts[$id] = SavingsAccount::whereKey($id)->lockForUpdate()->firstOrFail();
            }

            $reversals = [];
            foreach ($entries as $e) {
                if ($e->status !== 'COMPLETED' || in_array($e->type, [SavingsEntry::REVERSAL, SavingsEntry::OPENING], true)) {
                    throw SavingsException::invalidState('Only completed, non-reversal entries can be reversed.');
                }
                SavingsStateMachine::entry($e->status, 'REVERSED');

                $account = $accounts[$e->account_id];
                $ref = $this->newReference('REV');
                $opts = [
                    'source' => 'system', 'reverses_entry_id' => $e->id, 'group_ref' => $e->group_ref,
                    'narration' => "Reversal of {$e->reference}", 'meta' => ['reason' => $reason, 'original' => $e->reference],
                    'counter_type' => $e->type, 'reverse_counters' => true,
                ];

                if ($e->direction === SavingsEntry::CREDIT) {
                    $reversals[] = $this->debit($account, $e->amount, SavingsEntry::REVERSAL, $ref, $opts);
                    if ($e->type === SavingsEntry::DEPOSIT) {
                        $this->creditWallet($this->lockWallet($e->user_id), $e->amount, $ref, 'savings_deposit_reversal', $account);
                    }
                } else {
                    $reversals[] = $this->credit($account, $e->amount, SavingsEntry::REVERSAL, $ref, $opts);
                    if ($e->type === SavingsEntry::WITHDRAWAL) {
                        $this->debitWallet($this->lockWallet($e->user_id), $e->amount, $ref, 'savings_withdrawal_reversal', $account);
                    }
                }

                $e->status = 'REVERSED';
                $e->save();
            }

            return $reversals;
        });

        foreach ($result as $r) {
            $this->audit->record('entry.reversed', $r->user_id, $r->account_id, [
                'reversal' => $r->reference, 'reverses_entry_id' => $r->reverses_entry_id, 'reason' => $reason,
            ], $actor);
        }

        return $result;
    }

    // ------------------------------------------------------------------ internals

    private function run(int $userId, array $o, array $fingerprint, callable $body, string $auditEvent): array
    {
        $key = $o['idempotency_key'] ?? null;
        $hash = hash('sha256', json_encode($fingerprint));

        if ($key !== null && ($replay = $this->findReplay($userId, $key, $hash))) {
            return $replay;
        }

        try {
            $this->currentHash = $key !== null ? $hash : null;
            $result = DB::transaction($body, 3);
        } catch (DuplicateEntry $e) {
            return ['entry' => SavingsEntry::where('reference', $e->reference)->first(), 'replayed' => true];
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $existing = ($key !== null ? $this->findReplay($userId, $key, $hash) : null)
                    ?? $this->findByExecution($o['execution_id'] ?? null);
                if ($existing) {
                    Log::info('savings.duplicate_request', ['user_id' => $userId, 'event' => $auditEvent]);

                    return $existing;
                }
            }
            throw $e;
        }

        $entry = $result['entry'];
        $this->audit->record($auditEvent, $userId, $entry->account_id, [
            'reference' => $entry->reference, 'amount' => $entry->amount, 'type' => $entry->type,
        ], $o['actor'] ?? 'user');

        return $result + ['replayed' => false];
    }

    private function findReplay(int $userId, string $key, string $hash): ?array
    {
        $entry = SavingsEntry::where('user_id', $userId)->where('idempotency_key', $key)->first();
        if (! $entry) {
            return null;
        }
        if ($entry->request_hash !== $hash) {
            throw new SavingsException('This Idempotency-Key was already used with a different request.', 'IDEMPOTENCY_CONFLICT', 409);
        }
        Log::info('savings.duplicate_request', ['user_id' => $userId, 'reference' => $entry->reference]);

        return ['entry' => $entry, 'replayed' => true];
    }

    private function findByExecution(?int $executionId): ?array
    {
        $entry = $executionId ? SavingsEntry::where('execution_id', $executionId)->first() : null;

        return $entry ? ['entry' => $entry, 'replayed' => true] : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            && (str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE') || str_contains($msg, 'unique'));
    }

    private function lockAccount(int $id, int $userId): SavingsAccount
    {
        $account = SavingsAccount::whereKey($id)->where('user_id', $userId)->lockForUpdate()->first();
        if (! $account) {
            throw SavingsException::notFound('Savings account');
        }

        return $account;
    }

    private function lockWallet(int $userId): UserWallet
    {
        $wallet = UserWallet::where('user_id', $userId)
            ->where('currency_code', config('savings.currency'))
            ->lockForUpdate()
            ->first();

        if (! $wallet || ! $wallet->status) {
            throw new SavingsException('Your wallet is not available.', 'WALLET_UNAVAILABLE');
        }

        return $wallet;
    }

    private function applyGuard(array $o, SavingsAccount $account, string $amount): string
    {
        if (isset($o['guard'])) {
            $adjusted = ($o['guard'])($account, $amount);

            return is_string($adjusted) ? $adjusted : $amount;
        }

        return $amount;
    }

    private function applyAfter(array $o, SavingsAccount $account): void
    {
        if (isset($o['after'])) {
            ($o['after'])($account->refresh());
        }
    }

    private function debitWallet(UserWallet $wallet, string $amount, string $reference, string $kind, SavingsAccount $account): void
    {
        $balance = bcadd((string) $wallet->balance, '0', 8);
        $available = bcsub($balance, bcadd((string) ($wallet->reserved ?? '0'), '0', 8), 8);

        if (bccomp($available, $amount, 8) < 0) {
            throw SavingsException::insufficientFunds('wallet balance');
        }

        $wallet->balance = bcsub($balance, $amount, 8);
        $wallet->save();
        $this->walletTrail($wallet, 'debit', $amount, $reference, $kind, $account);
    }

    private function creditWallet(UserWallet $wallet, string $amount, string $reference, string $kind, SavingsAccount $account): void
    {
        $wallet->balance = bcadd(bcadd((string) $wallet->balance, '0', 8), $amount, 8);
        $wallet->save();
        $this->walletTrail($wallet, 'credit', $amount, $reference, $kind, $account);
    }

    private function walletTrail(UserWallet $wallet, string $type, string $amount, string $reference, string $kind, SavingsAccount $account): void
    {
        OrderTransaction::create([
            'user_wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => (string) $wallet->balance,
            'reference' => $reference,
            'metadata' => ['source' => 'savings', 'kind' => $kind, 'product' => $account->product, 'account_id' => $account->id],
        ]);
    }

    private function credit(SavingsAccount $account, string $amount, string $type, string $reference, array $o): SavingsEntry
    {
        $new = Money::add(Money::norm($account->balance), $amount);
        $this->bumpCounter($account, $o['counter_type'] ?? $type, $amount, ! empty($o['reverse_counters']));
        $account->balance = $new;
        $account->save();

        return $this->entry($account, $type, SavingsEntry::CREDIT, $amount, $new, $reference, $o);
    }

    private function debit(SavingsAccount $account, string $amount, string $type, string $reference, array $o): SavingsEntry
    {
        $balance = Money::norm($account->balance);
        $available = Money::sub($balance, Money::norm($account->held));
        if (Money::cmp($available, $amount) < 0) {
            throw SavingsException::insufficientFunds('savings balance');
        }

        $new = Money::sub($balance, $amount);
        $this->bumpCounter($account, $o['counter_type'] ?? $type, $amount, ! empty($o['reverse_counters']));
        $account->balance = $new;
        $account->save();

        return $this->entry($account, $type, SavingsEntry::DEBIT, $amount, $new, $reference, $o);
    }

    private function bumpCounter(SavingsAccount $account, string $type, string $amount, bool $reverse): void
    {
        $column = match ($type) {
            SavingsEntry::DEPOSIT, SavingsEntry::TRANSFER_IN, SavingsEntry::OPENING => 'total_deposited',
            SavingsEntry::WITHDRAWAL, SavingsEntry::TRANSFER_OUT, SavingsEntry::FEE => 'total_withdrawn',
            SavingsEntry::INTEREST => 'interest_earned',
            default => null,
        };
        if ($column === null) {
            return;
        }
        $current = Money::norm($account->{$column});
        $account->{$column} = $reverse ? Money::sub($current, $amount) : Money::add($current, $amount);
    }

    private function entry(SavingsAccount $account, string $type, string $direction, string $amount, string $balanceAfter, string $reference, array $o): SavingsEntry
    {
        return SavingsEntry::create([
            'reference' => $reference,
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'status' => 'COMPLETED',
            'source' => $o['source'] ?? null,
            'group_ref' => $o['group_ref'] ?? null,
            'idempotency_key' => $o['idempotency_key'] ?? null,
            'request_hash' => isset($o['idempotency_key']) ? $this->currentHash : null,
            'reverses_entry_id' => $o['reverses_entry_id'] ?? null,
            'execution_id' => $o['execution_id'] ?? null,
            'narration' => isset($o['narration']) ? Str::limit((string) $o['narration'], 191, '') : null,
            'meta' => $o['meta'] ?? null,
        ]);
    }

    private function newReference(string $prefix): string
    {
        return $prefix . '-' . strtoupper((string) Str::ulid());
    }
}

/** Internal signal: the unique reference was already posted (idempotent no-op). */
class DuplicateEntry extends \RuntimeException
{
    public function __construct(public readonly string $reference)
    {
        parent::__construct("Entry {$reference} already posted.");
    }
}
