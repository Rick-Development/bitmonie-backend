<?php

namespace App\Console\Commands;

use App\Models\RampTransaction;
use App\Services\QuidaxService;
use Illuminate\Console\Command;

class ReverseFailedOffRamp extends Command
{
    protected $signature = 'ramp:reverse-failed-offramp
                            {merchant_reference : The off-ramp merchant reference to reverse}
                            {--amount= : Override the recorded reversal amount}';

    protected $description = 'Reverse a stuck or failed off-ramp transfer from the Quidax main account back to the user sub-account.';

    public function handle(QuidaxService $quidaxService)
    {
        $merchantReference = (string) $this->argument('merchant_reference');

        $transaction = RampTransaction::with('user')
            ->where('merchant_reference', $merchantReference)
            ->first();

        if (!$transaction) {
            $this->error("Off-ramp transaction {$merchantReference} was not found.");
            return Command::FAILURE;
        }

        if (!$transaction->user || !$transaction->user->quidax_id) {
            $this->error('The transaction user does not have a linked Quidax account.');
            return Command::FAILURE;
        }

        if ($transaction->status === 'completed') {
            $this->error('Completed off-ramp transactions cannot be reversed with this command.');
            return Command::FAILURE;
        }

        $metadata = (array) $transaction->metadata;
        $jobMetadata = (array) ($metadata['job'] ?? []);

        if (isset($jobMetadata['manual_reversal']) && is_array($jobMetadata['manual_reversal'])) {
            $this->error('This transaction already has a recorded manual reversal attempt in metadata.');
            return Command::FAILURE;
        }

        $amount = $this->resolveAmount($transaction, $metadata, $jobMetadata);
        $currency = strtolower((string) ($transaction->from_currency ?? ''));
        $network = strtolower((string) ($transaction->network ?? ''));

        if ($amount <= 0) {
            $this->error('Could not determine a valid reversal amount. Use --amount=... to override it.');
            return Command::FAILURE;
        }

        if ($currency === '' || $network === '') {
            $this->error('The transaction is missing currency or network information.');
            return Command::FAILURE;
        }

        $response = $quidaxService->create_withdrawal('me', [
            'currency' => $currency,
            'network' => $network,
            'amount' => $amount,
            'fund_uid' => $transaction->user->quidax_id,
            'transaction_note' => "Manual ramp reversal: {$merchantReference}",
            'narration' => "Manual ramp reversal: {$merchantReference}",
        ]);

        $jobMetadata['manual_reversal'] = [
            'requested_at' => now()->toIso8601String(),
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'response' => $response,
        ];

        $transaction->metadata = array_merge($metadata, [
            'job' => $jobMetadata,
        ]);

        if ($this->isSuccessfulResponse($response)) {
            $transaction->status = 'failed';
            $transaction->save();

            $this->info("Reversal submitted successfully for {$merchantReference}.");
            $this->line('Amount: ' . $amount . ' ' . strtoupper($currency));
            $this->line('Response status: ' . ($response['status'] ?? 'unknown'));
            return Command::SUCCESS;
        }

        $transaction->save();

        $this->error('Reversal request was not accepted by Quidax.');
        $this->line('Response: ' . json_encode($response));
        return Command::FAILURE;
    }

    protected function resolveAmount(RampTransaction $transaction, array $metadata, array $jobMetadata): float
    {
        $overrideAmount = $this->option('amount');
        if ($overrideAmount !== null && $overrideAmount !== '') {
            return (float) $overrideAmount;
        }

        return (float) (
            $metadata['fees']['total']
            ?? $jobMetadata['main_account_withdrawal']['data']['amount']
            ?? 0
        );
    }

    protected function isSuccessfulResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'ok'], true);
    }
}
