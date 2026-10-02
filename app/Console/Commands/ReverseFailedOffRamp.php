<?php

namespace App\Console\Commands;

use App\Models\RampTransaction;
use App\Services\QuidaxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverseFailedOffRamp extends Command
{
    protected $signature = 'ramp:reverse-failed-offramp
                            {merchant_reference : The off-ramp merchant reference to reverse}
                            {--amount= : Override the reversal amount}
                            {--dry-run : Show what would be reversed without sending funds}';

    protected $description = 'Reverse a failed or stuck off-ramp transaction from the Quidax main account back to the user sub-account.';

    public function handle(QuidaxService $quidaxService): int
    {
        $merchantReference = (string) $this->argument('merchant_reference');
        $dryRun = (bool) $this->option('dry-run');

        Log::channel('ramp_sell')->warning(
            'Manual off-ramp reversal command started.',
            [
                'merchant_reference' => $merchantReference,
                'dry_run' => $dryRun,
            ]
        );

        /*
         * ------------------------------------------------------------
         * FIND TRANSACTION
         * ------------------------------------------------------------
         */
        $transaction = RampTransaction::with('user')
            ->where('merchant_reference', $merchantReference)
            ->first();

        if (!$transaction) {
            $this->error(
                "Off-ramp transaction {$merchantReference} was not found."
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * VALIDATE TRANSACTION TYPE
         * ------------------------------------------------------------
         */
        if ($transaction->type !== 'off_ramp') {
            $this->error(
                "Transaction {$merchantReference} is not an off-ramp transaction."
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * VALIDATE USER
         * ------------------------------------------------------------
         */
        if (!$transaction->user || !$transaction->user->quidax_id) {
            $this->error(
                'The transaction user does not have a linked Quidax account.'
            );

            Log::channel('ramp_sell')->error(
                'Manual reversal failed: user has no Quidax account.',
                [
                    'merchant_reference' => $merchantReference,
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                ]
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * VALIDATE STATUS
         * ------------------------------------------------------------
         *
         * Do not reverse transactions that are already completed.
         */
        if (in_array(
            strtolower((string) $transaction->status),
            ['completed', 'confirmed'],
            true
        )) {
            $this->error(
                "Transaction {$merchantReference} is already {$transaction->status} and cannot be reversed."
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * READ METADATA
         * ------------------------------------------------------------
         */
        $metadata = is_array($transaction->metadata)
            ? $transaction->metadata
            : [];

        $jobMetadata = is_array($metadata['job'] ?? null)
            ? $metadata['job']
            : [];

        /*
         * ------------------------------------------------------------
         * PREVENT DUPLICATE REVERSAL
         * ------------------------------------------------------------
         */
        if (
            isset($jobMetadata['manual_reversal'])
            && is_array($jobMetadata['manual_reversal'])
        ) {
            $this->error(
                'This transaction already has a recorded manual reversal attempt.'
            );

            Log::channel('ramp_sell')->warning(
                'Manual reversal blocked because reversal already exists.',
                [
                    'merchant_reference' => $merchantReference,
                    'transaction_id' => $transaction->id,
                    'manual_reversal' => $jobMetadata['manual_reversal'],
                ]
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * RESOLVE REVERSAL AMOUNT
         * ------------------------------------------------------------
         */
        $amount = $this->resolveAmount(
            $transaction,
            $metadata,
            $jobMetadata
        );

        if ($amount === null || $this->compareDecimal($amount, '0') <= 0) {
            $this->error(
                'Could not determine a valid reversal amount.'
            );

            $this->line(
                'Use --amount=... to explicitly specify the reversal amount.'
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * NORMALIZE CURRENCY / NETWORK
         * ------------------------------------------------------------
         */
        $currency = strtolower(
            trim((string) ($transaction->from_currency ?? ''))
        );

        $network = strtolower(
            trim((string) ($transaction->network ?? ''))
        );

        if ($currency === '' || $network === '') {
            $this->error(
                'The transaction is missing currency or network information.'
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * LOG REVERSAL DETAILS
         * ------------------------------------------------------------
         */
        Log::channel('ramp_sell')->warning(
            'Manual off-ramp reversal prepared.',
            [
                'merchant_reference' => $merchantReference,
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $amount,
                'currency' => $currency,
                'network' => $network,
                'fund_uid' => $transaction->user->quidax_id,
                'status' => $transaction->status,
                'dry_run' => $dryRun,
            ]
        );

        $this->info(
            "Transaction: {$merchantReference}"
        );

        $this->line(
            "Amount: {$amount} " . strtoupper($currency)
        );

        $this->line(
            "Network: " . strtoupper($network)
        );

        $this->line(
            "Destination: {$transaction->user->quidax_id}"
        );

        /*
         * ------------------------------------------------------------
         * DRY RUN
         * ------------------------------------------------------------
         */
        if ($dryRun) {
            $this->warn(
                'DRY RUN: No funds were transferred.'
            );

            return Command::SUCCESS;
        }

        /*
         * ------------------------------------------------------------
         * CONFIRM OPERATION
         * ------------------------------------------------------------
         */
        if (!$this->confirm(
            'Do you want to send this reversal from the main Quidax account?'
        )) {
            $this->warn('Reversal cancelled.');

            return Command::SUCCESS;
        }

        /*
         * ------------------------------------------------------------
         * SUBMIT REVERSAL
         * ------------------------------------------------------------
         */
        try {
            $response = $quidaxService->create_withdrawal(
                'me',
                [
                    'currency' => $currency,
                    'network' => $network,
                    'amount' => $amount,
                    'fund_uid' => $transaction->user->quidax_id,
                    'transaction_note' =>
                        "Manual ramp reversal: {$merchantReference}",
                    'narration' =>
                        "Manual ramp reversal: {$merchantReference}",
                ],
                $merchantReference . '_manual_reversal'
            );

            Log::channel('ramp_sell')->info(
                'Manual off-ramp reversal response received.',
                [
                    'merchant_reference' => $merchantReference,
                    'transaction_id' => $transaction->id,
                    'response' => $response,
                ]
            );
        } catch (Throwable $e) {
            Log::channel('ramp_sell')->error(
                'Manual off-ramp reversal threw an exception.',
                [
                    'merchant_reference' => $merchantReference,
                    'transaction_id' => $transaction->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            $this->error(
                'Reversal failed with an exception: ' . $e->getMessage()
            );

            return Command::FAILURE;
        }

        /*
         * ------------------------------------------------------------
         * RECORD REVERSAL ATTEMPT
         * ------------------------------------------------------------
         */
        $jobMetadata['manual_reversal'] = [
            'requested_at' => now()->toIso8601String(),
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'response' => $response,
        ];

        $transaction->metadata = array_merge(
            $metadata,
            [
                'job' => $jobMetadata,
            ]
        );

        /*
         * ------------------------------------------------------------
         * PROVIDER ACCEPTED REVERSAL
         * ------------------------------------------------------------
         */
        if ($this->isSuccessfulResponse($response)) {
            $transaction->status = 'failed';

            if ($this->hasColumn('provider_status')) {
                $transaction->provider_status = 'failed';
            }

            if ($this->hasColumn('provider_status_checked_at')) {
                $transaction->provider_status_checked_at = now();
            }

            $transaction->save();

            Log::channel('ramp_sell')->warning(
                'Manual off-ramp reversal submitted successfully.',
                [
                    'merchant_reference' => $merchantReference,
                    'transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'network' => $network,
                    'response' => $response,
                ]
            );

            $this->info(
                "Reversal submitted successfully for {$merchantReference}."
            );

            $this->line(
                "Amount: {$amount} " . strtoupper($currency)
            );

            $this->line(
                'Provider status: ' .
                ($response['status'] ?? 'unknown')
            );

            return Command::SUCCESS;
        }

        /*
         * ------------------------------------------------------------
         * PROVIDER REJECTED REVERSAL
         * ------------------------------------------------------------
         *
         * Do NOT mark the transaction as failed merely because the
         * reversal request was rejected.
         */
        $transaction->save();

        Log::channel('ramp_sell')->error(
            'Manual off-ramp reversal rejected by Quidax.',
            [
                'merchant_reference' => $merchantReference,
                'transaction_id' => $transaction->id,
                'response' => $response,
            ]
        );

        $this->error(
            'Reversal request was not accepted by Quidax.'
        );

        $this->line(
            'Provider response: ' . json_encode(
                $response,
                JSON_PRETTY_PRINT
            )
        );

        return Command::FAILURE;
    }

    /**
     * Resolve the amount to reverse.
     *
     * Priority:
     * 1. Explicit --amount
     * 2. Original main-account withdrawal amount
     * 3. Transaction from_amount
     */
    protected function resolveAmount(
        RampTransaction $transaction,
        array $metadata,
        array $jobMetadata
    ): ?string {
        /*
         * Explicit manual override.
         */
        $overrideAmount = $this->option('amount');

        if ($overrideAmount !== null && $overrideAmount !== '') {
            return $this->normalizeDecimal($overrideAmount);
        }

        /*
         * Prefer the exact amount that was moved
         * from the user's account to the main account.
         */
        $mainWithdrawalAmount = data_get(
            $jobMetadata,
            'main_account_withdrawal.data.amount'
        );

        if (
            $mainWithdrawalAmount !== null
            && $mainWithdrawalAmount !== ''
        ) {
            return $this->normalizeDecimal($mainWithdrawalAmount);
        }

        /*
         * Fallback to the transaction source amount.
         */
        if (
            $transaction->from_amount !== null
            && $transaction->from_amount !== ''
        ) {
            return $this->normalizeDecimal(
                $transaction->from_amount
            );
        }

        /*
         * Last fallback.
         */
        $feeAmount = data_get(
            $metadata,
            'fees.total'
        );

        if ($feeAmount !== null && $feeAmount !== '') {
            return $this->normalizeDecimal($feeAmount);
        }

        return null;
    }

    /**
     * Determine whether Quidax accepted the withdrawal request.
     */
    protected function isSuccessfulResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $status = strtolower(
            trim((string) ($response['status'] ?? ''))
        );

        return in_array(
            $status,
            ['success', 'ok'],
            true
        );
    }

    /**
     * Normalize crypto amount without using float arithmetic.
     */
    protected function normalizeDecimal($value): string
    {
        $value = trim((string) $value);

        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        if (function_exists('bcadd')) {
            return bcadd($value, '0', 18);
        }

        return $value;
    }

    /**
     * Compare decimal values safely.
     */
    protected function compareDecimal(
        string $left,
        string $right
    ): int {
        if (function_exists('bccomp')) {
            return bccomp(
                $this->normalizeDecimal($left),
                $this->normalizeDecimal($right),
                18
            );
        }

        return (float) $left <=> (float) $right;
    }

    /**
     * Check whether a column exists before writing to it.
     */
    protected function hasColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = \Schema::getColumnListing(
                'ramp_transactions'
            );
        }

        return in_array($column, $columns, true);
    }
}


// namespace App\Console\Commands;

// use App\Models\RampTransaction;
// use App\Services\QuidaxService;
// use Illuminate\Console\Command;

// class ReverseFailedOffRamp extends Command
// {
//     protected $signature = 'ramp:reverse-failed-offramp
//                             {merchant_reference : The off-ramp merchant reference to reverse}
//                             {--amount= : Override the recorded reversal amount}';

//     protected $description = 'Reverse a stuck or failed off-ramp transfer from the Quidax main account back to the user sub-account.';

//     public function handle(QuidaxService $quidaxService)
//     {
//         $merchantReference = (string) $this->argument('merchant_reference');

//         $transaction = RampTransaction::with('user')
//             ->where('merchant_reference', $merchantReference)
//             ->first();

//         if (!$transaction) {
//             $this->error("Off-ramp transaction {$merchantReference} was not found.");
//             return Command::FAILURE;
//         }

//         if (!$transaction->user || !$transaction->user->quidax_id) {
//             $this->error('The transaction user does not have a linked Quidax account.');
//             return Command::FAILURE;
//         }

//         if ($transaction->status === 'completed') {
//             $this->error('Completed off-ramp transactions cannot be reversed with this command.');
//             return Command::FAILURE;
//         }

//         $metadata = (array) $transaction->metadata;
//         $jobMetadata = (array) ($metadata['job'] ?? []);

//         if (isset($jobMetadata['manual_reversal']) && is_array($jobMetadata['manual_reversal'])) {
//             $this->error('This transaction already has a recorded manual reversal attempt in metadata.');
//             return Command::FAILURE;
//         }

//         $amount = $this->resolveAmount($transaction, $metadata, $jobMetadata);
//         $currency = strtolower((string) ($transaction->from_currency ?? ''));
//         $network = strtolower((string) ($transaction->network ?? ''));

//         if ($amount <= 0) {
//             $this->error('Could not determine a valid reversal amount. Use --amount=... to override it.');
//             return Command::FAILURE;
//         }

//         if ($currency === '' || $network === '') {
//             $this->error('The transaction is missing currency or network information.');
//             return Command::FAILURE;
//         }

//         $response = $quidaxService->create_withdrawal('me', [
//             'currency' => $currency,
//             'network' => $network,
//             'amount' => $amount,
//             'fund_uid' => $transaction->user->quidax_id,
//             'transaction_note' => "Manual ramp reversal: {$merchantReference}",
//             'narration' => "Manual ramp reversal: {$merchantReference}",
//         ]);

//         $jobMetadata['manual_reversal'] = [
//             'requested_at' => now()->toIso8601String(),
//             'amount' => $amount,
//             'currency' => $currency,
//             'network' => $network,
//             'response' => $response,
//         ];

//         $transaction->metadata = array_merge($metadata, [
//             'job' => $jobMetadata,
//         ]);

//         if ($this->isSuccessfulResponse($response)) {
//             $transaction->status = 'failed';
//             $transaction->save();

//             $this->info("Reversal submitted successfully for {$merchantReference}.");
//             $this->line('Amount: ' . $amount . ' ' . strtoupper($currency));
//             $this->line('Response status: ' . ($response['status'] ?? 'unknown'));
//             return Command::SUCCESS;
//         }

//         $transaction->save();

//         $this->error('Reversal request was not accepted by Quidax.');
//         $this->line('Response: ' . json_encode($response));
//         return Command::FAILURE;
//     }

//     protected function resolveAmount(RampTransaction $transaction, array $metadata, array $jobMetadata): float
//     {
//         $overrideAmount = $this->option('amount');
//         if ($overrideAmount !== null && $overrideAmount !== '') {
//             return (float) $overrideAmount;
//         }

//         return (float) (
//             $metadata['fees']['total']
//             ?? $jobMetadata['main_account_withdrawal']['data']['amount']
//             ?? 0
//         );
//     }

//     protected function isSuccessfulResponse($response): bool
//     {
//         if (!is_array($response)) {
//             return false;
//         }

//         return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'ok'], true);
//     }
// }