<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Admin\BasicSettings;
use App\Models\User;
use App\Services\SafeHavenService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class SafeHavenSavingsProvider implements SavingsFundingProviderInterface
{
    public function __construct(
        protected SafeHavenService $safeHavenService
    ) {
    }

    /**
     * Deposit/fund savings.
     *
     * User SafeHaven Sub-Account
     *          ↓
     * Platform SafeHaven Main Account
     */
    public function deposit(
        User $user,
        string $amount,
        string $reference,
        $narration='Savings Deposit',
    ): array {
        if (bccomp($amount, '0', 8) <= 0) {
            throw new RuntimeException(
                'Savings deposit amount must be greater than zero.'
            );
        }

        $virtualAccount = $this->safeHavenService->getUserSubAccount($user);

        if (
            !$virtualAccount ||
            empty($virtualAccount->account_number)
        ) {
            throw new RuntimeException(
                'SafeHaven account not found for user.'
            );
        }

        $settings = BasicSettings::first();

        $mainAccount = $settings?->safehaven_debit_account;

        if (empty($mainAccount)) {
            throw new RuntimeException(
                'SafeHaven main account is not configured.'
            );
        }

        $bankCode = $virtualAccount->bank_code;

        /*
         * Name enquiry against the platform's
         * SafeHaven main account.
         */
        $enquiry = $this->safeHavenService->nameEnquiry(
            $bankCode,
            $mainAccount
        );
        

        $sessionId =
            $enquiry['sessionId']
            ?? $enquiry['sessionID']
            ?? null;

        if (empty($sessionId)) {
            throw new RuntimeException(
                'Unable to obtain SafeHaven name enquiry session for savings deposit.'
            );
        }

        /*
         * Transfer:
         *
         * User SafeHaven Sub-Account
         *          ↓
         * Platform SafeHaven Main Account
         */
        try {
            $response = $this->safeHavenService->transfer([
                'nameEnquiryReference' => $sessionId,
                'paymentReference' => $reference ?: (string) Str::uuid(),
                'amount' => (float) $amount,
                'debitAccountNumber' => $virtualAccount->account_number,
                'beneficiaryAccountNumber' => $mainAccount,
                'beneficiaryBankCode' => $bankCode,
                'saveBeneficiary' => false,
                'narration' => $narration,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage() ?: 'Savings deposit transfer failed.', 0, $e);
        }

        return $this->ensureTransferSucceeded(
            $response,
            'Savings deposit'
        );
    }

    /**
     * Withdraw savings.
     *
     * Platform SafeHaven Main Account
     *          ↓
     * User SafeHaven Sub-Account
     */
    public function withdraw(
        User $user,
        string $amount,
        string $reference,
        string $narration = 'Savings Withdrawal',
    ): array {
        if (bccomp($amount, '0', 8) <= 0) {
            throw new RuntimeException(
                'Savings withdrawal amount must be greater than zero.'
            );
        }

        $virtualAccount = $this->safeHavenService->getUserSubAccount($user);

        if (
            !$virtualAccount ||
            empty($virtualAccount->account_number)
        ) {
            throw new RuntimeException(
                'SafeHaven account not found for user.'
            );
        }

        $settings = BasicSettings::first();

        $mainAccount = $settings?->safehaven_debit_account;

        if (empty($mainAccount)) {
            throw new RuntimeException(
                'SafeHaven main account is not configured.'
            );
        }

        $bankCode = $virtualAccount->bank_code;

        /*
         * Name enquiry against the user's
         * SafeHaven sub-account.
         */
        $enquiry = $this->safeHavenService->nameEnquiry(
            $bankCode,
            $virtualAccount->account_number
        );

        $sessionId =
            $enquiry['sessionId']
            ?? $enquiry['sessionID']
            ?? null;

        if (empty($sessionId)) {
            throw new RuntimeException(
                'Unable to obtain SafeHaven name enquiry session for savings withdrawal.'
            );
        }

        /*
         * Transfer:
         *
         * Platform SafeHaven Main Account
         *          ↓
         * User SafeHaven Sub-Account
         */
        try {
            $response = $this->safeHavenService->transfer([
                'nameEnquiryReference' => $sessionId,
                'paymentReference' => $reference ?: (string) Str::uuid(),
                'amount' => (float) $amount,
                'debitAccountNumber' => $mainAccount,
                'beneficiaryAccountNumber' => $virtualAccount->account_number,
                'beneficiaryBankCode' => $bankCode,
                'saveBeneficiary' => false,
                'narration' => $narration,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage() ?: 'Savings withdrawal transfer failed.', 0, $e);
        }

        return $this->ensureTransferSucceeded(
            $response,
            'Savings withdrawal'
        );
    }

    /**
     * Ensure the SafeHaven transfer did not enter
     * a terminal failure state.
     *
     * SafeHaven transfer statuses:
     * Created
     * Initiated
     * Processing
     * Completed
     * Canceled
     * Failed
     */
    protected function ensureTransferSucceeded(
        array $response,
        string $operation,
    ): array {
        $status = strtolower(
            trim((string) ($response['status'] ?? ''))
        );

        if (in_array($status, ['failed', 'canceled'], true)) {
            $message = $response['responseMessage']
                ?? $response['message']
                ?? "{$operation} failed.";

            throw new RuntimeException(
                "{$operation} failed: {$message}"
            );
        }

        return $response;
    }

/**
 * Get SafeHaven transfer status.
 *
 * POST /transfers/status
 *
 * {
 *     "sessionId": "...",
 *     "paymentReference": "..."
 * }
 */
public function getTransferStatus(
    string $sessionId,
    string $paymentReference
): array {
    if (trim($sessionId) === '') {
        throw new RuntimeException(
            'SafeHaven transfer session ID is required.'
        );
    }

    if (trim($paymentReference) === '') {
        throw new RuntimeException(
            'SafeHaven payment reference is required.'
        );
    }

    return $this->safeHavenService->transferStatus([
        'sessionId' => $sessionId,
        'paymentReference' => $paymentReference,
    ]);
}

}