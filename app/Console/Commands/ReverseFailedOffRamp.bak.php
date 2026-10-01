<?php

namespace App\Console\Commands;

use App\Models\RampTransaction;
use App\Services\QuidaxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Class ReverseFailedOffRamp
 *
 * This command manually reverses a failed or stuck off-ramp transaction
 * by sending funds from the platform's main Quidax account back into
 * the user's Quidax sub-account.
 *
 * It is intended for operational recovery when:
 * - user funds were moved internally
 * - external payout failed
 * - funds remain inside the platform wallet
 */
class ReverseFailedOffRamp extends Command
{
    /**
     * Command signature.
     *
     * merchant_reference:
     * Unique transaction identifier to locate the failed off-ramp.
     *
     * --amount:
     * Optional manual override for reversal amount.
     */
       protected $signature = 'ramp:reverse-failed-offramp';
    // protected $signature = 'ramp:reverse-failed-offramp
    //                         {merchant_reference : The off-ramp merchant reference to reverse}
    //                         {--amount= : Override the recorded reversal amount}';

    /**
     * Console command description.
     */
    protected $description = 'Reverse a stuck or failed off-ramp transfer from the Quidax main account back to the user sub-account.';

    /**
     * Execute the reversal process.
     *
     * @param QuidaxService $quidaxService
     * @return int
     */
    public function handle(QuidaxService $quidaxService)
    {
        
        
    RampTransaction::with('user')
    ->whereIn('status', ['awaiting_payout', 'pending'])
    ->update([
        'status' => 'confirmed'
    ]);
    
    return;
        
        $rampTransactions = RampTransaction::with('user')
            ->whereIn('status', ['awaiting_payout', 'pending'])
            ->get();
            
        

    foreach ($rampTransactions as $rampTransaction) {
        
        // Retrieve merchant reference from artisan argument
        $merchantReference = (string) $rampTransaction -> merchant_reference;

        // Record command execution start
        Log::info('Manual off-ramp reversal command started.', [
            'merchant_reference' => $merchantReference,
        ]);

        // Find the transaction and eager load the related user
        $transaction = RampTransaction::with('user')
            ->where('merchant_reference', $merchantReference)
            ->first();
            

        // Stop if no transaction was found
        if (!$transaction) {
            Log::error('Off-ramp reversal failed: transaction not found.', [
                'merchant_reference' => $merchantReference,
            ]);

            $this->error("Off-ramp transaction {$merchantReference} was not found.");
            return Command::FAILURE;
        }

        // Log successful transaction retrieval
        Log::info('Off-ramp transaction located.', [
            'merchant_reference' => $merchantReference,
            'transaction_id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'status' => $transaction->status,
        ]);

        // Validate the user has a Quidax sub-account
        if (!$transaction->user || !$transaction->user->quidax_id) {
            Log::error('Off-ramp reversal failed: user missing Quidax account.', [
                'merchant_reference' => $merchantReference,
                'user_id' => $transaction->user_id,
            ]);

            $this->error('The transaction user does not have a linked Quidax account.');
            return Command::FAILURE;
        }

        // Prevent reversal of already completed transactions
        if ($transaction->status === 'confirmed') {
            Log::error('Off-ramp reversal blocked: transaction already completed.', [
                'merchant_reference' => $merchantReference,
                'transaction_id' => $transaction->id,
            ]);

            $this->error('Completed off-ramp transactions cannot be reversed with this command.');
            return Command::FAILURE;
        }

        // Safely extract metadata arrays
        $metadata = (array) $transaction->metadata;
        $jobMetadata = (array) ($metadata['job'] ?? []);

        // Prevent duplicate reversal requests
        if (isset($jobMetadata['manual_reversal']) && is_array($jobMetadata['manual_reversal'])) {
            Log::error('Off-ramp reversal blocked: duplicate manual reversal attempt.', [
                'merchant_reference' => $merchantReference,
                'transaction_id' => $transaction->id,
            ]);

            $this->error('This transaction already has a recorded manual reversal attempt in metadata.');
            return Command::FAILURE;
        }

        // Determine amount to reverse
        $amount = $this->resolveAmount($transaction, $metadata, $jobMetadata);

        // Normalize transaction values
        $currency = strtolower((string) ($transaction->from_currency ?? ''));
        $network = strtolower((string) ($transaction->network ?? ''));

        // Validate amount
        if ($amount <= 0) {
            Log::error('Off-ramp reversal failed: invalid amount.', [
                'merchant_reference' => $merchantReference,
                'amount' => $amount,
            ]);

            $this->error('Could not determine a valid reversal amount. Use --amount=... to override it.');
            return Command::FAILURE;
        }

        // Validate transaction currency/network
        if ($currency === '' || $network === '') {
            Log::error('Off-ramp reversal failed: missing currency or network.', [
                'merchant_reference' => $merchantReference,
                'currency' => $currency,
                'network' => $network,
            ]);

            $this->error('The transaction is missing currency or network information.');
            return Command::FAILURE;
        }

        // Log outgoing reversal request
        Log::info('Submitting manual reversal to Quidax.', [
            'merchant_reference' => $merchantReference,
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'fund_uid' => $transaction->user->quidax_id,
        ]);

        // Send funds from main account back to user sub-account
        // $response = $quidaxService->create_withdrawal('me', [
        //     'currency' => $currency,
        //     'network' => $network,
        //     'amount' => $amount,
        //     'fund_uid' => $transaction->user->quidax_id,
        //     'transaction_note' => "Manual ramp reversal: {$merchantReference}",
        //     'narration' => "Manual ramp reversal: {$merchantReference}",
        // ]);

        // Log provider response
        // Log::info('Quidax reversal response received.', [
        //     'merchant_reference' => $merchantReference,
        //     'response' => $response,
        // ]);

        // Save reversal attempt into metadata
        // $jobMetadata['manual_reversal'] = [
        //     'requested_at' => now()->toIso8601String(),
        //     'amount' => $amount,
        //     'currency' => $currency,
        //     'network' => $network,
        //     'response' => $response,
        // ];

        // Merge updated metadata
        // $transaction->metadata = array_merge($metadata, [
        //     'job' => $jobMetadata,
        // ]);

        // If provider accepted the request
        // if ($this->isSuccessfulResponse($response)) {
        //     $transaction->status = 'failed';
        //     $transaction->save();

        //     Log::info('Manual reversal completed successfully.', [
        //         'merchant_reference' => $merchantReference,
        //         'transaction_id' => $transaction->id,
        //     ]);

        //     $this->info("Reversal submitted successfully for {$merchantReference}.");
        //     $this->line('Amount: ' . $amount . ' ' . strtoupper($currency));
        //     $this->line('Response status: ' . ($response['status'] ?? 'unknown'));

        //     return Command::SUCCESS;
        // }
        
        $transaction->status = 'confirmed';
        // Save failed attempt for audit purposes
        $transaction->save();
         return Command::SUCCESS;

        // Log::error('Manual reversal rejected by Quidax.', [
        //     'merchant_reference' => $merchantReference,
        //     'transaction_id' => $transaction->id,
        //     'response' => $response,
        // ]);

        // $this->error('Reversal request was not accepted by Quidax.');
        // $this->line('Response: ' . json_encode($response));

        // return Command::FAILURE;
        }
    }

    /**
     * Determine reversal amount.
     *
     * Priority:
     * 1. --amount option
     * 2. stored total fee
     * 3. main account withdrawal amount
     */
    protected function resolveAmount(RampTransaction $transaction, array $metadata, array $jobMetadata): float
    {
         $overrideAmount = 0.00;
        if($transaction->type == "off_ramp"){
            $overrideAmount = $transaction->from_amount;
        }else{
            $overrideAmount = $transaction->to_amount;
        }

        if ($overrideAmount !== null && $overrideAmount !== '') {
            return (float) $overrideAmount;
        }

        return (float) (
            $metadata['fees']['total']
            ?? $jobMetadata['main_account_withdrawal']['data']['amount']
            ?? 0
        );
    }

    /**
     * Check whether provider response indicates success.
     */
    protected function isSuccessfulResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'ok'], true);
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