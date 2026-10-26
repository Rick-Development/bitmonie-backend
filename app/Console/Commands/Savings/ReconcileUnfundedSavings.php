<?php

namespace App\Console\Commands\Savings;

use App\Models\SavingsAccount;
use App\Models\SavingsEntry;
use App\Models\User;
use App\Services\SafeHavenService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use App\Services\Savings\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileUnfundedSavings extends Command
{
    protected $signature = 'savings:reconcile-unfunded 
                            {--user_id= : Specific user ID to inspect} 
                            {--fix : Reset unfunded balances to 0.00, cancel affected plans, and sync wallet} 
                            {--charge : Attempt to charge SafeHaven now for unfunded balances}';

    protected $description = 'Identify and resolve savings accounts that were credited without debiting the user\'s SafeHaven bank account';

    public function handle(
        SafeHavenService $safeHavenService,
        SavingsFundingProviderInterface $fundingProvider
    ): int {
        $userId = $this->option('user_id');
        $doFix = (bool) $this->option('fix');
        $doCharge = (bool) $this->option('charge');

        if ($doFix && $doCharge) {
            $this->error('You cannot specify both --fix and --charge. Choose one action.');
            return self::FAILURE;
        }

        $query = SavingsAccount::where('balance', '>', 0);
        if ($userId) {
            $query->where('user_id', (int) $userId);
        }

        $accounts = $query->with('plan')->get();

        if ($accounts->isEmpty()) {
            $this->info('No accounts with positive savings balances found.');
            return self::SUCCESS;
        }

        $unfundedAccounts = [];

        foreach ($accounts as $account) {
            $user = User::find($account->user_id);
            if (! $user) {
                continue;
            }

            $virtualAccount = $safeHavenService->getUserSubAccount($user);
            $hasSafeHaven = $virtualAccount !== null && ! empty($virtualAccount->account_number);

            $deposits = SavingsEntry::where('account_id', $account->id)
                ->where('type', SavingsEntry::DEPOSIT)
                ->whereIn('source', ['wallet', 'manual', 'scheduled'])
                ->get();

            $unfundedEntries = [];
            $unfundedTotal = '0.00';

            foreach ($deposits as $entry) {
                $meta = $entry->meta ?? [];
                // If it has no provider_reference recorded, it was never debited at SafeHaven
                if (empty($meta['provider_reference']) && empty($meta['provider'])) {
                    $unfundedEntries[] = $entry;
                    $unfundedTotal = Money::add($unfundedTotal, Money::norm($entry->amount));
                }
            }

            if (Money::cmp($unfundedTotal, '0.00') > 0) {
                $effectiveUnfunded = Money::min($unfundedTotal, Money::norm($account->balance));

                $unfundedAccounts[] = [
                    'account' => $account,
                    'user' => $user,
                    'has_safehaven' => $hasSafeHaven,
                    'unfunded_total' => $effectiveUnfunded,
                    'entries' => $unfundedEntries,
                ];
            }
        }

        if (empty($unfundedAccounts)) {
            $this->info('All savings accounts with positive balances have verified funding. No unbacked accounts found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($unfundedAccounts as $item) {
            $acc = $item['account'];
            $rows[] = [
                'Account ID' => $acc->id,
                'User ID' => $acc->user_id,
                'Product' => $acc->product,
                'Title' => $acc->title,
                'Balance' => Money::norm($acc->balance),
                'Unfunded Amount' => $item['unfunded_total'],
                'SafeHaven SubAccount?' => $item['has_safehaven'] ? 'Yes' : 'No',
            ];
        }

        $this->warn('Found ' . count($unfundedAccounts) . ' savings account(s) credited without SafeHaven debit:');
        $this->table(['Account ID', 'User ID', 'Product', 'Title', 'Balance', 'Unfunded Amount', 'SafeHaven SubAccount?'], $rows);

        if (! $doFix && ! $doCharge) {
            $this->newLine();
            $this->line('<comment>Run with --fix to cancel these unfunded plans, reset balances to 0, and sync user wallets.</comment>');
            $this->line('<comment>Run with --charge to attempt debiting the user\'s SafeHaven sub-account now for the unfunded balance.</comment>');
            return self::SUCCESS;
        }

        if ($doFix) {
            $this->info('Resetting unfunded savings accounts and syncing wallets...');

            foreach ($unfundedAccounts as $item) {
                $acc = $item['account'];
                $user = $item['user'];
                $amountToZero = $item['unfunded_total'];

                DB::transaction(function () use ($acc, $amountToZero, $user, $safeHavenService) {
                    $locked = SavingsAccount::whereKey($acc->id)->lockForUpdate()->first();
                    $newBalance = Money::sub(Money::norm($locked->balance), $amountToZero);
                    if (Money::cmp($newBalance, '0.00') < 0) {
                        $newBalance = '0.00';
                    }

                    $locked->update([
                        'balance' => $newBalance,
                        'status' => SavingsAccount::CANCELLED,
                        'closed_at' => now(),
                    ]);

                    if ($locked->plan) {
                        $locked->plan->update([
                            'status' => 'CANCELLED',
                            'next_run_at' => null,
                        ]);
                    }

                    SavingsEntry::create([
                        'reference' => 'CORR-' . Str::uuid(),
                        'user_id' => $locked->user_id,
                        'account_id' => $locked->id,
                        'type' => 'REVERSAL',
                        'direction' => 'DEBIT',
                        'amount' => $amountToZero,
                        'balance_after' => $newBalance,
                        'status' => 'COMPLETED',
                        'source' => 'system',
                        'narration' => 'Reconciliation: reversed unbacked deposit',
                        'meta' => ['reconciliation' => 'unfunded_reset', 'timestamp' => now()->toIso8601String()],
                    ]);

                    $safeHavenService->syncUserWalletBalance($user, config('savings.currency', 'NGN'));
                });

                $this->info("Account #{$acc->id} ({$acc->title}) reset and user #{$user->id} wallet synced.");
            }

            $this->info('Reconciliation completed successfully.');
            return self::SUCCESS;
        }

        if ($doCharge) {
            $this->info('Attempting to charge SafeHaven for unfunded amounts...');

            foreach ($unfundedAccounts as $item) {
                $acc = $item['account'];
                $user = $item['user'];
                $chargeAmount = $item['unfunded_total'];

                if (! $item['has_safehaven']) {
                    $this->error("Skipping Account #{$acc->id}: User #{$user->id} has no active SafeHaven sub-account.");
                    continue;
                }

                $providerRef = 'savings:reconcile:' . Str::uuid();

                try {
                    $fundingProvider->deposit(
                        $user,
                        $chargeAmount,
                        $providerRef,
                        "Reconcile unfunded savings: {$acc->title}"
                    );

                    foreach ($item['entries'] as $entry) {
                        $entryMeta = $entry->meta ?? [];
                        $entryMeta['provider'] = 'safehaven';
                        $entryMeta['provider_reference'] = $providerRef;
                        $entry->meta = $entryMeta;
                        $entry->save();
                    }

                    $safeHavenService->syncUserWalletBalance($user, config('savings.currency', 'NGN'));

                    $this->info("Successfully charged {$chargeAmount} NGN from SafeHaven for Account #{$acc->id}.");
                } catch (\Throwable $e) {
                    $this->error("Failed to charge SafeHaven for Account #{$acc->id} (User #{$user->id}): " . $e->getMessage());
                }
            }

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }
}
