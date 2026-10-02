<?php

namespace App\Services;

use App\Http\Helpers\SafeHeaven\AccountHelper;
use App\Http\Helpers\SafeHeaven\TransferHelper;
use App\Http\Helpers\SafeHeaven\VASHelper;
use App\Models\OrderTransaction;
use App\Models\RampTransaction;
use App\Models\SafeHavenWebhookEvent;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Models\VirtualAccounts;
use App\Models\User;
use App\Models\Bank;
use App\Models\KycVerification;
use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Notifications\User\SafeHavenCreditNotification;

class SafeHavenService
{
    protected $accountHelper;
    protected $transferHelper;
    protected $vasHelper;

    public function __construct(
        AccountHelper $accountHelper,
        TransferHelper $transferHelper,
        VASHelper $vasHelper
    ) {
        $this->accountHelper = $accountHelper;
        $this->transferHelper = $transferHelper;
        $this->vasHelper = $vasHelper;
    }

    /**
     * Create an individual sub-account for a user.
     */
    public function createSubAccount($user)
    {
        // Fetch BVN from KYC Tier 1
        $kyc = KycVerification::where('user_id', $user->id)
            ->where('level', 1)
            ->where('status', 'verified')
            ->first();

        if (!$kyc || !isset($kyc->data['bvn'])) {
            throw new Exception("BVN not found in KYC data. Please complete Tier 1 verification again.");
        }

        $verificationResult = $kyc->data['verification_result'] ?? [];
        
        
        $data = [
            "phoneNumber" => "+234" . substr(preg_replace('/\D/', '', $user->mobile), -10),
            "emailAddress" => $user->email,
            "externalReference" => (string) Str::uuid(),
            "firstName" => $verificationResult['firstName'] ?? $user->firstname,
            "lastName" => $verificationResult['lastName'] ?? $user->lastname,
            "identityType" => "BVN",
            "identityId" => $kyc->transaction_id,
            "identityNumber" => $kyc->data['bvn'] ?? '',
            "autoSweep" => false, 
        ];

        if (!empty($verificationResult['middleName'])) {
            $data['middleName'] = $verificationResult['middleName'];
        }

        // Ensure gender is passed if available
        if (isset($verificationResult['gender'])) {
             $data['gender'] = $verificationResult['gender'];
        }

        // Include DOB if available
        if (isset($verificationResult['dob'])) {
            $data['dateOfBirth'] = $verificationResult['dob'];
        }

        // OTP is CRITICAL for SafeHaven sub-account creation via BVN/NIN
        // However, if we have already verified the OTP in Tier 1, reusing it throws "OTP already verified".
        // But sending no OTP throws "400".
        // Strategy: Try using 'vID' (Verified ID) as identityType if we have a verified identityId.
        
        $data['identityType'] = 'vID';
        // Some docs suggest identityNumber might still be needed or ignored, but safe to send?
        // Let's keep identityNumber for vID just in case, but remove OTP.
        $data['identityNumber'] = $kyc->data['bvn'] ?? '';
        unset($data['otp']); 
        
        // if (isset($kyc->data['otp'])) {
        //    $data['otp'] = $kyc->data['otp'];
        // }

        Log::info("SafeHaven Sub-Account Final Payload", ['payload' => $data]);

        $response = $this->accountHelper->createSubAccountInd($data);

        if (($response['status'] ?? false) !== true && ($response['statusCode'] ?? 0) !== 200) {
             throw new Exception("SafeHaven Sub-Account Creation Failed: " . ($response['description'] ?? 'Unknown error'));
        }

        return $response['data'];
    }

    /**
     * Perform name enquiry for bank transfer.
     */
    public function nameEnquiry(string $bankCode, string $accountNumber)
    {
        $data = [
            "bankCode" => $bankCode,
            "accountNumber" => $accountNumber
        ];

        $response = $this->transferHelper->nameEnquiry($data);

        if (($response['status'] ?? false) !== true && ($response['statusCode'] ?? 0) !== 200) {
            throw new Exception("Name Enquiry Failed: " . ($response['description'] ?? 'Unknown error'));
        }

        return $response['data'];
    }

