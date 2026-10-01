<?php

namespace App\Jobs;

use App\Models\RampTransaction;
use App\Services\QuidaxService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRampSell implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    protected int $syncBalanceWaitSeconds = 120;
    protected int $syncBalancePollIntervalSeconds = 5;

    public $user;
    public $merchantReference;
    public $mainAccountData;
    public $sourceCurrency;
    public $sourceAmount;
    public $rampAddress;
    public $network;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $merchantReference, $mainAccountData, $sourceCurrency, $sourceAmount, $rampAddress, $network)
    {
        $this->user = $user;
        $this->merchantReference = $merchantReference;
        $this->mainAccountData = $mainAccountData;
        $this->sourceCurrency = $sourceCurrency;
        $this->sourceAmount = $sourceAmount;
        $this->rampAddress = $rampAddress;
        $this->network = $network;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(QuidaxService $quidaxService)
    {
        Log::info("ProcessRampSell: Starting for {$this->merchantReference}");

        try {
            $transaction = RampTransaction::where('merchant_reference', $this->merchantReference)->first();
            if (!$transaction || in_array($transaction->status, ['awaiting_payout', 'completed', 'failed'], true)) {
                return;
            }

            $jobMetadata = $this->getJobMetadata($transaction);
            $mainAccountResponse = $jobMetadata['main_account_withdrawal'] ?? null;

            if (!$this->isSuccessfulWithdrawalResponse($mainAccountResponse)) {
                $mainAccountResponse = $quidaxService->create_withdrawal($this->user->quidax_id, $this->mainAccountData);
                Log::info("ProcessRampSell User->Master Withdrawal", ['response' => $mainAccountResponse]);

                $jobMetadata['main_account_withdrawal'] = $mainAccountResponse;
                $jobMetadata['main_account_withdrawal_requested_at'] = now()->toIso8601String();
                $transaction = $this->updateJobMetadata($transaction, $jobMetadata);

                if (!$this->isSuccessfulWithdrawalResponse($mainAccountResponse)) {
                    $this->failTransaction(
                        "Withdrawal from user to master failed: " . ($mainAccountResponse['message'] ?? 'Unknown error'),
                        $jobMetadata
                    );
                    return;
                }
            }

            $mainWalletResponse = $quidaxService->fetchUserWallet('me', strtolower($this->sourceCurrency));
            Log::info('ProcessRampSell Main Wallet Check', [
                'merchant_reference' => $this->merchantReference,
                'response' => $mainWalletResponse,
            ]);

            $availableMainBalance = (float) ($mainWalletResponse['data']['balance'] ?? 0);
            $requiredRampBalance = (float) $this->sourceAmount;

            $jobMetadata['main_account_ready_check'] = [
                'required_balance' => (string) $requiredRampBalance,
                'available_balance' => (string) $availableMainBalance,
                'checked_at' => now()->toIso8601String(),
            ];
            $transaction = $this->updateJobMetadata($transaction, $jobMetadata);

            if (($mainWalletResponse['status'] ?? '') !== 'success' || $availableMainBalance < $requiredRampBalance) {
                if ($this->shouldBlockUntilBalanceSettles()) {
                    $mainWalletResponse = $this->waitForMainWalletBalance($quidaxService, $requiredRampBalance, $mainWalletResponse);
                    $availableMainBalance = (float) ($mainWalletResponse['data']['balance'] ?? 0);

                    $jobMetadata['main_account_ready_check'] = [
                        'required_balance' => (string) $requiredRampBalance,
                        'available_balance' => (string) $availableMainBalance,
                        'checked_at' => now()->toIso8601String(),
                    ];
                    $transaction = $this->updateJobMetadata($transaction, $jobMetadata);
                }
            }

            if (($mainWalletResponse['status'] ?? '') !== 'success' || $availableMainBalance < $requiredRampBalance) {
                if ($this->attempts() >= $this->tries || $this->shouldBlockUntilBalanceSettles()) {
                    $jobMetadata['automatic_reversal'] = $this->reverseWithdrawal(
                        $quidaxService,
                        'Timed out waiting for the Quidax main account to receive the user transfer for off-ramp settlement.'
                    );
                    $this->failTransaction(
                        'Timed out waiting for the Quidax main account to receive the user transfer for off-ramp settlement.',
                        $jobMetadata
                    );
                    return;
                }

                $delay = $this->retryDelaySeconds();
                Log::info('ProcessRampSell: Waiting for main account balance to settle before ramp withdrawal', [
                    'merchant_reference' => $this->merchantReference,
                    'attempt' => $this->attempts(),
                    'release_in_seconds' => $delay,
                    'required_balance' => $requiredRampBalance,
                    'available_balance' => $availableMainBalance,
                ]);

                $this->release($delay);
                return;
            }

            $rampResponse = $quidaxService->create_withdrawal('me', [
                'network' => strtolower($this->network),
                'amount' => $this->sourceAmount,
                'currency' => strtolower($this->sourceCurrency),
                'fund_uid' => $this->rampAddress,
                'transaction_note' => "Off-Ramp Sell: Master account pay-out to Ramp",
                'narration' => "Off-Ramp Sell: Master account pay-out to Ramp",
            ]);

            Log::info("ProcessRampSell Master->Ramp Withdrawal", ['response' => $rampResponse]);

            if (!$this->isSuccessfulWithdrawalResponse($rampResponse)) {
                $jobMetadata['last_ramp_withdrawal_error'] = $rampResponse;
                $jobMetadata['last_ramp_withdrawal_error_at'] = now()->toIso8601String();
                $transaction = $this->updateJobMetadata($transaction, $jobMetadata);

                if ($this->isInsufficientBalanceResponse($rampResponse) && $this->attempts() < $this->tries) {
                    $delay = $this->retryDelaySeconds();
                    Log::warning('ProcessRampSell: Main account withdrawal has not settled yet, retrying ramp withdrawal later', [
                        'merchant_reference' => $this->merchantReference,
                        'attempt' => $this->attempts(),
                        'release_in_seconds' => $delay,
                        'provider_message' => $rampResponse['message'] ?? null,
                        'provider_data' => $rampResponse['data'] ?? null,
                    ]);

                    $this->release($delay);
                    return;
                }

                $jobMetadata['automatic_reversal'] = $this->reverseWithdrawal($quidaxService, 'Ramp destination withdrawal failed');
                $this->failTransaction(
                    "Master to Ramp withdrawal failed: " . ($rampResponse['message'] ?? 'Unknown error'),
                    $jobMetadata
                );
                return;
            }

            $jobMetadata['ramp_withdrawal'] = $rampResponse;
            $jobMetadata['ramp_withdrawal_requested_at'] = now()->toIso8601String();

            $transaction->status = 'awaiting_payout';
            $transaction->metadata = array_merge((array) $transaction->metadata, [
                'job' => $jobMetadata,
            ]);
            $transaction->save();
        } catch (Exception $e) {
            Log::error("ProcessRampSell Error: " . $e->getMessage());
            $this->failTransaction("System Error: " . $e->getMessage());
        }
    }

    protected function getJobMetadata(RampTransaction $transaction): array
    {
        $metadata = (array) $transaction->metadata;
        $jobMetadata = $metadata['job'] ?? [];

        return is_array($jobMetadata) ? $jobMetadata : [];
    }

    protected function updateJobMetadata(RampTransaction $transaction, array $jobMetadata): RampTransaction
    {
        $transaction->metadata = array_merge((array) $transaction->metadata, [
            'job' => $jobMetadata,
        ]);
        $transaction->save();

        return $transaction->fresh();
    }

    protected function isSuccessfulWithdrawalResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'ok'], true);
    }

    protected function isInsufficientBalanceResponse(array $response): bool
    {
        $message = strtolower((string) ($response['message'] ?? ''));
        $dataMessage = strtolower((string) ($response['data']['message'] ?? ''));

        return str_contains($message, 'insufficient balance')
            || str_contains($dataMessage, 'insufficient balance');
    }

    protected function retryDelaySeconds(): int
    {
        return match (true) {
            $this->attempts() >= 8 => 300,
            $this->attempts() >= 5 => 120,
            default => 30,
        };
    }

    protected function shouldBlockUntilBalanceSettles(): bool
    {
        return config('queue.default') === 'sync';
    }

    protected function waitForMainWalletBalance(QuidaxService $quidaxService, float $requiredRampBalance, array $lastResponse = []): array
    {
        $deadline = microtime(true) + $this->syncBalanceWaitSeconds;
        $walletResponse = $lastResponse;

        while (microtime(true) < $deadline) {
            sleep($this->syncBalancePollIntervalSeconds);

            $walletResponse = $quidaxService->fetchUserWallet('me', strtolower($this->sourceCurrency));
            $availableMainBalance = (float) ($walletResponse['data']['balance'] ?? 0);

            Log::info('ProcessRampSell: Sync main wallet balance poll', [
                'merchant_reference' => $this->merchantReference,
                'required_balance' => $requiredRampBalance,
                'available_balance' => $availableMainBalance,
                'response' => $walletResponse,
            ]);

            if (($walletResponse['status'] ?? '') === 'success' && $availableMainBalance >= $requiredRampBalance) {
                return $walletResponse;
            }
        }

        return $walletResponse;
    }

    private function reverseWithdrawal(QuidaxService $quidaxService, string $reason): array
    {
        try {
            $response = $quidaxService->create_withdrawal('me', [
                'currency' => strtolower($this->sourceCurrency),
                'network' => strtolower($this->mainAccountData['network'] ?? $this->network),
                'amount' => $this->mainAccountData['amount'] ?? $this->sourceAmount,
                'fund_uid' => $this->user->quidax_id,
                'transaction_note' => "Ramp reversal: {$reason}",
                'narration' => "Ramp reversal: {$reason}",
            ]);

            Log::info('ProcessRampSell: Reversal response', [
                'merchant_reference' => $this->merchantReference,
                'response' => $response,
            ]);

            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            Log::error("ProcessRampSell Reversal Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function failTransaction($reason, array $jobMetadata = [])
    {
        $transaction = RampTransaction::where('merchant_reference', $this->merchantReference)->first();
        if ($transaction) {
            $existingJobMetadata = $this->getJobMetadata($transaction);
            $transaction->status = 'failed';
            $transaction->metadata = array_merge((array) $transaction->metadata, [
                'failure_reason' => $reason,
                'job' => array_merge($existingJobMetadata, $jobMetadata),
            ]);
            $transaction->save();
        }
    }
}
