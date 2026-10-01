<?php

namespace App\Services;

use App\Models\Admin\Admin;
use App\Models\Admin\Currency;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\WalletLedger;
use App\Models\WalletWithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralWalletService
{
    public function currency(): string
    {
        return (string) config('referral_wallet.currency', 'NGN');
    }

    public function getBalanceSummary(User $user): array
    {
        $metrics = $this->getMetrics($user);

        return [
            'currency' => $this->currency(),
            'total_earned' => (float) $metrics['total_earned'],
            'available_for_withdrawal' => (float) $metrics['available'],
            'pending_withdrawal' => (float) $metrics['pending'],
            'completed_withdrawals' => (float) $metrics['completed_withdrawals'],
            'ledger_balance' => (float) $metrics['ledger_balance'],
            'wallet_balance' => (float) $metrics['wallet_balance'],
        ];
    }

    public function convertToNaira(string $amount): array
    {
        $normalizedAmount = $this->normalizeAmount($amount);
        $exchangeRate = $this->normalizeAmount((string) config('referral_wallet.naira_exchange_rate', 1));
        $convertedAmount = $this->mul($normalizedAmount, $exchangeRate);

        return [
            'source_amount' => (float) $normalizedAmount,
            'source_currency' => (string) config('referral_wallet.earning_currency', 'NGN'),
            'target_currency' => 'NGN',
            'exchange_rate' => (float) $exchangeRate,
            'converted_amount' => (float) $convertedAmount,
        ];
    }

    public function processConversion(User $user, string $amount): array
    {
        return DB::transaction(function () use ($user, $amount) {
            $normalizedAmount = $this->normalizeAmount($amount);
            $wallet = $this->getWallet($user, true);
            $metrics = $this->getMetrics($user, $wallet, true);

            // 1. Validate they have enough referral earnings
            $this->validateWithdrawalAmount($normalizedAmount, $metrics['available']);

            // 2. Perform SafeHaven Transfer from Master to SubAccount
            $safeHavenService = app(\App\Services\SafeHavenService::class);
            $userSubAccount = $safeHavenService->getUserSubAccount($user);

            if (!$userSubAccount || empty($userSubAccount->account_number)) {
                throw new RuntimeException('You must have a SafeHaven sub-account to convert earnings.');
            }

            $settings = \App\Models\Admin\BasicSettings::first();
            $masterAccount = $settings->safehaven_debit_account ?? config('services.safeHeaven.main_account');
            
            if (!$masterAccount) {
                throw new RuntimeException('Master SafeHaven account is not configured.');
            }

            $safeHavenBankCode = config('services.safeHeaven.bank_code', '090281');

            // Name Enquiry
            $enquiry = $safeHavenService->nameEnquiry($safeHavenBankCode, $userSubAccount->account_number);
            $sessionId = $enquiry['sessionId'] ?? ($enquiry['sessionID'] ?? null);

            // Transfer Payload
            $reference = $this->generateReference('CNV');
            $payload = [
                'nameEnquirySessionId' => $sessionId,
                'paymentReference' => $reference,
                'amount' => $normalizedAmount,
                'debitAccountNumber' => $masterAccount,
                'creditAccountNumber' => $userSubAccount->account_number,
                'creditBankCode' => $safeHavenBankCode,
                'narration' => 'Referral Earnings Conversion',
            ];

            // Perform transfer
            $safeHavenService->transfer($payload);

            // 3. Mark in Referral Ledger as converted (withdrawn from referral balance)
            WalletLedger::create([
                'user_id' => $user->id,
                'amount' => $normalizedAmount,
                'currency_code' => $this->currency(),
                'transaction_type' => 'withdraw', 
                'status' => 'completed',
                'balance_before' => $metrics['available'],
                'balance_after' => $this->sub($metrics['available'], $normalizedAmount),
                'pending_before' => $metrics['pending'],
                'pending_after' => $metrics['pending'],
                'reference' => $reference,
                'description' => 'Earnings converted and transferred to SafeHaven account',
                'metadata' => [
                    'safehaven_transfer_reference' => $payload['paymentReference'],
                ],
                'transaction_at' => now(),
            ]);

            // IMPORTANT: Since SafeHaven will send a credit webhook, we MUST debit the local UserWallet NOW
            // so that the incoming webhook effectively balances it out and doesn't double-credit the user.
            \App\Services\WalletService::debit($wallet->id, $normalizedAmount, $reference . '-DEBIT', [
                'type' => 'referral_conversion_debit',
                'description' => 'Debit for referral conversion transfer (awaiting SafeHaven settlement)',
            ]);

            return [
                'source_amount' => (float) $normalizedAmount,
                'converted_amount' => (float) $normalizedAmount,
                'status' => 'processing',
                'message' => 'Conversion initiated. Funds have been transferred to your SafeHaven account.',
            ];
        });
    }

    public function recordEarning(
        User $user,
        string $amount,
        string $reference,
        array $metadata = [],
        ?Referral $referral = null
    ): WalletLedger {
        $normalizedAmount = $this->normalizeAmount($amount);
        $metrics = $this->getMetrics($user);

        return WalletLedger::create([
            'user_id' => $user->id,
            'referral_id' => $referral?->id,
            'amount' => $normalizedAmount,
            'currency_code' => $this->currency(),
            'transaction_type' => 'earn',
            'status' => 'completed',
            'balance_before' => $metrics['available'],
            'balance_after' => $this->add($metrics['available'], $normalizedAmount),
            'pending_before' => $metrics['pending'],
            'pending_after' => $metrics['pending'],
            'reference' => $reference,
            'description' => $metadata['description'] ?? 'Referral earning credited',
            'metadata' => $metadata,
            'transaction_at' => now(),
        ]);
    }

    public function requestWithdrawal(User $user, array $payload): WalletWithdrawalRequest
    {
        return DB::transaction(function () use ($user, $payload) {
            $amount = $this->normalizeAmount((string) $payload['amount']);
            $wallet = $this->getWallet($user, true);
            $metrics = $this->getMetrics($user, $wallet, true);

            $this->validateWithdrawalAmount($amount, $metrics['available']);

            $reference = $this->generateReference('RWD');

            WalletService::debitToReserve($wallet->id, $amount, $reference, [
                'type' => 'referral_withdrawal_request',
                'description' => 'Referral wallet withdrawal request created',
            ]);

            $ledger = WalletLedger::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency_code' => $this->currency(),
                'transaction_type' => 'withdraw',
                'status' => 'pending',
                'balance_before' => $metrics['available'],
                'balance_after' => $this->sub($metrics['available'], $amount),
                'pending_before' => $metrics['pending'],
                'pending_after' => $this->add($metrics['pending'], $amount),
                'reference' => $reference,
                'description' => 'Withdrawal request submitted',
                'metadata' => [
                    'bank_name' => $payload['bank_name'],
                    'account_number' => $payload['account_number'],
                    'account_name' => $payload['account_name'],
                ],
                'transaction_at' => now(),
            ]);

            return WalletWithdrawalRequest::create([
                'user_id' => $user->id,
                'wallet_ledger_id' => $ledger->id,
                'reference' => $reference,
                'amount' => $amount,
                'currency_code' => $this->currency(),
                'bank_name' => $payload['bank_name'],
                'account_number' => $payload['account_number'],
                'account_name' => $payload['account_name'],
                'status' => 'pending',
            ])->load(['ledger', 'user']);
        });
    }

    public function approveWithdrawal(int $withdrawalRequestId, ?Admin $admin = null, ?string $note = null): WalletWithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawalRequestId, $admin, $note) {
            $withdrawalRequest = WalletWithdrawalRequest::with(['ledger', 'user'])
                ->whereKey($withdrawalRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawalRequest->status !== 'pending') {
                throw new RuntimeException('Withdrawal request has already been processed.');
            }

            $wallet = $this->getWallet($withdrawalRequest->user, true);

            WalletService::commitReservedDebit($wallet->id, $this->normalizeAmount((string) $withdrawalRequest->amount), $withdrawalRequest->reference, [
                'type' => 'referral_withdrawal_approved',
                'withdrawal_request_id' => $withdrawalRequest->id,
            ]);

            $withdrawalRequest->update([
                'status' => 'completed',
                'admin_note' => $note,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
            ]);

            if ($withdrawalRequest->ledger) {
                $ledgerMetadata = $withdrawalRequest->ledger->metadata ?? [];
                $ledgerMetadata['admin_note'] = $note;
                $ledgerMetadata['reviewed_at'] = now()->toDateTimeString();
                $withdrawalRequest->ledger->update([
                    'status' => 'completed',
                    'description' => 'Withdrawal request approved',
                    'metadata' => $ledgerMetadata,
                ]);
            }

            return $withdrawalRequest->fresh(['ledger', 'user', 'reviewer']);
        });
    }

    public function rejectWithdrawal(int $withdrawalRequestId, ?Admin $admin = null, ?string $note = null): WalletWithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawalRequestId, $admin, $note) {
            $withdrawalRequest = WalletWithdrawalRequest::with(['ledger', 'user'])
                ->whereKey($withdrawalRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawalRequest->status !== 'pending') {
                throw new RuntimeException('Withdrawal request has already been processed.');
            }

            $wallet = $this->getWallet($withdrawalRequest->user, true);

            WalletService::releaseReservedToBalance($wallet->id, $this->normalizeAmount((string) $withdrawalRequest->amount), $withdrawalRequest->reference, [
                'type' => 'referral_withdrawal_rejected',
                'withdrawal_request_id' => $withdrawalRequest->id,
            ]);

            $withdrawalRequest->update([
                'status' => 'rejected',
                'admin_note' => $note,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => now(),
            ]);

            if ($withdrawalRequest->ledger) {
                $ledgerMetadata = $withdrawalRequest->ledger->metadata ?? [];
                $ledgerMetadata['admin_note'] = $note;
                $ledgerMetadata['reviewed_at'] = now()->toDateTimeString();
                $withdrawalRequest->ledger->update([
                    'status' => 'rejected',
                    'description' => 'Withdrawal request rejected',
                    'metadata' => $ledgerMetadata,
                ]);
            }

            return $withdrawalRequest->fresh(['ledger', 'user', 'reviewer']);
        });
    }

    public function getTransactionHistory(User $user, int $perPage = 20)
    {
        return WalletLedger::where('user_id', $user->id)
            ->with(['referral.referred:id,username', 'withdrawalRequest'])
            ->latest('transaction_at')
            ->paginate($perPage)
            ->through(function (WalletLedger $entry) {
                return [
                    'reference' => $entry->reference,
                    'amount' => (float) $entry->amount,
                    'currency' => $entry->currency_code,
                    'transaction_type' => $entry->transaction_type,
                    'status' => $entry->status,
                    'description' => $entry->description,
                    'balance_before' => (float) $entry->balance_before,
                    'balance_after' => (float) $entry->balance_after,
                    'pending_before' => (float) $entry->pending_before,
                    'pending_after' => (float) $entry->pending_after,
                    'transaction_at' => optional($entry->transaction_at)->format('Y-m-d H:i:s'),
                    'referred_user' => $entry->referral?->referred?->username,
                    'withdrawal' => $entry->withdrawalRequest ? [
                        'bank_name' => $entry->withdrawalRequest->bank_name,
                        'account_number' => $entry->withdrawalRequest->account_number,
                        'account_name' => $entry->withdrawalRequest->account_name,
                        'admin_note' => $entry->withdrawalRequest->admin_note,
                    ] : null,
                ];
            });
    }

    public function getWithdrawalRequests(?string $status = null, int $perPage = 20)
    {
        $query = WalletWithdrawalRequest::with(['user', 'ledger', 'reviewer'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    protected function getWallet(User $user, bool $lockForUpdate = false): UserWallet
    {
        $query = UserWallet::where('user_id', $user->id)
            ->where('currency_code', $this->currency());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $wallet = $query->first();

        if ($wallet) {
            return $wallet;
        }

        $currency = Currency::where('code', $this->currency())->first();

        if (!$currency) {
            throw new RuntimeException('Referral wallet currency is not configured in admin currencies.');
        }

        $wallet = UserWallet::create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'balance' => 0,
            'reserved' => 0,
            'status' => 1,
        ]);

        if ($lockForUpdate) {
            return UserWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }

        return $wallet;
    }

    protected function getMetrics(User $user, ?UserWallet $wallet = null, bool $lockForUpdate = false): array
    {
        $query = WalletLedger::where('user_id', $user->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $entries = $query->get(['amount', 'transaction_type', 'status']);

        $totalEarned = '0';
        $completedWithdrawals = '0';
        $pendingWithdrawals = '0';

        foreach ($entries as $entry) {
            $amount = $this->normalizeAmount((string) $entry->amount);

            if ($entry->transaction_type === 'earn' && $entry->status === 'completed') {
                $totalEarned = $this->add($totalEarned, $amount);
                continue;
            }

            if ($entry->transaction_type !== 'withdraw') {
                continue;
            }

            if ($entry->status === 'completed') {
                $completedWithdrawals = $this->add($completedWithdrawals, $amount);
            } elseif ($entry->status === 'pending') {
                $pendingWithdrawals = $this->add($pendingWithdrawals, $amount);
            }
        }

        $ledgerBalance = $this->sub($totalEarned, $completedWithdrawals);
        $ledgerAvailable = $this->sub($ledgerBalance, $pendingWithdrawals);

        if ($this->cmp($ledgerAvailable, '0') < 0) {
            $ledgerAvailable = '0';
        }

        $wallet = $wallet ?? $this->getWallet($user);
        $walletBalance = $this->normalizeAmount((string) $wallet->balance);

        return [
            'total_earned' => $totalEarned,
            'completed_withdrawals' => $completedWithdrawals,
            'pending' => $pendingWithdrawals,
            'ledger_balance' => $ledgerBalance,
            'wallet_balance' => $walletBalance,
            'available' => $this->minAmount($ledgerAvailable, $walletBalance),
        ];
    }

    protected function validateWithdrawalAmount(string $amount, string $available): void
    {
        if ($this->cmp($amount, '0') <= 0) {
            throw new RuntimeException('Withdrawal amount must be greater than zero.');
        }

        $minimum = $this->normalizeAmount((string) config('referral_wallet.min_withdrawal_amount', 0));
        if ($this->cmp($amount, $minimum) < 0) {
            throw new RuntimeException('Minimum withdrawal amount is ' . $this->currency() . ' ' . $minimum . '.');
        }

        $maximum = config('referral_wallet.max_withdrawal_amount');
        if ($maximum !== null && $maximum !== '' && $this->cmp($amount, $this->normalizeAmount((string) $maximum)) > 0) {
            throw new RuntimeException('Maximum withdrawal amount is ' . $this->currency() . ' ' . $maximum . '.');
        }

        if ($this->cmp($amount, $available) > 0) {
            throw new RuntimeException('Insufficient available referral wallet balance.');
        }
    }

    protected function generateReference(string $prefix): string
    {
        do {
            $reference = $prefix . '-' . strtoupper(Str::random(12));
        } while (
            WalletLedger::where('reference', $reference)->exists() ||
            WalletWithdrawalRequest::where('reference', $reference)->exists()
        );

        return $reference;
    }

    protected function normalizeAmount(string $amount): string
    {
        return number_format((float) $amount, 8, '.', '');
    }

    protected function add(string $left, string $right): string
    {
        return bcadd($left, $right, 8);
    }

    protected function sub(string $left, string $right): string
    {
        return bcsub($left, $right, 8);
    }

    protected function mul(string $left, string $right): string
    {
        return bcmul($left, $right, 8);
    }

    protected function cmp(string $left, string $right): int
    {
        return bccomp($left, $right, 8);
    }

    protected function minAmount(string $left, string $right): string
    {
        return $this->cmp($left, $right) <= 0 ? $left : $right;
    }
}