    /**
     * Process bank transfer.
     */
    public function transfer(array $payload)
    {
    
        $response = $this->transferHelper->transfer($payload);
        

        if (($response['status'] ?? false) !== true && ($response['statusCode'] ?? 0) !== 200) {
        
            throw new Exception("Transfer Failed: " . ($response['message'] ?? $response['description'] ?? 'Unknown error'));
        }

        return $response['data'];
    }

    /**
     * Sync the user's mirrored wallet balance from the SafeHaven sub-account when possible.
     */
    public function syncUserWalletBalance(User $user, string $currencyCode = 'NGN'): ?string
    {
        $virtualAccount = $this->getUserSubAccount($user);

        if (!$virtualAccount || empty($virtualAccount->account_id)) {
            return null;
        }

        try {
            $response = $this->accountHelper->getAccount($virtualAccount->account_id);

            if (!is_array($response) || (int) ($response['statusCode'] ?? 0) !== 200 || !is_array($response['data'] ?? null)) {
                return null;
            }

            $account = $response['data'];
            $balance = $account['accountBalance'] ?? $account['bookBalance'] ?? null;

            if ($balance === null || $balance === '' || !is_numeric($balance)) {
                return null;
            }

            $wallet = $this->findUserWalletByCurrency($user, $currencyCode);

            if (!$wallet) {
                return null;
            }

            $normalizedBalance = number_format((float) $balance, 8, '.', '');

            if (bccomp((string) $wallet->balance, $normalizedBalance, 8) !== 0) {
                $wallet->balance = $normalizedBalance;
                $wallet->save();
            }

            return (string) $wallet->balance;
        } catch (Exception $e) {
            Log::warning("SafeHaven Wallet Sync Failed", [
                'user_id' => $user->id,
                'account_id' => $virtualAccount->account_id,
                'currency' => strtoupper($currencyCode),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getUserSubAccount($user): ?VirtualAccounts
    {
        $virtualAccount = $user->virtualAccounts()
            ->where(function ($query) {
                $query->where('provider', 'safehaven')
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('provider')
                            ->where(function ($accountQuery) {
                                $accountQuery->where('bank_name', 'like', '%SafeHaven%')
                                    ->orWhereIn('bank_code', ['090286', '090281']);
                            });
                    });
            })
            ->latest('id')
            ->first();

        if ($virtualAccount && empty($virtualAccount->provider)) {
            $virtualAccount->update(['provider' => 'safehaven']);
        }

        return $virtualAccount;
    }

    /**
     * Handle inbound settlement webhook from SafeHaven.
     */
    public function handleSettlement(array $payload)
    {
        Log::info("SafeHaven Settlement Webhook", ['payload' => $payload]);
        $settlement = $this->normalizeSettlementPayload($payload);

        if ($settlement === null) {
            Log::info("SafeHaven Settlement Ignored", ['reason' => 'unsupported_or_invalid_payload']);
            return false;
        }

        $notificationData = null;

        DB::transaction(function () use ($payload, $settlement, &$notificationData) {
            $event = SafeHavenWebhookEvent::where('provider_reference', $settlement['provider_reference'])
                ->lockForUpdate()
                ->first();

            if ($event && $event->status === 'processed') {
                Log::info("SafeHaven Settlement Duplicate Ignored", [
                    'provider_reference' => $settlement['provider_reference'],
                ]);

                return;
            }

            if (!$event) {
                $event = SafeHavenWebhookEvent::create([
                    'provider_reference' => $settlement['provider_reference'],
                    'event_type' => $settlement['event_type'],
                    'webhook_type' => $settlement['webhook_type'],
                    'direction' => $settlement['direction'],
                    'account_number' => $settlement['account_number'],
                    'status' => 'processing',
                    'payload' => $payload,
                ]);
            } else {
                $event->update([
                    'event_type' => $settlement['event_type'],
                    'webhook_type' => $settlement['webhook_type'],
                    'direction' => $settlement['direction'],
                    'account_number' => $settlement['account_number'],
                    'status' => 'processing',
                    'payload' => $payload,
                    'error_message' => null,
                ]);
            }

            $virtualAccount = $this->findVirtualAccountForSettlement($settlement['account_number']);

            if (!$virtualAccount) {
                $this->markSettlementEventAsFailed($event, 'Virtual account not found for settlement.');
                Log::error("SafeHaven Virtual Account not found for number: " . $settlement['account_number']);
                return;
            }

            $user = $virtualAccount->user;

            if (empty($virtualAccount->account_id) && !empty($settlement['account_id'])) {
                $virtualAccount->update([
                    'account_id' => $settlement['account_id'],
                ]);
            }

            if (!$user) {
                $this->markSettlementEventAsFailed($event, 'User not found for settlement virtual account.');
                Log::error("User not found for SafeHaven Account: " . $settlement['account_number']);
                return;
            }

            $wallet = $this->findUserWalletByCurrency($user, 'NGN', true);

            if (!$wallet) {
                $this->markSettlementEventAsFailed($event, 'NGN wallet not found for settlement.');
                Log::error("NGN Wallet not found for User: " . $user->id);
                return;
            }

            $creditAmount = $this->calculateSettlementCreditAmount(
                $settlement['amount'],
                $settlement['provider_fee'],
                $settlement['vat'],
                $settlement['stamp_duty']
            );

            if (bccomp($creditAmount, '0.00000000', 8) <= 0) {
                $this->markSettlementEventAsFailed($event, 'Net settlement credit amount is zero or negative.');
                Log::warning("SafeHaven Settlement Skipped: deductions exceed or equal amount.", [
                    'provider_reference' => $settlement['provider_reference'],
                    'amount' => $settlement['amount'],
                    'fees' => $settlement['provider_fee'],
                    'vat' => $settlement['vat'],
                    'stamp_duty' => $settlement['stamp_duty'],
                ]);
                return;
            }

            $wallet->balance = bcadd((string) $wallet->balance, $creditAmount, 8);
            $wallet->save();

            OrderTransaction::create([
                'user_wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $creditAmount,
                'balance_after' => $wallet->balance,
                'reference' => $this->settlementLedgerReference($settlement['provider_reference']),
                'metadata' => [
                    'provider' => 'safehaven',
                    'provider_reference' => $settlement['provider_reference'],
                    'raw_info' => [
                        'amount_sent' => $settlement['amount'],
                        'provider_fee' => $settlement['provider_fee'],
                        'vat' => $settlement['vat'],
                        'stamp_duty' => $settlement['stamp_duty'],
                        'account_number' => $settlement['account_number'],
                        'webhook_type' => $settlement['webhook_type'],
                    ],
                ],
            ]);

            $this->markLinkedTransactionsAsCompleted($settlement['provider_reference'], $wallet);

            try {
                $reconciledTransaction = $this->reconcileCompletedOffRampPayout($user, $settlement, $wallet, $creditAmount);

                if ($reconciledTransaction) {
                    Log::info("SafeHaven Settlement Reconciled Off-Ramp", [
                        'user_id' => $user->id,
                        'merchant_reference' => $reconciledTransaction->merchant_reference,
                        'provider_reference' => $settlement['provider_reference'],
                    ]);
                }
            } catch (Exception $e) {
                Log::warning("SafeHaven Settlement Off-Ramp Reconciliation Failed", [
                    'user_id' => $user->id,
                    'provider_reference' => $settlement['provider_reference'],
                    'error' => $e->getMessage(),
                ]);
            }

            $event->update([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'credit_amount' => $creditAmount,
                'balance_after' => (string) $wallet->balance,
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            try {
                app(\App\Services\ReferralService::class)->completeReferral($user, $creditAmount);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to complete referral on deposit: " . $e->getMessage());
            }

            Log::info("SafeHaven Settlement Successful", [
                'user_id' => $user->id,
                'credit_amount' => $creditAmount,
                'fees_deducted' => $settlement['provider_fee'],
                'reference' => $settlement['provider_reference'],
            ]);

            $notificationData = [
                'user' => $user,
                'amount' => $creditAmount,
                'reference' => $settlement['provider_reference'],
            ];
        });

        if ($notificationData) {
            try {
                $notificationData['user']->notify(new SafeHavenCreditNotification(
                    $notificationData['amount'],
                    $notificationData['reference']
                ));
            } catch (Exception $e) {
                Log::warning("SafeHaven Settlement Notification Failed", [
                    'user_id' => $notificationData['user']->id,
                    'reference' => $notificationData['reference'],
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Sync banks from SafeHaven to local DB.
     */
    public function syncBanks()
    {
        $response = $this->transferHelper->bankList();

        if (($response['status'] ?? false) !== true && ($response['statusCode'] ?? 0) !== 200) {
            throw new Exception("Failed to fetch bank list from SafeHaven: " . ($response['description'] ?? 'Unknown error'));
        }

        $banks = $response['data'];
        $count = 0;

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['code' => $bank['bankCode']],
                [
                    'name' => $bank['name'] ?? $bank['bankName'] ?? 'Unknown Bank',
                    'slug' => Str::slug($bank['name'] ?? $bank['bankName'] ?? 'unknown'),
                    'logo_image' => $bank['logoImage'] ?? null,
                    'is_active' => true
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Debit User Sub-Account to App Main Account
     */
    public function debitSubAccountToApp($debitAccountNumber, $amount)
    {
        $settings = \App\Models\Admin\BasicSettings::first();
        $creditAccount = $settings->safehaven_debit_account ?? null;
        
        // Use Config or Default for Bank Code (SafeHaven MFB)
        $creditBank = config('services.safeHeaven.bank_code', '090281');
        
        if(!$creditAccount) {
             // Fallback to Env if DB is empty? User said "take from database".
             // Maybe legacy env support?
             $creditAccount = config('services.safeHeaven.main_account');
        }

        if(!$creditAccount) {
            throw new Exception("SafeHaven Main Debit Account not configured in Basic Settings.");
        }

        // 1. Name Enquiry (Validate Destination)
        $enquiry = $this->nameEnquiry($creditBank, $creditAccount);
        $sessionId = $enquiry['sessionId'] ?? ($enquiry['sessionID'] ?? null);

        // 2. Transfer
        $payload = [
            'nameEnquirySessionId' => $sessionId,
            'paymentReference' => (string) Str::uuid(),
            'amount' => $amount,
            'debitAccountNumber' => $debitAccountNumber,
            'creditAccountNumber' => $creditAccount,
            'creditBankCode' => $creditBank,
            'narration' => 'Instant Order Debit',
        ];

        
        
        return $this->transfer($payload);
    }

    protected function normalizeSettlementPayload(array $payload): ?array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $webhookType = strtolower((string) ($payload['type'] ?? ''));
        $eventType = strtolower((string) ($payload['eventType'] ?? ''));
        $direction = strtolower((string) ($data['type'] ?? ''));
        $status = strtolower((string) ($data['status'] ?? $payload['status'] ?? ''));
        $responseCode = (string) ($data['responseCode'] ?? $payload['responseCode'] ?? '');
        $isReversed = (bool) ($data['isReversed'] ?? false);
        $accountNumber = $data['creditAccountNumber']
            ?? $payload['creditAccountNumber']
            ?? $data['accountNumber']
            ?? $payload['accountNumber']
            ?? $data['realCreditAccountNumber']
            ?? $payload['realCreditAccountNumber']
            ?? $data['beneficiaryAccountNumber']
            ?? $payload['beneficiaryAccountNumber']
            ?? null;
        $accountId = $data['account']
            ?? $payload['account']
            ?? null;

        $providerReference = $data['paymentReference']
            ?? $data['sessionId']
            ?? $data['reference']
            ?? $payload['reference']
            ?? $data['_id']
            ?? null;

        $isIncomingTransfer = $webhookType === 'virtualaccount.transfer'
            || (in_array($webhookType, ['transfer'], true) && in_array($direction, ['inwards', 'credit'], true))
            || ($eventType === 'account.credit' && $direction !== 'outwards');

        $isCompleted = in_array($status, ['completed', 'success', 'successful', 'approved'], true)
            || $responseCode === '00';

        if (!$accountNumber || !$providerReference || !$isIncomingTransfer || !$isCompleted || $isReversed) {
            return null;
        }

        $amount = $this->normalizeMoneyValue($data['amount'] ?? $payload['amount'] ?? null);

        if ($amount === null || bccomp($amount, '0.00000000', 8) <= 0) {
            return null;
        }

        return [
            'event_type' => $eventType !== '' ? $eventType : null,
            'webhook_type' => $webhookType,
            'direction' => $direction,
            'status' => $status,
            'response_code' => $responseCode !== '' ? $responseCode : null,
            'settled_at' => $this->normalizeTimestamp(
                $data['approvedAt']
                ?? $data['updatedAt']
                ?? $payload['approvedAt']
                ?? $payload['updatedAt']
                ?? $data['createdAt']
                ?? $payload['createdAt']
                ?? null
            ),
            'account_number' => (string) $accountNumber,
            'account_id' => $accountId ? (string) $accountId : null,
            'provider_reference' => (string) $providerReference,
            'amount' => $amount,
            'provider_fee' => $this->normalizeMoneyValue($data['fees'] ?? $payload['fees'] ?? 0) ?? '0.00000000',
            'vat' => $this->normalizeMoneyValue($data['vat'] ?? $payload['vat'] ?? 0) ?? '0.00000000',
            'stamp_duty' => $this->normalizeMoneyValue($data['stampDuty'] ?? $payload['stampDuty'] ?? 0) ?? '0.00000000',
        ];
    }

    protected function findUserWalletByCurrency(User $user, string $currencyCode, bool $lockForUpdate = false): ?UserWallet
    {
        $normalizedCurrency = strtoupper($currencyCode);

        $query = $user->wallets()->where(function ($walletQuery) use ($normalizedCurrency) {
            $walletQuery->where('currency_code', $normalizedCurrency);

            if (Schema::hasTable('currencies')) {
                $walletQuery->orWhereHas('currency', function ($currencyQuery) use ($normalizedCurrency) {
                    $currencyQuery->where('code', $normalizedCurrency);
                });
            }
        });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    protected function calculateSettlementCreditAmount(string $amount, string $providerFee, string $vat, string $stampDuty): string
    {
        $netAmount = bcsub($amount, $providerFee, 8);
        $netAmount = bcsub($netAmount, $vat, 8);

        return bcsub($netAmount, $stampDuty, 8);
    }

    protected function findVirtualAccountForSettlement(string $accountNumber): ?VirtualAccounts
    {
        $virtualAccount = VirtualAccounts::where('account_number', $accountNumber)
            ->where(function ($query) {
                $query->where('provider', 'safehaven')
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('provider')
                            ->where(function ($accountQuery) {
                                $accountQuery->where('bank_name', 'like', '%SafeHaven%')
                                    ->orWhereIn('bank_code', ['090286', '090281']);
                            });
                    });
            })
            ->latest('id')
            ->first();

        if ($virtualAccount && empty($virtualAccount->provider)) {
            $virtualAccount->update(['provider' => 'safehaven']);
        }

        return $virtualAccount;
    }

    protected function markSettlementEventAsFailed(SafeHavenWebhookEvent $event, string $message): void
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    protected function markLinkedTransactionsAsCompleted(string $providerReference, UserWallet $wallet): void
    {
        Transaction::query()
            ->where('trx_id', $providerReference)
            ->where('wallet_id', $wallet->id)
            ->where('attribute', GlobalConst::RECEIVED)
            ->where('status', PaymentGatewayConst::STATUSPENDING)
            ->update([
                'status' => PaymentGatewayConst::STATUSSUCCESS,
                'available_balance' => $wallet->balance,
                'updated_at' => now(),
            ]);
    }

    protected function settlementLedgerReference(string $providerReference): string
    {
        return 'safehaven:settlement:' . $providerReference;
    }

    protected function reconcileCompletedOffRampPayout(User $user, array $settlement, UserWallet $wallet, string $creditAmount): ?RampTransaction
    {
        $transaction = $this->findMatchingOffRampTransactionForSettlement($user, $settlement, $creditAmount);

        if (!$transaction) {
            return null;
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $settlementMetadata = is_array($metadata['settlement'] ?? null) ? $metadata['settlement'] : [];
        $fiatPayout = is_array($settlementMetadata['fiat_payout'] ?? null) ? $settlementMetadata['fiat_payout'] : [];

        $transaction->update([
            'status' => 'completed',
            'to_amount' => $transaction->to_amount ?? $creditAmount,
            'metadata' => array_merge($metadata, [
                'settlement' => array_merge($settlementMetadata, [
                    'fiat_payout' => array_merge($fiatPayout, [
                        'provider' => 'safehaven',
                        'status' => 'completed',
                        'provider_reference' => $settlement['provider_reference'],
                        'account_number' => $settlement['account_number'],
                        'gross_amount' => $settlement['amount'],
                        'net_amount' => $creditAmount,
                        'provider_fee' => $settlement['provider_fee'],
                        'vat' => $settlement['vat'],
                        'stamp_duty' => $settlement['stamp_duty'],
                        'credited_at' => $settlement['settled_at'] ?? now()->toIso8601String(),
                    ]),
                ]),
                'wallet_update' => [
                    'currency' => 'NGN',
                    'credited_amount' => $creditAmount,
                    'balance_after' => (string) $wallet->balance,
                    'sync_method' => 'safehaven_settlement',
                    'provider_reference' => $settlement['provider_reference'],
                    'checked_at' => now()->toIso8601String(),
                ],
                'provider_status_checked_at' => now()->toIso8601String(),
                'safehaven_settlement' => [
                    'provider_reference' => $settlement['provider_reference'],
                    'reconciled_at' => now()->toIso8601String(),
                ],
                'reservation_released_at' => $metadata['reservation_released_at'] ?? now()->toDateTimeString(),
                'reservation_release_reason' => $metadata['reservation_release_reason'] ?? 'safehaven_settlement_completed',
            ]),
        ]);

        return $transaction->fresh();
    }

    protected function findMatchingOffRampTransactionForSettlement(User $user, array $settlement, string $creditAmount): ?RampTransaction
    {
        $settledAt = $this->parseTimestamp($settlement['settled_at'] ?? null) ?? now();

        $candidates = RampTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'off_ramp')
            ->whereIn('status', ['processing', 'confirmed', 'awaiting_payout'])
            ->where('account_number', $settlement['account_number'])
            ->where('created_at', '<=', $settledAt)
            ->where('created_at', '>=', $settledAt->copy()->subDay())
            ->lockForUpdate()
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sort(function (RampTransaction $left, RampTransaction $right) use ($settlement, $creditAmount, $settledAt) {
                return $this->offRampSettlementCandidateScore($left, $settlement, $creditAmount, $settledAt)
                    <=> $this->offRampSettlementCandidateScore($right, $settlement, $creditAmount, $settledAt);
            })
            ->first();
    }

    protected function offRampSettlementCandidateScore(RampTransaction $transaction, array $settlement, string $creditAmount, Carbon $settledAt): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $jobMetadata = is_array($metadata['job'] ?? null) ? $metadata['job'] : [];
        $payoutRequestedAt = $this->parseTimestamp($jobMetadata['ramp_withdrawal_requested_at'] ?? null)
            ?? $transaction->updated_at
            ?? $transaction->created_at;
        $expectedAmount = is_numeric($transaction->to_amount) ? (float) $transaction->to_amount : null;
        $grossAmount = (float) $settlement['amount'];
        $netAmount = (float) $creditAmount;

        $amountDiff = $expectedAmount === null
            ? PHP_FLOAT_MAX
            : min(abs($expectedAmount - $grossAmount), abs($expectedAmount - $netAmount));

        return [
            empty($jobMetadata['ramp_withdrawal']) ? 1 : 0,
            $payoutRequestedAt instanceof Carbon && $payoutRequestedAt->greaterThan($settledAt) ? 1 : 0,
            round($amountDiff, 4),
            $payoutRequestedAt instanceof Carbon ? abs($settledAt->diffInSeconds($payoutRequestedAt)) : PHP_INT_MAX,
            $transaction->id,
        ];
    }

    protected function normalizeMoneyValue($value): ?string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }

    protected function normalizeTimestamp($value): ?string
    {
        return $this->parseTimestamp($value)?->toIso8601String();
    }

    protected function parseTimestamp($value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception $e) {
            return null;
        }
    }
   
/**
 * Get SafeHaven transfer status.
 *
 * SafeHaven requires:
 * - sessionId
 * - paymentReference
 */
public function transferStatus(
    string $sessionId,
    string $paymentReference
): array {
    if (trim($sessionId) === '') {
        throw new Exception(
            'SafeHaven transfer session ID is required.'
        );
    }

    if (trim($paymentReference) === '') {
        throw new Exception(
            'SafeHaven payment reference is required.'
        );
    }

    $response = $this->transferHelper->transferStatus([
        'sessionId' => $sessionId,
        'paymentReference' => $paymentReference,
    ]);

    if (
        ($response['status'] ?? false) !== true
        && (int) ($response['statusCode'] ?? 0) !== 200
    ) {
        throw new Exception(
            'SafeHaven Transfer Status Check Failed: '
            . (
                $response['message']
                ?? $response['description']
                ?? 'Unknown error'
            )
        );
    }

    return $response;
}

}
