<?php

namespace App\Jobs;

use App\Mail\CryptoWithdrawalFailedAdminMail;
use App\Mail\CryptoWithdrawalNotificationMail;
use App\Models\Admin\Admin;
use App\Models\CryptoNotificationLog;
use App\Models\User;
use App\Models\Withdrawals;
use App\Services\QuidaxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessQuidaxWithdrawal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $mainWithdrawalWaitSeconds = 180;
    protected int $mainWithdrawalPollIntervalSeconds = 5;
    protected int $destinationRetryAttempts = 3;
    protected int $destinationRetryDelaySeconds = 5;

    protected $user;
    protected $mainAccountData;
    protected $destinationData;
    protected $feeAmount;
    protected $totalAmount;
    protected $pendingWithdrawalId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $mainAccountData, $destinationData, $feeAmount = 0, $totalAmount = 0, $pendingWithdrawalId = null)
    {
        $this->user = $user;
        $this->mainAccountData = $mainAccountData;
        $this->destinationData = $destinationData;
        $this->feeAmount = $feeAmount;
        $this->totalAmount = $totalAmount;
        $this->pendingWithdrawalId = $pendingWithdrawalId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(QuidaxService $quidax)
    {
        Log::info('ProcessQuidaxWithdrawal: Starting withdrawal.', $this->buildLogContext());

        $currency = strtolower((string) ($this->mainAccountData['currency'] ?? $this->destinationData['currency'] ?? ''));
        $mainAccountRecipientId = (string) ($this->mainAccountData['recipient_id'] ?? $this->mainAccountData['fund_uid'] ?? '');
        $transferAmount = $this->decimalAmount($this->mainAccountData['amount'] ?? $this->totalAmount ?? '0');
        $requiredMainBalance = $this->decimalAmount($this->destinationData['amount'] ?? $transferAmount);

        if ($currency === '' || $mainAccountRecipientId === '' || $this->compareCryptoAmounts($transferAmount, '0') <= 0) {
            Log::error('ProcessQuidaxWithdrawal: Invalid main account transfer configuration.', [
                'main_account_data' => $this->mainAccountData,
                'destination_data' => $this->destinationData,
            ] + $this->buildLogContext());
            $this->markPendingWithdrawalFailed('Invalid main account transfer configuration', $this->mainAccountData);
            return;
        }

        // 1. Move the funds from the user sub-account to the main account using Quidax internal transfer.
        $mainAccountResponse = $quidax->transfer(
            $this->user->quidax_id,
            $mainAccountRecipientId,
            $this->formatCryptoAmount($transferAmount),
            $currency,
            $this->mainAccountData['reference'] ?? null,
            $this->mainAccountData['transaction_note'] ?? 'Internal transfer to main account'
        );
        Log::info('ProcessQuidaxWithdrawal: Main account transfer response.', [
            'response' => $mainAccountResponse,
        ] + $this->buildLogContext());

        if (!$this->isSuccessfulResponse($mainAccountResponse)) {
            Log::error('ProcessQuidaxWithdrawal: Main account transfer failed.', [
                'response' => $mainAccountResponse,
            ] + $this->buildLogContext());
            $this->markPendingWithdrawalFailed(
                $this->extractFailureReason($mainAccountResponse, 'Main account transfer failed'),
                $mainAccountResponse
            );
            return;
        }

        $mainWalletResponse = $quidax->fetchUserWallet('me', $currency);
        $availableMainBalance = $this->decimalAmount($mainWalletResponse['data']['balance'] ?? '0');

        Log::info('ProcessQuidaxWithdrawal: Main account balance check', [
            'currency' => $currency,
            'required_balance' => $this->formatCryptoAmount($requiredMainBalance),
            'available_balance' => $this->formatCryptoAmount($availableMainBalance),
            'response' => $mainWalletResponse,
        ] + $this->buildLogContext());

        if (($mainWalletResponse['status'] ?? '') !== 'success' || $this->compareCryptoAmounts($availableMainBalance, $requiredMainBalance) < 0) {
            $mainWalletResponse = $this->waitForMainAccountBalance($quidax, $currency, $requiredMainBalance, $mainWalletResponse);
            $availableMainBalance = $this->decimalAmount($mainWalletResponse['data']['balance'] ?? '0');
        }

        if (($mainWalletResponse['status'] ?? '') !== 'success' || $this->compareCryptoAmounts($availableMainBalance, $requiredMainBalance) < 0) {
            Log::warning('ProcessQuidaxWithdrawal: Timed out waiting for main account balance. Reversing transfer.', [
                'currency' => $currency,
                'required_balance' => $this->formatCryptoAmount($requiredMainBalance),
                'available_balance' => $this->formatCryptoAmount($availableMainBalance),
            ] + $this->buildLogContext());

            $this->reverseMainAccountTransfer($quidax, 'Timed out waiting for main account balance settlement');
            $this->markPendingWithdrawalFailed('Timed out waiting for main account balance settlement', $mainWalletResponse);
            return;
        }

        $response = $this->attemptDestinationWithdrawal($quidax);
        Log::info('ProcessQuidaxWithdrawal: Destination withdrawal response.', [
            'response' => $response,
        ] + $this->buildLogContext());

        if ($this->isSuccessfulResponse($response)) {
            $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
            $txid = $this->extractTransactionHash($responseData)
                ?? $this->firstDataValue($responseData, ['id', 'uuid', 'reference'])
                ?? 'unknown';
            $mainTransferId = $mainAccountResponse['data']['id'] ?? $mainAccountResponse['data']['reference'] ?? 'internal-transfer';
            $this->persistCompletedWithdrawal($response, $mainTransferId, $txid);

            Log::info('ProcessQuidaxWithdrawal: Withdrawal record created.', $this->buildLogContext([
                'txid' => $txid,
                'main_transfer_id' => $mainTransferId,
            ]));
            return;
        }

        Log::warning('ProcessQuidaxWithdrawal: Destination failed. Reversing main account transfer.', [
            'response' => $response,
        ] + $this->buildLogContext());

        $failureReason = $this->extractFailureReason($response, 'Destination withdrawal failed');
        $this->reverseMainAccountTransfer($quidax, $failureReason);
        $this->markPendingWithdrawalFailed($failureReason, $response);
    }

    protected function waitForMainAccountBalance(QuidaxService $quidax, string $currency, string $requiredBalance, array $lastResponse = []): array
    {
        $deadline = microtime(true) + $this->mainWithdrawalWaitSeconds;
        $walletResponse = $lastResponse;

        do {
            if (microtime(true) >= $deadline) {
                break;
            }

            sleep($this->mainWithdrawalPollIntervalSeconds);

            $walletResponse = $quidax->fetchUserWallet('me', $currency);
            $availableMainBalance = $this->decimalAmount($walletResponse['data']['balance'] ?? '0');

            Log::info('ProcessQuidaxWithdrawal: Main account balance check', [
                'currency' => $currency,
                'required_balance' => $this->formatCryptoAmount($requiredBalance),
                'available_balance' => $this->formatCryptoAmount($availableMainBalance),
                'response' => $walletResponse,
            ] + $this->buildLogContext());

            if (($walletResponse['status'] ?? '') === 'success' && $this->compareCryptoAmounts($availableMainBalance, $requiredBalance) >= 0) {
                return $walletResponse;
            }
        } while (true);

        return $walletResponse;
    }

    // protected function attemptDestinationWithdrawal(QuidaxService $quidax): array
    // {
    //     $lastResponse = [];
    //     $this->destinationData['network'] = $this->resolveWithdrawalNetwork((string) ($this->destinationData['network'] ?? ''));
    //     $addressValidation = $this->normalizeWithdrawalAddress(
    //         (string) ($this->destinationData['fund_uid'] ?? ''),
    //         (string) ($this->destinationData['network'] ?? '')
    //     );

    //     if (!($addressValidation['valid'] ?? false)) {
    //         return [
    //             'status' => 'error',
    //             'message' => $addressValidation['message'] ?? 'Invalid withdrawal address.',
    //             'data' => $addressValidation['data'] ?? [],
    //         ];
    //     }

    //     if (($addressValidation['normalized'] ?? false) === true) {
    //         Log::info('ProcessQuidaxWithdrawal: Destination address normalized before provider request.', [
    //             'input_address' => $this->maskCryptoAddress((string) ($this->destinationData['fund_uid'] ?? '')),
    //             'normalized_address' => $this->maskCryptoAddress((string) $addressValidation['address']),
    //         ] + $this->buildLogContext());

    //         $this->destinationData['fund_uid'] = (string) $addressValidation['address'];
    //     }

    //     for ($attempt = 1; $attempt <= $this->destinationRetryAttempts; $attempt++) {
    //         $lastResponse = $quidax->create_withdrawal('me', $this->destinationData);

    //         if ($this->isSuccessfulResponse($lastResponse)) {
    //             return $lastResponse;
    //         }

    //         if (!$this->isInsufficientBalanceResponse($lastResponse) || $attempt === $this->destinationRetryAttempts) {
    //             break;
    //         }

    //         Log::warning('ProcessQuidaxWithdrawal: Destination withdrawal hit insufficient balance, retrying.', [
    //             'attempt' => $attempt,
    //             'retry_in_seconds' => $this->destinationRetryDelaySeconds,
    //             'response' => $lastResponse,
    //         ] + $this->buildLogContext());

    //         sleep($this->destinationRetryDelaySeconds);
    //     }

    //     return is_array($lastResponse) ? $lastResponse : [];
    // }
    protected function attemptDestinationWithdrawal(QuidaxService $quidax): array
{
    $lastResponse = [];

    $this->destinationData['network'] = $this->resolveWithdrawalNetwork(
        (string) ($this->destinationData['network'] ?? '')
    );

    $addressValidation = $this->normalizeWithdrawalAddress(
        (string) ($this->destinationData['fund_uid'] ?? ''),
        (string) ($this->destinationData['network'] ?? '')
    );

    if (!($addressValidation['valid'] ?? false)) {
        return [
            'status' => 'error',
            'message' => $addressValidation['message'] ?? 'Invalid withdrawal address.',
            'data' => $addressValidation['data'] ?? [],
        ];
    }

    if (($addressValidation['normalized'] ?? false) === true) {
        Log::info(
            'ProcessQuidaxWithdrawal: Destination address normalized before provider request.',
            [
                'input_address' => $this->maskCryptoAddress(
                    (string) ($this->destinationData['fund_uid'] ?? '')
                ),
                'normalized_address' => $this->maskCryptoAddress(
                    (string) $addressValidation['address']
                ),
            ] + $this->buildLogContext()
        );

        $this->destinationData['fund_uid'] = (string) $addressValidation['address'];
    }

    for ($attempt = 1; $attempt <= $this->destinationRetryAttempts; $attempt++) {

        $lastResponse = $quidax->create_withdrawal(
            'me',
            $this->destinationData
        );

        /*
         * Quidax accepted the withdrawal.
         *
         * IMPORTANT:
         * The withdrawal may still be "Processing".
         * Do not mark the local withdrawal as completed here.
         *
         * Save the Quidax provider withdrawal ID so that the
         * final webhook can locate this exact local withdrawal.
         */
        if ($this->isSuccessfulResponse($lastResponse)) {

            $this->persistProviderWithdrawalReference($lastResponse);

            return $lastResponse;
        }

        if (
            !$this->isInsufficientBalanceResponse($lastResponse)
            || $attempt === $this->destinationRetryAttempts
        ) {
            break;
        }

        Log::warning(
            'ProcessQuidaxWithdrawal: Destination withdrawal hit insufficient balance, retrying.',
            [
                'attempt' => $attempt,
                'retry_in_seconds' => $this->destinationRetryDelaySeconds,
                'response' => $lastResponse,
            ] + $this->buildLogContext()
        );

        sleep($this->destinationRetryDelaySeconds);
    }

    return is_array($lastResponse) ? $lastResponse : [];
}

    protected function resolveWithdrawalNetwork(string $network): string
    {
        $key = $this->normalizeNetworkKey($network);

        $aliases = [
            'arbitrum_network' => 'arbitrum',
            'arbitrum_one' => 'arbitrum',
            'base_mainnet' => 'base',
            'base_network' => 'base',
            'binance_smart_chain' => 'bep20',
            'binance_smart_chain_bep20' => 'bep20',
            'bnb_smart_chain_bep20' => 'bep20',
            'bnb_smart_chain' => 'bep20',
            'bep_20' => 'bep20',
            'ethereum' => 'erc20',
            'ethereum_erc20' => 'erc20',
            'ethereum_mainnet' => 'erc20',
            'ethereum_network' => 'erc20',
            'erc_20' => 'erc20',
            'lisk_network' => 'lsk',
            'optimism_network' => 'optimism',
            'polygon_network' => 'polygon',
            'tron_trc20' => 'trc20',
            'tron_network' => 'trc20',
            'trc_20' => 'trc20',
        ];

        return $aliases[$key] ?? $key;
    }

    protected function normalizeWithdrawalAddress(string $address, string $network): array
    {
        $address = trim($address);

        if ($address === '') {
            return [
                'valid' => false,
                'address' => $address,
                'message' => 'Wallet address is required.',
                'data' => [
                    'network' => $network,
                ],
            ];
        }

        if (!$this->networkUsesEvmAddress($network)) {
            return [
                'valid' => true,
                'address' => $address,
                'normalized' => false,
            ];
        }

        $normalizedAddress = $address;
        $autoPrefixed = false;

        if (preg_match('/\A[0-9a-fA-F]{40}\z/', $normalizedAddress) === 1) {
            $normalizedAddress = '0x' . $normalizedAddress;
            $autoPrefixed = true;
        }

        if (preg_match('/\A0x[0-9a-fA-F]{40}\z/', $normalizedAddress) !== 1) {
            return [
                'valid' => false,
                'address' => $address,
                'message' => 'Invalid wallet address for ' . strtoupper($network) . '. Use a 0x-prefixed EVM address with 40 hexadecimal characters.',
                'data' => [
                    'network' => $network,
                    'address' => $this->maskCryptoAddress($address),
                    'expected_format' => '0x followed by 40 hexadecimal characters',
                ],
            ];
        }

        return [
            'valid' => true,
            'address' => $normalizedAddress,
            'normalized' => $autoPrefixed,
        ];
    }

    protected function networkUsesEvmAddress(string $network): bool
    {
        return in_array($this->normalizeNetworkKey($network), [
            'arbitrum',
            'arbitrum_one',
            'avax',
            'avalanche',
            'avalanche_c_chain',
            'base',
            'base_mainnet',
            'base_network',
            'bep20',
            'binance_smart_chain',
            'bnb_smart_chain',
            'bsc',
            'celo',
            'erc20',
            'eth',
            'ethereum',
            'ethereum_mainnet',
            'ethereum_network',
            'fantom',
            'ftm',
            'lisk',
            'lsk',
            'mantle',
            'matic',
            'optimism',
            'polygon',
            'scroll',
            'sonic',
            'world_chain',
            'worldchain',
            'zksync',
        ], true);
    }

    protected function normalizeNetworkKey(string $network): string
    {
        $key = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($network))) ?? '';
        $key = trim($key, '_');

        return $key;
    }

    protected function maskCryptoAddress(?string $address): ?string
    {
        $address = trim((string) $address);

        if ($address === '') {
            return null;
        }

        if (strlen($address) <= 12) {
            return $address;
        }

        return substr($address, 0, 6) . '...' . substr($address, -4);
    }

    protected function reverseMainAccountTransfer(QuidaxService $quidax, string $reason): void
    {
        $reverseResponse = $quidax->fundSubAccount(
            $this->user->quidax_id,
            $this->mainAccountData['amount'] ?? $this->totalAmount,
            strtolower((string) ($this->mainAccountData['currency'] ?? $this->destinationData['currency'] ?? '')),
            ($this->destinationData['reference'] ?? $this->mainAccountData['reference'] ?? 'withdrawal') . '-reversal',
            "Withdrawal reversal: {$reason}"
        );

        Log::info("ProcessQuidaxWithdrawal: Main account reversal response", [
            'reason' => $reason,
            'response' => $reverseResponse,
        ] + $this->buildLogContext());
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

    protected function extractFailureReason(array $response, string $fallback): string
    {
        $status = strtolower((string) ($response['status'] ?? ''));
        $message = trim((string) ($response['message'] ?? ''));
        $dataMessage = trim((string) ($response['data']['message'] ?? ''));
        $errorMessage = trim((string) ($response['error'][0]['message'] ?? ''));
        $errorDetails = trim((string) ($response['error'][0]['details'] ?? ''));

        foreach ([$dataMessage, $message, $errorDetails, $errorMessage] as $candidate) {
            if ($candidate !== '') {
                if ($status !== '' && !str_contains(strtolower($candidate), $status)) {
                    return "{$fallback}: {$candidate}";
                }

                return $candidate;
            }
        }

        return $fallback;
    }

    protected function persistCompletedWithdrawal(array $response, string $mainWithdrawalId, string $txid): void
    {
        $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
        $withdrawal = $this->pendingWithdrawalId
            ? Withdrawals::query()->find($this->pendingWithdrawalId)
            : null;

        $existingRecipientData = is_array($withdrawal?->recipient_data) ? $withdrawal->recipient_data : [];
        $existingWalletMeta = is_array($withdrawal?->wallet) ? $withdrawal->wallet : [];
        $providerRecipientData = is_array($responseData['recipient'] ?? null) ? $responseData['recipient'] : [];
        $providerWalletMeta = is_array($responseData['wallet'] ?? null) ? $responseData['wallet'] : [];

        $providerWithdrawalId = $this->firstDataValue($responseData, ['id', 'uuid', 'reference']) ?? $txid;
        $transactionHash = $this->extractTransactionHash($responseData)
            ?? $this->validStoredTransactionHash($txid, [$providerWithdrawalId])
            ?? $this->validStoredTransactionHash($existingWalletMeta['transaction_hash'] ?? null)
            ?? $this->validStoredTransactionHash($existingWalletMeta['txid'] ?? null)
            ?? $this->validStoredTransactionHash($withdrawal?->trans_id ?? null, [$providerWithdrawalId, $withdrawal?->reference]);
        $recipientAddress = $this->extractRecipientAddress($responseData)
            ?? $this->extractRecipientAddress($providerRecipientData)
            ?? $this->extractRecipientAddress($existingRecipientData)
            ?? $this->extractRecipientAddress($existingWalletMeta)
            ?? ($this->destinationData['fund_uid'] ?? null);
        $network = $this->extractNetwork($responseData)
            ?? $this->extractNetwork($providerRecipientData)
            ?? $this->extractNetwork($existingRecipientData)
            ?? $this->extractNetwork($existingWalletMeta)
            ?? ($this->destinationData['network'] ?? null);

        $recipientData = array_replace_recursive($providerRecipientData, $existingRecipientData);
        $recipientData['type'] = $recipientData['type'] ?? 'coin_address';
        if ($recipientAddress) {
            data_set($recipientData, 'details.address', $recipientAddress);
            $recipientData['address'] = $recipientData['address'] ?? $recipientAddress;
        }
        if ($network) {
            data_set($recipientData, 'details.network', strtolower((string) $network));
        }

        $walletMeta = array_merge($existingWalletMeta, $providerWalletMeta, [
            'status' => 'completed',
            'reservation_type' => 'crypto_withdrawal',
            'main_account_withdrawal_id' => $mainWithdrawalId,
            'reservation_released_at' => now()->toDateTimeString(),
            'reservation_release_reason' => 'withdrawal_completed',
        ]);

        if ($transactionHash) {
            $walletMeta['transaction_hash'] = $transactionHash;
            $walletMeta['txid'] = $transactionHash;
        }
        if ($providerWithdrawalId && $providerWithdrawalId !== 'unknown') {
            $walletMeta['provider_withdrawal_id'] = $providerWithdrawalId;
        }
        if ($recipientAddress) {
            $walletMeta['recipient_address'] = $recipientAddress;
        }
        if ($network) {
            $walletMeta['network'] = strtolower((string) $network);
        }

        Log::info('ProcessQuidaxWithdrawal: Persisting completed withdrawal hash/address.', $this->buildLogContext([
            'transaction_hash' => $transactionHash,
            'provider_withdrawal_id' => $providerWithdrawalId,
            'recipient_address' => $recipientAddress,
        ]));

        $payload = [
            'user_id' => $this->user->id,
            'reference' => $responseData['reference'] ?? ($this->destinationData['reference'] ?? null),
            'type' => $responseData['type'] ?? 'coin_address',
            'currency' => $responseData['currency'] ?? strtolower((string) ($this->destinationData['currency'] ?? '')),
            'amount' => $responseData['amount'] ?? ($this->destinationData['amount'] ?? null),
            'fee' => $this->feeAmount,
            'total' => $this->totalAmount,
            'trans_id' => $transactionHash ?: $providerWithdrawalId ?: $txid,
            'transaction_note' => $responseData['transaction_note'] ?? $withdrawal?->transaction_note,
            'recipient_data' => $recipientData,
            'wallet' => $walletMeta,
            'user' => $responseData['user'] ?? (is_array($withdrawal?->user) ? $withdrawal->user : null),
        ];

        if ($withdrawal) {
            $withdrawal->update($payload);
            $withdrawal->refresh();
            $this->queueWithdrawalNotifications($withdrawal, 'completed');
            return;
        }

        $withdrawal = Withdrawals::create($payload);
        $this->queueWithdrawalNotifications($withdrawal, 'completed');
    }

    protected function markPendingWithdrawalFailed(string $reason, array $response = []): void
    {
        if (!$this->pendingWithdrawalId) {
            return;
        }

        $withdrawal = Withdrawals::query()->find($this->pendingWithdrawalId);
        if (!$withdrawal) {
            return;
        }

        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $walletMeta['status'] = 'failed';
        $walletMeta['failure_reason'] = $reason;
        $walletMeta['reservation_released_at'] = now()->toDateTimeString();
        $walletMeta['reservation_release_reason'] = 'withdrawal_failed';
        if (!empty($response)) {
            $walletMeta['failure_response'] = $response;
        }

        $userMeta = is_array($withdrawal->user) ? $withdrawal->user : [];
        $userMeta['status'] = 'failed';
        $userMeta['failure_reason'] = $reason;

        $withdrawal->update([
            'trans_id' => 'failed:' . ($withdrawal->reference ?? $withdrawal->id),
            'wallet' => $walletMeta,
            'user' => $userMeta,
        ]);

        $withdrawal->refresh();
        $this->queueWithdrawalNotifications($withdrawal, 'failed', $reason);
    }

    protected function queueWithdrawalNotifications(Withdrawals $withdrawal, string $status, ?string $reason = null): void
    {
        try {
            $user = $this->user instanceof User ? $this->user : User::find($withdrawal->user_id);
            $details = $this->buildWithdrawalEmailDetails($withdrawal, $status, $reason);

            if ($user && !empty($user->email)) {
                $this->queueUserWithdrawalMail($user, $withdrawal, $status, $details);
            }

            if ($status === 'failed') {
                $this->queueAdminWithdrawalFailureMail($withdrawal, $details);
            }
        } catch (\Throwable $exception) {
            Log::error('ProcessQuidaxWithdrawal: Failed to queue withdrawal notification.', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'status' => $status,
                'message' => $exception->getMessage(),
            ] + $this->buildLogContext());
        }
    }

    protected function queueUserWithdrawalMail(User $user, Withdrawals $withdrawal, string $status, array $details): void
    {
        $reference = (string) ($withdrawal->reference ?? $withdrawal->id);

        if (!$this->shouldSendUserWithdrawalNotification($status, $details)) {
            Log::info('ProcessQuidaxWithdrawal: User withdrawal email skipped until terminal details are complete.', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $reference,
                'status' => $status,
                'has_transaction_hash' => !empty($details['transaction_hash']),
            ] + $this->buildLogContext());

            return;
        }

        $dedupeKey = $this->withdrawalNotificationDedupeKey($reference, $status, 'user');

        if ($this->markNotificationDuplicate($dedupeKey, $reference, "crypto_withdrawal_{$status}")) {
            return;
        }

        $log = CryptoNotificationLog::create([
            'provider' => 'quidax',
            'event_name' => "local.withdrawal.{$status}",
            'event_id' => $reference,
            'dedupe_key' => $dedupeKey,
            'transaction_reference' => $reference,
            'user_id' => $user->id,
            'payload' => [
                'source' => 'withdrawal_worker',
                'withdrawal_id' => $withdrawal->id,
                'details' => $details,
            ],
            'notification_type' => "crypto_withdrawal_{$status}",
            'status' => 'processing',
            'duplicate' => false,
        ]);

        try {
            Mail::to($user->email)->queue(new CryptoWithdrawalNotificationMail($user, $details));
            $this->logMailFailures("crypto_withdrawal_{$status}", $reference);
            $log->update([
                'status' => 'queued',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            Log::error('ProcessQuidaxWithdrawal: User withdrawal email queue failed.', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $reference,
                'status' => $status,
                'message' => $exception->getMessage(),
            ] + $this->buildLogContext());
        }
    }

    protected function shouldSendUserWithdrawalNotification(string $status, array $details): bool
    {
        if ($status === 'failed') {
            return true;
        }

        if ($status !== 'completed') {
            return false;
        }

        return trim((string) ($details['transaction_hash'] ?? '')) !== '';
    }

    protected function queueAdminWithdrawalFailureMail(Withdrawals $withdrawal, array $details): void
    {
        $reference = (string) ($withdrawal->reference ?? $withdrawal->id);
        $dedupeKey = $this->withdrawalNotificationDedupeKey($reference, 'failed', 'admin');

        if ($this->markNotificationDuplicate($dedupeKey, $reference, 'crypto_withdrawal_failed_admin')) {
            return;
        }

        $log = CryptoNotificationLog::create([
            'provider' => 'quidax',
            'event_name' => 'local.withdrawal.failed',
            'event_id' => $reference,
            'dedupe_key' => $dedupeKey,
            'transaction_reference' => $reference,
            'user_id' => $withdrawal->user_id,
            'payload' => [
                'source' => 'withdrawal_worker',
                'withdrawal_id' => $withdrawal->id,
                'details' => $details,
            ],
            'notification_type' => 'crypto_withdrawal_failed_admin',
            'status' => 'processing',
            'duplicate' => false,
        ]);

        $adminEmails = Admin::query()
            ->where('status', true)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        if ($adminEmails === [] && config('mail.from.address')) {
            $adminEmails = [config('mail.from.address')];
        }

        try {
            foreach ($adminEmails as $email) {
                Mail::to($email)->queue(new CryptoWithdrawalFailedAdminMail($details));
            }

            $this->logMailFailures('crypto_withdrawal_failed_admin', $reference);
            $log->update([
                'status' => $adminEmails === [] ? 'ignored' : 'queued',
                'sent_at' => $adminEmails === [] ? null : now(),
                'error_message' => $adminEmails === [] ? 'No admin email configured.' : null,
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            Log::error('ProcessQuidaxWithdrawal: Admin withdrawal email queue failed.', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ] + $this->buildLogContext());
        }
    }

    protected function markNotificationDuplicate(string $dedupeKey, string $reference, string $notificationType): bool
    {
        $existing = CryptoNotificationLog::where('dedupe_key', $dedupeKey)->first();

        if (!$existing) {
            return false;
        }

        $existing->forceFill([
            'duplicate' => true,
            'duplicate_count' => $existing->duplicate_count + 1,
        ])->save();

        Log::info('ProcessQuidaxWithdrawal: Duplicate withdrawal notification suppressed.', [
            'reference' => $reference,
            'notification_type' => $notificationType,
            'dedupe_key' => $dedupeKey,
            'duplicate_count' => $existing->duplicate_count,
        ] + $this->buildLogContext());

        return true;
    }

    protected function buildWithdrawalEmailDetails(Withdrawals $withdrawal, string $status, ?string $reason = null): array
    {
        $recipientData = is_array($withdrawal->recipient_data) ? $withdrawal->recipient_data : [];
        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $transactionHash = $this->validStoredTransactionHash($walletMeta['transaction_hash'] ?? null)
            ?? $this->validStoredTransactionHash($walletMeta['txid'] ?? null)
            ?? $this->validStoredTransactionHash($withdrawal->trans_id ?? null, [
                $walletMeta['provider_withdrawal_id'] ?? null,
                $withdrawal->reference ?? null,
            ]);
        $recipientAddress = $this->extractRecipientAddress($recipientData)
            ?? $this->extractRecipientAddress($walletMeta)
            ?? ($this->destinationData['fund_uid'] ?? null);
        $network = $this->extractNetwork($walletMeta)
            ?? $this->extractNetwork($recipientData)
            ?? ($this->destinationData['network'] ?? null);

        return [
            'user_id' => $withdrawal->user_id,
            'amount' => (string) ($withdrawal->amount ?? '0'),
            'coin' => (string) ($withdrawal->currency ?? ''),
            'network' => (string) ($network ?? ''),
            'recipient_address' => $recipientAddress,
            'transaction_hash' => $transactionHash,
            'transaction_reference' => (string) ($withdrawal->reference ?? $withdrawal->id),
            'status' => $status,
            'failure_reason' => $reason,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    protected function withdrawalNotificationDedupeKey(string $reference, string $status, string $scope = 'user'): string
    {
        return sha1('quidax|withdrawal|' . strtolower($scope) . '|' . strtolower($reference) . '|' . strtolower($status));
    }

    protected function extractTransactionHash(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'txid',
            'tx_id',
            'transaction_hash',
            'hash',
            'blockchain_transaction_hash',
            'blockchain_txid',
            'transaction.hash',
            'transaction.txid',
            'withdrawal.txid',
            'withdrawal.transaction_hash',
            'blockchain.txid',
            'blockchain.transaction_hash',
            'data.txid',
            'data.transaction_hash',
        ]);
    }

    protected function extractRecipientAddress(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'fund_uid',
            'address',
            'recipient_address',
            'destination_address',
            'to_address',
            'wallet_address',
            'recipient.address',
            'recipient.details.address',
            'recipient.data.address',
            'destination.address',
            'destination.details.address',
            'details.address',
            'data.fund_uid',
            'data.recipient.details.address',
        ]);
    }

    protected function extractNetwork(array $data): ?string
    {
        return $this->firstDataValue($data, [
            'network',
            'blockchain',
            'chain',
            'recipient.network',
            'recipient.details.network',
            'destination.network',
            'details.network',
            'data.network',
        ]);
    }

    protected function firstDataValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : null;
            }
        }

        return null;
    }

    protected function validStoredTransactionHash($value, array $rejectValues = []): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'unknown') {
            return null;
        }

        foreach ($rejectValues as $rejectValue) {
            if ($rejectValue !== null && strtolower($value) === strtolower(trim((string) $rejectValue))) {
                return null;
            }
        }

        foreach (['failed:', 'pending:', 'processing:', 'initiated:', 'cancelled:', 'canceled:'] as $prefix) {
            if (str_starts_with(strtolower($value), $prefix)) {
                return null;
            }
        }

        return $value;
    }

    protected function logMailFailures(string $notificationType, ?string $reference = null): void
    {
        $mailer = Mail::getFacadeRoot();

        if (!method_exists($mailer, 'failures')) {
            return;
        }

        $failures = Mail::failures();
        if (!empty($failures)) {
            Log::error('ProcessQuidaxWithdrawal: Mail failures reported after notification queue.', [
                'notification_type' => $notificationType,
                'reference' => $reference,
                'failures' => $failures,
            ] + $this->buildLogContext());
        }
    }

    protected function decimalAmount($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            $value = sprintf('%.18F', $value);
        }

        $value = trim((string) $value);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        return bcadd($value, '0', 18);
    }

    protected function compareCryptoAmounts($left, $right): int
    {
        return bccomp($this->decimalAmount($left), $this->decimalAmount($right), 8);
    }

    protected function formatCryptoAmount($value): string
    {
        $formatted = bcadd($this->decimalAmount($value), '0', 8);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    protected function buildLogContext(array $extra = []): array
    {
        return array_merge([
            'user_id' => $this->user->id ?? null,
            'quidax_id' => $this->user->quidax_id ?? null,
            'pending_withdrawal_id' => $this->pendingWithdrawalId,
            'reference' => $this->destinationData['reference'] ?? $this->mainAccountData['reference'] ?? null,
            'currency' => strtolower((string) ($this->destinationData['currency'] ?? $this->mainAccountData['currency'] ?? '')),
            'network' => strtolower((string) ($this->destinationData['network'] ?? $this->mainAccountData['network'] ?? '')),
        ], $extra);
    }
    protected function persistProviderWithdrawalReference(array $response): void
{
    if (!$this->pendingWithdrawalId) {
        Log::warning(
            'ProcessQuidaxWithdrawal: Cannot save provider withdrawal ID because pending withdrawal ID is missing.',
            [
                'response' => $response,
            ] + $this->buildLogContext()
        );

        return;
    }

    $withdrawal = Withdrawals::query()->find($this->pendingWithdrawalId);

    if (!$withdrawal) {
        Log::warning(
            'ProcessQuidaxWithdrawal: Pending withdrawal record not found when saving provider withdrawal ID.',
            [
                'pending_withdrawal_id' => $this->pendingWithdrawalId,
                'response' => $response,
            ] + $this->buildLogContext()
        );

        return;
    }

    $responseData = is_array($response['data'] ?? null)
        ? $response['data']
        : [];

    $providerWithdrawalId = $this->firstDataValue(
        $responseData,
        ['id', 'uuid', 'provider_id', 'withdrawal_id']
    );

    if (!$providerWithdrawalId) {
        Log::warning(
            'ProcessQuidaxWithdrawal: Quidax accepted withdrawal but returned no provider withdrawal ID.',
            [
                'response' => $response,
            ] + $this->buildLogContext()
        );

        return;
    }

    $walletMeta = is_array($withdrawal->wallet)
        ? $withdrawal->wallet
        : [];

    $walletMeta['provider_withdrawal_id'] = $providerWithdrawalId;

    // At this point Quidax has accepted the withdrawal,
    // but it is NOT necessarily completed.
    $walletMeta['status'] = 'processing';

    $providerStatus = $responseData['status'] ?? null;

    if ($providerStatus !== null && $providerStatus !== '') {
        $walletMeta['provider_status'] = $providerStatus;
    }

    $walletMeta['provider_accepted_at'] = now()->toDateTimeString();

    $withdrawal->update([
        'wallet' => $walletMeta,

        // Keep your merchant/local reference as the transaction ID
        // while the provider withdrawal is still processing.
        'trans_id' => 'processing:' . (
            $withdrawal->reference
            ?? $this->destinationData['reference']
            ?? $withdrawal->id
        ),
    ]);

    $withdrawal->refresh();

    Log::info(
        'ProcessQuidaxWithdrawal: Provider withdrawal ID saved successfully.',
        [
            'withdrawal_id' => $withdrawal->id,
            'provider_withdrawal_id' => $providerWithdrawalId,
            'provider_status' => $providerStatus,
        ] + $this->buildLogContext()
    );
}
}
