<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Admin\BasicSettings;
use App\Models\User;
use App\Services\SafeHavenService;
use App\Services\Savings\Contracts\SavingsFundingProviderInterface;
use Illuminate\Support\Str;

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
            throw new SavingsException(
                'Savings deposit amount must be greater than zero.',
                'INVALID_AMOUNT'
            );
        }

        $virtualAccount = $this->safeHavenService->getUserSubAccount($user);

        if (
            !$virtualAccount ||
            empty($virtualAccount->account_number)
        ) {
            throw new SavingsException(
                'SafeHaven account not found for user.',
                'PROVIDER_ACCOUNT_MISSING',
                422
            );
        }

        $settings = BasicSettings::first();

        $mainAccount = $settings?->safehaven_debit_account;

        if (empty($mainAccount)) {
            throw new SavingsException(
                'Savings funding provider is not configured. Please try again later.',
                'PROVIDER_NOT_CONFIGURED',
                503,
                true
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
            throw new SavingsException(
                'Unable to verify savings funding account. Please try again later.',
                'PROVIDER_ENQUIRY_FAILED',
                502,
                true
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
        } catch (SavingsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SavingsException(
                $e->getMessage() ?: 'Savings deposit transfer failed.',
                'PROVIDER_TRANSFER_FAILED',
                502,
                true
            );
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
            throw new SavingsException(
                'Savings withdrawal amount must be greater than zero.',
                'INVALID_AMOUNT'
            );
        }

        $virtualAccount = $this->safeHavenService->getUserSubAccount($user);

        if (
            !$virtualAccount ||
            empty($virtualAccount->account_number)
        ) {
            throw new SavingsException(
                'SafeHaven account not found for user.',
                'PROVIDER_ACCOUNT_MISSING',
                422
            );
        }

        $settings = BasicSettings::first();

        $mainAccount = $settings?->safehaven_debit_account;

        if (empty($mainAccount)) {
            throw new SavingsException(
                'Savings funding provider is not configured. Please try again later.',
                'PROVIDER_NOT_CONFIGURED',
                503,
                true
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
            throw new SavingsException(
                'Unable to verify savings funding account. Please try again later.',
                'PROVIDER_ENQUIRY_FAILED',
                502,
                true
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
        } catch (SavingsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SavingsException(
                $e->getMessage() ?: 'Savings withdrawal transfer failed.',
                'PROVIDER_TRANSFER_FAILED',
                502,
                true
            );
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

            throw new SavingsException(
                "{$operation} failed: {$message}",
                'PROVIDER_TRANSFER_FAILED',
                502,
                true
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
            throw new SavingsException(
                'SafeHaven transfer session ID is required.',
                'PROVIDER_STATUS_INVALID'
            );
        }

        if (trim($paymentReference) === '') {
            throw new SavingsException(
                'SafeHaven payment reference is required.',
                'PROVIDER_STATUS_INVALID'
            );
        }

        return $this->safeHavenService->transferStatus([
            'sessionId' => $sessionId,
            'paymentReference' => $paymentReference,
        ]);
    }
}