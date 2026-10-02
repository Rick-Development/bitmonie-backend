<?php

namespace App\Jobs;

use App\Models\BushaTransaction;
use App\Services\BushaService;
use App\Services\QuidaxService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBushaSell implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $quoteId;
    public $reference;
    public $mainAccountData;
    public $sourceCurrency;
    public $targetCurrency;
    public $sourceAmount; // Amount to send to Busha
    public $totalAmount; // Amount withdrawn from User (Source + Fee)

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $quoteId, $reference, $mainAccountData, $sourceCurrency, $targetCurrency, $sourceAmount, $totalAmount)
    {
        $this->user = $user;
        $this->quoteId = $quoteId;
        $this->reference = $reference;
        $this->mainAccountData = $mainAccountData;
        $this->sourceCurrency = $sourceCurrency;
        $this->targetCurrency = $targetCurrency;
        $this->sourceAmount = $sourceAmount;
        $this->totalAmount = $totalAmount;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(BushaService $bushaService, QuidaxService $quidaxService)
    {
        Log::info("ProcessBushaSell: Starting trade execution for {$this->reference}");

        try {
            // 1. Withdraw User -> Main
            $mainAccountResponse = $quidaxService->create_withdrawal($this->user->quidax_id, $this->mainAccountData);
            Log::info("ProcessBushaSell: Main account withdrawal", ['response' => $mainAccountResponse]);

            if ($mainAccountResponse['status'] !== "success") {
                $this->failTransaction("Withdrawal from user failed: " . ($mainAccountResponse['message'] ?? 'Unknown error'));
                return;
            }

            // Wait for confirmation
            sleep(10);

            // 2. Execute Busha Quote
            try {
                $transfer = $bushaService->executeQuote($this->quoteId, $this->reference);
            } catch (Exception $e) {
                // Reverse Main Withdrawal
                $this->reverseWithdrawal($quidaxService, "Quote Execution Failed");
                $this->failTransaction("Quote execution failed: " . $e->getMessage());
                return;
            }

            // Update Transaction with Busha Order ID
            $this->updateTransactionOrderId($transfer['id'] ?? null, $transfer['data'] ?? []);

            $pay_in = $transfer['data']['pay_in'];
            $address = $pay_in['address'];
            $expires_at = $pay_in['expires_at'];
            $network = $this->normalizeNetwork($pay_in['network']);

            if ($expires_at < now()->toDateTimeString()) {
                $this->reverseWithdrawal($quidaxService, "Pay-in expired");
                $this->failTransaction("Pay-in address expired");
                return;
            }

            // 3. Withdraw Main -> Busha
            $bushaResponse = $quidaxService->create_withdrawal('me', [
                'network' => strtolower($network),
                'amount' => $this->sourceAmount,
                'currency' => strtolower($this->sourceCurrency),
                'fund_uid' => $address,
                'transaction_note' => "Trading {$this->targetCurrency} to {$this->sourceCurrency}",
                'narration' => "Trading {$this->targetCurrency} to {$this->sourceCurrency}",
            ]);

            if ($bushaResponse['status'] !== 'success') {
                $this->reverseWithdrawal($quidaxService, "Trading failed (Main -> Busha)");
                $this->failTransaction("Failed to send to Busha: " . ($bushaResponse['message'] ?? 'Unknown error'));
                return;
            }

            Log::info("ProcessBushaSell: Trade Success", ['response' => $bushaResponse]);
            // Transaction status will be updated by Webhook or we can set to 'processing'/'completed' here
            // Assuming Webhook handles final state, but we can mark as "sent"
            
        } catch (Exception $e) {
            Log::error("ProcessBushaSell Error: " . $e->getMessage());
            $this->failTransaction("System Error: " . $e->getMessage());
            // Attempt reversal if possible (complicated if state unknown)
        }
    }

    private function reverseWithdrawal(QuidaxService $quidax, $reason)
    {
        Log::info("ProcessBushaSell: Reversing withdrawal. Reason: $reason");
        $quidax->create_withdrawal('me', [
            'currency' => strtolower($this->sourceCurrency),
            'network' => strtolower($this->mainAccountData['network']), // Use original network
            'amount' => $this->totalAmount,
            'fund_uid' => $this->user->quidax_id,
            'transaction_note' => "Reversal: $reason",
            'narration' => "Reversal: $reason",
        ]);
    }

    private function failTransaction($reason)
    {
        $transaction = BushaTransaction::where('reference', $this->reference)->first();
        if ($transaction) {
            $transaction->status = 'failed';
            $transaction->metadata = array_merge($transaction->metadata ?? [], ['failure_reason' => $reason]);
            $transaction->save();
        }
    }

    private function updateTransactionOrderId($orderId, $transferData)
    {
        $transaction = BushaTransaction::where('reference', $this->reference)->first();
        if ($transaction) {
            $transaction->busha_order_id = $orderId;
            $transaction->metadata = array_merge($transaction->metadata ?? [], $transferData);
            $transaction->save();
        }
    }

    private function normalizeNetwork($network) {
        return match($network) {
            'BSC' => 'BEP20',
            'TRX' => 'TRC20',
            'Ethereum' => 'ERC20',
            default => $network
        };
    }
}
