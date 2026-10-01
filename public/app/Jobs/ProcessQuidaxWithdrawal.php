<?php

namespace App\Jobs;

use App\Models\Withdrawals;
use App\Services\QuidaxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessQuidaxWithdrawal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $mainBalanceWaitSeconds = 120;
    protected int $mainBalancePollIntervalSeconds = 5;
    protected int $destinationRetryAttempts = 3;
    protected int $destinationRetryDelaySeconds = 5;

    protected $user;
    protected $mainAccountData;
    protected $destinationData;
    protected $feeAmount;
    protected $totalAmount;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $mainAccountData, $destinationData, $feeAmount = 0, $totalAmount = 0)
    {
        $this->user = $user;
        $this->mainAccountData = $mainAccountData;
        $this->destinationData = $destinationData;
        $this->feeAmount = $feeAmount;
        $this->totalAmount = $totalAmount;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(QuidaxService $quidax)
    {
        Log::info("ProcessQuidaxWithdrawal: Starting withdrawal for user " . $this->user->id);

        // 1. Withdraw to the main account first.
        $mainAccountResponse = $quidax->create_withdrawal($this->user->quidax_id, $this->mainAccountData);
        Log::info("ProcessQuidaxWithdrawal: Main account withdrawal response", ['response' => $mainAccountResponse]);

        if (!$this->isSuccessfulResponse($mainAccountResponse)) {
            Log::error("ProcessQuidaxWithdrawal: Main account withdrawal failed.", ['response' => $mainAccountResponse]);
            return;
        }

        if (!$this->waitForMainAccountBalance($quidax)) {
            Log::warning('ProcessQuidaxWithdrawal: Timed out waiting for main account balance. Reversing withdrawal.', [
                'user_id' => $this->user->id,
                'currency' => strtolower((string) ($this->mainAccountData['currency'] ?? $this->destinationData['currency'] ?? '')),
                'required_balance' => (float) ($this->destinationData['amount'] ?? 0),
            ]);

            $this->reverseMainAccountWithdrawal($quidax, $mainAccountResponse, 'Timed out waiting for main account balance');
            return;
        }

        $response = $this->attemptDestinationWithdrawal($quidax);
        Log::info("ProcessQuidaxWithdrawal: Destination withdrawal response", ['response' => $response]);

        if ($this->isSuccessfulResponse($response)) {
            $txid = $response['data']['txid'] ?? $response['data']['id'] ?? 'unknown';

            Withdrawals::create([
                'user_id' => $this->user->id,
                'reference' => $response['data']['reference'] ?? null,
                'type' => $response['data']['type'] ?? null,
                'currency' => $response['data']['currency'] ?? null,
                'amount' => $response['data']['amount'] ?? null,
                'fee' => $this->feeAmount,
                'total' => $this->totalAmount,
                'trans_id' => $txid,
                'transaction_note' => $response['data']['transaction_note'] ?? null,
                'recipient_data' => $response['data']['recipient'] ?? null,
                'wallet' => $response['data']['wallet'] ?? null,
                'user' => $response['data']['user'] ?? null,
            ]);

            Log::info("ProcessQuidaxWithdrawal: Withdrawal record created.");
            return;
        }

        Log::warning("ProcessQuidaxWithdrawal: Destination failed. Reversing main account withdrawal.", [
            'response' => $response,
        ]);

        $this->reverseMainAccountWithdrawal($quidax, $mainAccountResponse, 'Destination withdrawal failed');
    }

    protected function waitForMainAccountBalance(QuidaxService $quidax): bool
    {
        $currency = strtolower((string) ($this->mainAccountData['currency'] ?? $this->destinationData['currency'] ?? ''));
        $requiredBalance = (float) ($this->destinationData['amount'] ?? 0);

        if ($currency === '' || $requiredBalance <= 0) {
            return true;
        }

        $deadline = microtime(true) + $this->mainBalanceWaitSeconds;

        do {
            $walletResponse = $quidax->fetchUserWallet('me', $currency);
            $availableBalance = (float) ($walletResponse['data']['balance'] ?? 0);

            Log::info('ProcessQuidaxWithdrawal: Main account balance check', [
                'user_id' => $this->user->id,
                'currency' => $currency,
                'required_balance' => $requiredBalance,
                'available_balance' => $availableBalance,
                'response' => $walletResponse,
            ]);

            if (($walletResponse['status'] ?? '') === 'success' && $availableBalance >= $requiredBalance) {
                return true;
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            sleep($this->mainBalancePollIntervalSeconds);
        } while (true);

        return false;
    }

    protected function attemptDestinationWithdrawal(QuidaxService $quidax): array
    {
        $lastResponse = [];

        for ($attempt = 1; $attempt <= $this->destinationRetryAttempts; $attempt++) {
            $lastResponse = $quidax->create_withdrawal('me', $this->destinationData);

            if ($this->isSuccessfulResponse($lastResponse)) {
                return $lastResponse;
            }

            if (!$this->isInsufficientBalanceResponse($lastResponse) || $attempt === $this->destinationRetryAttempts) {
                break;
            }

            Log::warning('ProcessQuidaxWithdrawal: Destination withdrawal hit insufficient balance, retrying.', [
                'user_id' => $this->user->id,
                'attempt' => $attempt,
                'retry_in_seconds' => $this->destinationRetryDelaySeconds,
                'response' => $lastResponse,
            ]);

            sleep($this->destinationRetryDelaySeconds);
        }

        return is_array($lastResponse) ? $lastResponse : [];
    }

    protected function reverseMainAccountWithdrawal(QuidaxService $quidax, array $mainAccountResponse, string $reason): void
    {
        $mainWithdrawalId = $mainAccountResponse['data']['id'] ?? null;

        if ($mainWithdrawalId) {
            $cancelResponse = $quidax->cancel_withdrawal($this->user->quidax_id, $mainWithdrawalId);
            Log::info("ProcessQuidaxWithdrawal: Cancel withdrawal response", ['response' => $cancelResponse]);

            if ($this->isSuccessfulResponse($cancelResponse)) {
                return;
            }
        }

        $reverseResponse = $quidax->create_withdrawal('me', [
            'currency' => strtolower((string) ($this->mainAccountData['currency'] ?? $this->destinationData['currency'] ?? '')),
            'network' => strtolower((string) ($this->mainAccountData['network'] ?? $this->destinationData['network'] ?? '')),
            'amount' => $this->mainAccountData['amount'] ?? $this->totalAmount,
            'fund_uid' => $this->user->quidax_id,
            'transaction_note' => "Withdrawal reversal: {$reason}",
            'narration' => "Withdrawal reversal: {$reason}",
        ]);

        Log::info("ProcessQuidaxWithdrawal: Manual reversal response", ['response' => $reverseResponse]);
    }

    protected function isSuccessfulResponse($response): bool
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
}
