<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Jobs\ProcessRampSell;
use App\Models\Bank;
use App\Models\KycVerification;
use App\Models\RampTransaction;
use App\Models\SafeHavenWebhookEvent;
use App\Models\UserWallet;
use App\Notifications\RampBuyNotification;
use App\Notifications\RampSellNotification;
use App\Services\QuidaxRampService;
use App\Services\QuidaxService;
use App\Services\QuidaxSpendableBalanceService;
use App\Services\RampTransactionSyncService;
use App\Services\SafeHavenService;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BuyAndSellController extends Controller
{
    protected QuidaxRampService $rampService;
    protected QuidaxService $quidaxService;
    protected QuidaxSpendableBalanceService $quidaxSpendableBalanceService;
    protected SafeHavenService $safehavenService;

    public function __construct(
        QuidaxRampService $rampService,
        QuidaxService $quidaxService,
        QuidaxSpendableBalanceService $quidaxSpendableBalanceService,
        SafeHavenService $safehavenService
    ) {
        $this->rampService = $rampService;
        $this->quidaxService = $quidaxService;
        $this->quidaxSpendableBalanceService = $quidaxSpendableBalanceService;
        $this->safehavenService = $safehavenService;
    }

    public function getSupportedAssets()
    {
        return Response::successResponse('Supported assets fetched successfully', [
            ['code' => 'USDT', 'name' => 'Tether'],
            ['code' => 'USDC', 'name' => 'USD Coin'],
        ]);
    }

    public function getNetworksForAsset($asset)
    {
        $asset = strtoupper($asset);
        $networks = [];

        if (in_array($asset, ['USDT', 'USDC'], true)) {
            $networks = [
                ['code' => 'TRC20', 'name' => 'Tron (TRC20)'],
                ['code' => 'BEP20', 'name' => 'BNB Smart Chain (BEP20)'],
                ['code' => 'ERC20', 'name' => 'Ethereum (ERC20)'],
                ['code' => 'POLYGON', 'name' => 'Polygon'],
            ];
        }

        return Response::successResponse("Supported networks for {$asset} fetched successfully", $networks);
    }

    public function getSupportedFiats()
    {
        return Response::successResponse('Supported fiats fetched successfully', [
            ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => 'NGN'],
        ]);
    }

    public function getBanks(Request $request)
    {
        try {
            $country = strtoupper($request->get('country', 'NG'));
            $result = $this->rampService->getBanks($country);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to fetch ramp bank list');
            }

            $banks = collect($result['data'] ?? [])->map(function ($bank) use ($country) {
                return [
                    'name' => $bank['name'] ?? $bank['bank_name'] ?? $bank['bankName'] ?? null,
                    'code' => (string) ($bank['code'] ?? $bank['bank_code'] ?? $bank['bankCode'] ?? ''),
                    'country' => strtoupper($bank['country'] ?? $country),
                ];
            })->filter(fn ($bank) => !empty($bank['name']) && !empty($bank['code']))->values();

            return Response::successResponse('Ramp banks fetched successfully', [
                'country' => $country,
                'banks' => $banks,
            ]);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function getQuote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:buy,sell',
            'currency' => 'required|string',
            'network' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $customerProfile = $this->resolveRampCustomerProfile($user);
            $type = $request->type;
            $cryptoCurrency = strtolower($request->currency);
            $network = strtolower($request->network);
            $merchantReference = 'QUOTE_' . $user->id . '_' . Str::random(8);

            if ($type === 'buy') {
                $result = $this->rampService->initiateOnRamp([
                    'from_currency' => 'ngn',
                    'to_currency' => $cryptoCurrency,
                    'from_amount' => (string) $request->amount,
                    'network' => $network,
                    'merchant_reference' => $merchantReference,
                    'customer' => $customerProfile['payload'],
                    'wallet_address' => [
                        'address' => '0x' . str_repeat('0', 40),
                        'network' => $network,
                    ],
                ]);
            } else {
                $result = $this->rampService->initiateOffRamp([
                    'from_currency' => $cryptoCurrency,
                    'to_currency' => 'ngn',
                    'from_amount' => (string) $request->amount,
                    'network' => $network,
                    'merchant_reference' => $merchantReference,
                    'customer' => $customerProfile['payload'],
                ]);
            }

            if (!$this->isSuccessfulRampResponse($result)) {
                throw new Exception($result['message'] ?? "Failed to fetch {$type} quote from our transaction provider.");
            }

            $data = is_array($result['data']) ? $result['data'] : [];
            $fromAmount = (float) ($data['from_amount'] ?? 0);
            $toAmount = (float) ($data['to_amount'] ?? 0);
            $rate = 0;

            if ($fromAmount > 0 && $toAmount > 0) {
                $rate = $type === 'buy'
                    ? round($fromAmount / $toAmount, 2)
                    : round($toAmount / $fromAmount, 2);
            }

            return Response::successResponse('Quote fetched successfully', [
                'type' => $type,
                'merchant_reference' => $merchantReference,
                'from_currency' => strtoupper((string) ($data['from_currency'] ?? '')),
                'to_currency' => strtoupper((string) ($data['to_currency'] ?? '')),
                'from_amount' => $fromAmount,
                'to_amount' => $toAmount,
                'blockchain_fee' => (float) ($data['blockchain_fee'] ?? 0),
                'rate' => $rate,
            ]);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function initiateOnRamp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'from_amount' => 'required|numeric|min:1',
            'network' => 'required|string',
            'wallet_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $customerProfile = $this->resolveRampCustomerProfile($user);
            $payload = [
                'from_currency' => strtolower($request->from_currency),
                'to_currency' => strtolower($request->to_currency),
                'from_amount' => (string) $request->from_amount,
                'merchant_reference' => 'ONRAMP_' . $user->id . '_' . Str::random(10),
                'customer' => $customerProfile['payload'],
                'wallet_address' => [
                    'address' => $request->wallet_address,
                    'network' => strtolower($request->network),
                ],
            ];

            $result = $this->rampService->initiateOnRamp($payload);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to initiate on-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];

            $transaction = RampTransaction::create(array_merge([
                'user_id' => $user->id,
                'merchant_reference' => $payload['merchant_reference'],
                'public_id' => $data['public_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'type' => 'on_ramp',
                'from_currency' => $data['from_currency'] ?? strtolower($request->from_currency),
                'to_currency' => $data['to_currency'] ?? strtolower($request->to_currency),
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'network' => strtolower($request->network),
                'wallet_address' => $request->wallet_address,
                'blockchain_fee' => $data['blockchain_fee'] ?? null,
                'stamp_charge' => $data['stamp_charge'] ?? null,
                'status' => 'pending',
                'metadata' => [
                    'customer_profile' => $customerProfile['debug'],
                    'initiate_data' => $data,
                ],
            ], $this->providerTrackingAttributesFromData($data, 'pending')));

            return Response::successResponse($result['message'] ?? 'On-ramp transaction initiated successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function refreshOnRamp(Request $request, string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'on_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            if ($transaction->status !== 'pending') {
                return $this->fetchOnRampStatusResponse($transaction);
            }

            $validator = Validator::make($request->all(), [
                'from_currency' => 'required|string',
                'to_currency' => 'required|string',
                'from_amount' => 'required|numeric|min:1',
            ]);

            if ($validator->fails()) {
                return Response::errorResponse($validator->errors()->first());
            }

            $result = $this->rampService->refreshOnRamp($merchantReference, [
                'from_currency' => strtolower($request->from_currency),
                'to_currency' => strtolower($request->to_currency),
                'from_amount' => (string) $request->from_amount,
            ]);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to refresh on-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];

            $transaction->update(array_merge([
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'blockchain_fee' => $data['blockchain_fee'] ?? null,
                'stamp_charge' => $data['stamp_charge'] ?? $transaction->stamp_charge,
                'metadata' => $this->mergeMetadata($transaction->metadata, ['refresh_data' => $data]),
            ], $this->providerTrackingAttributesFromData($data, (string) $transaction->status)));

            $transaction->refresh();

            return Response::successResponse($result['message'] ?? 'On-ramp transaction refreshed successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function getOnRamp(string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'on_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            return $this->fetchOnRampStatusResponse($transaction);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function confirmOnRamp(Request $request, string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'on_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            if (in_array($transaction->status, ['confirmed', 'processing', 'completed'], true)) {
                return $this->fetchOnRampStatusResponse($transaction);
            }

            if ($transaction->status !== 'pending') {
                return Response::errorResponse('Transaction has already been processed or is not pending.', null, 409);
            }

            $locked = RampTransaction::whereKey($transaction->id)
                ->where('status', 'pending')
                ->update(['status' => 'processing']);

            if (!$locked) {
                $transaction->refresh();
                return Response::errorResponse('Transaction is already being processed. Please try again shortly.', null, 409);
            }

            $transaction->refresh();
            $result = $this->rampService->confirmOnRamp($merchantReference);

            if (!$this->isSuccessfulRampResponse($result) && $this->shouldFetchExistingTransaction($result)) {
                $result = $this->rampService->onRampTransaction($merchantReference);
            }

            if (!$this->isSuccessfulRampResponse($result)) {
                $transaction->update(['status' => 'pending']);
                return $this->providerErrorResponse($result, 'Failed to confirm on-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];
            $accountNumber = $data['account_number'] ?? null;
            $bankCode = $data['bank_code'] ?? null;
            $amountExpected = $data['amount_expected'] ?? $data['amount'] ?? null;

            if (!$accountNumber || !$bankCode || !$amountExpected) {
                throw new Exception('Provider response is missing payment account details.');
            }

            $bank = Bank::where('is_active', true)
                ->where(function ($query) use ($data, $bankCode) {
                    $query->where('code', $bankCode);

                    if (!empty($data['bank_name'])) {
                        $query->orWhere('name', 'like', '%' . $data['bank_name'] . '%');
                    }
                })
                ->first();

            $beneficiaryBankCode = $bank->code ?? $bankCode;
            $bankName = $bank->name ?? ($data['bank_name'] ?? null);

            $enquiryResponse = $this->safehavenService->nameEnquiry($beneficiaryBankCode, $accountNumber);
            $sessionId = $this->extractSafeHavenSessionId($enquiryResponse);

            if (!$sessionId) {
                throw new Exception('Failed to verify payment account.');
            }

            $debitAccountNumber = $this->getSafeHavenAccountNumber($user);
            if (!$debitAccountNumber) {
                throw new Exception('User does not have a SafeHaven account to debit.');
            }

            $transferResponse = $this->safehavenService->transfer([
                'saveBeneficiary' => false,
                'nameEnquiryReference' => $sessionId,
                'debitAccountNumber' => $debitAccountNumber,
                'beneficiaryBankCode' => $beneficiaryBankCode,
                'beneficiaryAccountNumber' => $accountNumber,
                'amount' => (float) $amountExpected,
                'narration' => 'Ramp on-ramp payment',
                'paymentReference' => 'ramp_buy_' . Str::random(12),
            ]);

            $walletUpdate = $this->syncOnRampWalletBalance(
                $user,
                (string) strtoupper((string) $transaction->from_currency),
                $this->formatAmount($amountExpected) ?? (string) $amountExpected,
                $transaction->merchant_reference,
                is_array($transferResponse) ? $transferResponse : []
            );

            $transaction->update(array_merge([
                'status' => 'confirmed',
                'public_id' => $transaction->public_id ?? ($data['public_id'] ?? null),
                'bank_name' => $bankName,
                'bank_code' => $beneficiaryBankCode,
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $accountNumber,
                'processor_fee' => $data['processor_fee'] ?? null,
                'vat' => $data['vat'] ?? null,
                'transfer_session_id' => $sessionId,
                'metadata' => $this->mergeMetadata($transaction->metadata, [
                    'confirm_data' => $data,
                    'transfer_data' => is_array($transferResponse) ? $transferResponse : [],
                    'wallet_update' => $walletUpdate,
                ]),
            ], $this->providerTrackingAttributesFromData($data, 'confirmed')));

            $transaction->refresh();
            $user->notify(new RampBuyNotification($transaction));

            return Response::successResponse($result['message'] ?? 'On-ramp transaction confirmed successfully', $this->buildOnRampConfirmPayload($transaction));
        } catch (Exception $e) {
            if (isset($transaction) && $transaction instanceof RampTransaction && $transaction->status === 'processing') {
                $transaction->update(['status' => 'pending']);
            }

            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function initiateOffRamp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'from_amount' => 'required|numeric|min:0.01',
            'network' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $customerProfile = $this->resolveOffRampCustomerProfile($user);
            $payload = [
                'from_currency' => strtolower($request->from_currency),
                'to_currency' => strtolower($request->to_currency),
                'from_amount' => (string) $request->from_amount,
                'network' => strtolower($request->network),
                'merchant_reference' => 'OFFRAMP_' . $user->id . '_' . Str::random(10),
                'customer' => $customerProfile['payload'],
            ];

            $result = $this->rampService->initiateOffRamp($payload);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to initiate off-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];

            $transaction = RampTransaction::create(array_merge([
                'user_id' => $user->id,
                'merchant_reference' => $payload['merchant_reference'],
                'public_id' => $data['public_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'type' => 'off_ramp',
                'from_currency' => $data['from_currency'] ?? strtolower($request->from_currency),
                'to_currency' => $data['to_currency'] ?? strtolower($request->to_currency),
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'network' => strtolower($request->network),
                'status' => 'pending',
                'metadata' => [
                    'customer_profile' => $customerProfile['debug'],
                    'initiate_data' => $data,
                ],
            ], $this->providerTrackingAttributesFromData($data, 'pending')));

            $bankSetup = [
                'attached' => false,
                'message' => 'No payout bank account attached yet. Call the bank-account endpoint before confirming if automatic setup is unavailable.',
            ];

            $defaultBankPayload = $this->getDefaultOffRampBankPayload($user);

            if ($defaultBankPayload) {
                $bankResult = $this->attachOffRampBankAccount($transaction, $defaultBankPayload);

                if ($this->isSuccessfulRampResponse($bankResult)) {
                    $transaction->refresh();
                    $bankSetup = [
                        'attached' => true,
                        'message' => $bankResult['message'] ?? 'Default payout bank account attached successfully.',
                    ];
                } else {
                    $bankSetup = [
                        'attached' => false,
                        'message' => $bankResult['message'] ?? 'Automatic payout bank setup failed. Attach a bank account before confirming.',
                        'details' => $bankResult['data'] ?? null,
                    ];
                }
            }

            return Response::successResponse($result['message'] ?? 'Off-ramp transaction initiated successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                [
                    'quote' => $data,
                    'payout_account' => $this->formatBankAccountSummary($transaction),
                    'bank_account_setup' => $bankSetup,
                ]
            ));
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function refreshOffRamp(Request $request, string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'off_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            if ($transaction->status !== 'pending') {
                return $this->fetchOffRampStatusResponse($transaction);
            }

            $validator = Validator::make($request->all(), [
                'from_currency' => 'required|string',
                'to_currency' => 'required|string',
                'from_amount' => 'required|numeric|min:0.01',
                'network' => 'required|string',
            ]);

            if ($validator->fails()) {
                return Response::errorResponse($validator->errors()->first());
            }

            $result = $this->rampService->refreshOffRamp($merchantReference, [
                'from_currency' => strtolower($request->from_currency),
                'to_currency' => strtolower($request->to_currency),
                'from_amount' => (string) $request->from_amount,
                'network' => strtolower($request->network),
            ]);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to refresh off-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];

            $transaction->update(array_merge([
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'network' => strtolower($request->network),
                'metadata' => $this->mergeMetadata($transaction->metadata, ['refresh_data' => $data]),
            ], $this->providerTrackingAttributesFromData($data, (string) $transaction->status)));

            $transaction->refresh();

            return Response::successResponse($result['message'] ?? 'Off-ramp transaction refreshed successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function getOffRamp(string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'off_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            return $this->fetchOffRampStatusResponse($transaction);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function addBankAccountOffRamp(Request $request, string $merchantReference)
    {
        $validator = Validator::make($request->all(), [
            'bank_code' => 'required|string',
            'account_number' => 'required|string',
            'currency_code' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'account_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'off_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            if ($transaction->status !== 'pending') {
                return Response::errorResponse('Only pending transactions can be updated with payout bank details.', null, 409);
            }

            $payload = [
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
                'currency_code' => $request->currency_code ?? 'ngn',
            ];

            if ($request->filled('bank_name')) {
                $payload['bank_name'] = $request->bank_name;
            }

            if ($request->filled('account_name')) {
                $payload['account_name'] = $request->account_name;
            }

            $result = $this->attachOffRampBankAccount($transaction, $payload, [
                'bank_name' => $request->bank_name,
                'account_name' => $request->account_name,
            ]);

            if (!$this->isSuccessfulRampResponse($result)) {
                return $this->providerErrorResponse($result, 'Failed to add bank account');
            }

            $transaction->refresh();

            return Response::successResponse($result['message'] ?? 'Payout bank account attached successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                [
                    'payout_account' => $this->formatBankAccountSummary($transaction),
                    'bank_account_response' => $result['data'] ?? null,
                ]
            ));
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function confirmOffRamp(Request $request, string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'off_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }
            

            if (in_array($transaction->status, ['awaiting_payout', 'completed'], true)) {
                return $this->fetchOffRampStatusResponse($transaction);
            }

            if ($transaction->status !== 'pending') {
                return Response::errorResponse('Transaction has already been processed or is not pending.', null, 409);
            }

            $locked = RampTransaction::whereKey($transaction->id)
                ->where('status', 'pending')
                ->update(['status' => 'processing']);

            if (!$locked) {
                $transaction->refresh();
                return Response::errorResponse('Transaction is already being processed. Please try again shortly.', null, 409);
            }

            $transaction->refresh();

            if (!$transaction->bank_code || !$transaction->account_number) {
        
                $defaultBankPayload = $this->getDefaultOffRampBankPayload($user);
                

                if (!$defaultBankPayload) {
                    $transaction->update(['status' => 'pending']);
                    return Response::errorResponse('Please attach a payout bank account before confirming this off-ramp transaction.', null, 422);
                }
                $bankResult = $this->attachOffRampBankAccount($transaction, $defaultBankPayload);

                if (!$this->isSuccessfulRampResponse($bankResult)) {
                    $transaction->update(['status' => 'pending']);
                    return $this->providerErrorResponse($bankResult, 'Failed to attach payout bank account before confirmation', 422);
                }

                $transaction->refresh();
            }

            $sourceCurrency = strtolower((string) $transaction->from_currency);
            $sourceAmount = $this->decimalAmount($transaction->from_amount);
            $network = strtolower((string) $transaction->network);
            $reference = $transaction->merchant_reference;

            if (!$user->quidax_id) {
                throw new Exception('User does not have a linked crypto trading account.');
            }

            Cache::forget("quidax.wallet.{$user->quidax_id}.{$sourceCurrency}");
            Cache::forget("quidax_wallet:{$user->quidax_id}:{$sourceCurrency}");

            $quidaxWalletResponse = $this->quidaxService->fetchUserWallet($user->quidax_id, $sourceCurrency);
    
            if (($quidaxWalletResponse['status'] ?? '') !== 'success') {
                throw new Exception($quidaxWalletResponse['message'] ?? "Could not fetch crypto wallet for {$sourceCurrency}.");
            }

            $feeResponse = $this->quidaxService->getWithdrawalFee($sourceCurrency,$sourceAmount, $network);
            if (!in_array(strtolower((string) ($feeResponse['status'] ?? '')), ['success', 'ok'], true) || !array_key_exists('fee', $feeResponse['data'] ?? [])) {
                throw new Exception($feeResponse['message'] ?? 'Could not determine withdrawal fee.');
            }

            $feeType = $feeResponse['data']['type'] ?? 'flat';
            $quidaxFee = $this->calculateSellFee($sourceAmount, $feeResponse['data']['fee'] ?? '0', $feeType);
            $platformFee = $this->bcMulAmount($quidaxFee, '1.25');
            $processorFee = $this->bcSubAmount($platformFee, $quidaxFee);
            $totalAmountToWithdrawFromUser = $this->bcAddAmount($sourceAmount, $platformFee);
            
            $quidaxWalletData = $this->quidaxSpendableBalanceService->augmentWalletPayload(
                $user,
                $this->normalizeQuidaxWalletPayload($quidaxWalletResponse['data']),
                $sourceCurrency,
                null,
                ['exclude_ramp_transaction_id' => $transaction->id]
            );
            
            $quidaxBalance = $this->decimalAmount($quidaxWalletData['available_balance'] ?? '0');

            $balanceContext = [
                'user_id' => $user->id,
                'merchant_reference' => $reference,
                'currency' => $sourceCurrency,
                'network' => $network,
                'provider_balance' => $quidaxWalletData['provider_balance'] ?? null,
                'provider_locked_balance' => $quidaxWalletData['provider_locked_balance'] ?? null,
                'reserved_outgoing_balance' => $quidaxWalletData['reserved_outgoing_balance'] ?? null,
                'available_balance' => $this->formatDecimalAmount($quidaxBalance),
                'requested_sell_amount' => $this->formatDecimalAmount($sourceAmount),
                'quidax_fee' => $this->formatDecimalAmount($quidaxFee),
                'platform_fee' => $this->formatDecimalAmount($platformFee),
                'processor_fee' => $this->formatDecimalAmount($processorFee),
                'total_required' => $this->formatDecimalAmount($totalAmountToWithdrawFromUser),
                'fee_type' => $feeType,
            ];

            Log::info('Ramp off-ramp balance validation before sell confirmation', $balanceContext);

            if ($this->compareDecimalAmounts($quidaxBalance, $totalAmountToWithdrawFromUser) < 0) {
                Log::warning('Ramp off-ramp insufficient available balance before sell confirmation', $balanceContext);
                $transaction->update(['status' => 'pending']);

                return Response::errorResponse(
                    "Insufficient available balance. Required: {$this->formatDecimalAmount($totalAmountToWithdrawFromUser)} {$sourceCurrency} (including {$this->formatDecimalAmount($platformFee)} fee) but available: {$this->formatDecimalAmount($quidaxBalance)} {$sourceCurrency}.",
                    [
                        'currency' => $sourceCurrency,
                        'network' => $network,
                        'amount' => $this->formatDecimalAmount($sourceAmount),
                        'fee' => $this->formatDecimalAmount($platformFee),
                        'quidax_fee' => $this->formatDecimalAmount($quidaxFee),
                        'processor_fee' => $this->formatDecimalAmount($processorFee),
                        'total' => $this->formatDecimalAmount($totalAmountToWithdrawFromUser),
                        'available_balance' => $this->formatDecimalAmount($quidaxBalance),
                        'provider_balance' => $quidaxWalletData['provider_balance'] ?? null,
                        'reserved_outgoing_balance' => $quidaxWalletData['reserved_outgoing_balance'] ?? null,
                    ],
                    422
                );
            }

            $result = $this->rampService->confirmOffRamp($merchantReference);
    
            Log::info('Ramp off-ramp provider confirmation response', [
                'user_id' => $user->id,
                'merchant_reference' => $reference,
                'provider_status' => $result['status'] ?? null,
                'provider_http_status' => $result['http_status'] ?? null,
                'provider_message' => $result['message'] ?? null,
            ]);

            if (!$this->isSuccessfulRampResponse($result) && $this->shouldFetchExistingTransaction($result)) {
                $result = $this->rampService->offRampTransaction($merchantReference);
            }

            if (!$this->isSuccessfulRampResponse($result)) {
                $transaction->update(['status' => 'pending']);
                return $this->providerErrorResponse($result, 'Failed to confirm off-ramp transaction');
            }

            $data = is_array($result['data']) ? $result['data'] : [];
            $rampAddress = $data['address'] ?? null;

            if (!$rampAddress) {
                throw new Exception('Provider response is missing a ramp deposit address.');
            }

            $network = strtolower((string) ($data['network'] ?? $transaction->network));

            $mainAccountResponse = $this->quidaxService->getUser();
            $mainAccountId = $mainAccountResponse['data']['id'] ?? 'me';

            if (!$mainAccountId) {
                throw new Exception('Could not determine the Quidax main account identifier.');
            }

            DB::beginTransaction();

            $transaction->update(array_merge([
                'status' => 'processing',
                'wallet_address' => $rampAddress,
                'network' => $network,
                'blockchain_fee' => $this->formatDecimalAmount($quidaxFee),
                'processor_fee' => $this->formatDecimalAmount($processorFee),
                'metadata' => $this->mergeMetadata($transaction->metadata, [
                    'confirm_data' => $data,
                    'sell_balance_validation' => $balanceContext,
                    'fees' => [
                        'quidax_fee' => $this->formatDecimalAmount($quidaxFee),
                        'platform_fee' => $this->formatDecimalAmount($platformFee),
                        'processor_fee' => $this->formatDecimalAmount($processorFee),
                        'total' => $this->formatDecimalAmount($totalAmountToWithdrawFromUser),
                    ],
                ]),
            ], $this->providerTrackingAttributesFromData($data, 'processing')));

            $mainAccountWithdrawalData = [
                'currency' => $sourceCurrency,
                'network' => $network,
                'amount' => $this->formatDecimalAmount($totalAmountToWithdrawFromUser),
                'fund_uid' => $mainAccountId,
                'transaction_note' => "Off-ramp move to main account: {$reference}",
                'narration' => "Off-ramp move to main account: {$reference}",
            ];
            ProcessRampSell::dispatchAfterResponse(
    $user,
    $reference,
    $mainAccountWithdrawalData,          // must contain the FULL amount (source + fee)
    $sourceCurrency,
    $this->formatDecimalAmount($sourceAmount), // pure sell amount
    $rampAddress,
    $network
);

            $transaction->refresh();
            $user->notify(new RampSellNotification($transaction));

            DB::commit();

            return Response::successResponse('Off-ramp trade initiated successfully. Processing funds movement.', $this->buildOffRampConfirmPayload($transaction));
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if (isset($transaction) && $transaction instanceof RampTransaction && $transaction->status === 'processing') {
                $transaction->update(['status' => 'pending']);
            }

            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    protected function isSuccessfulRampResponse(array $response): bool
    {
        return ($response['ok'] ?? false) === true
            || in_array($response['status'] ?? '', ['ok', 'success'], true);
    }

    protected function shouldFetchExistingTransaction(array $response): bool
    {
        return in_array($response['status'] ?? '', ['bad_request', 'conflict', 'unprocessable_entity'], true);
    }

    protected function providerErrorResponse(array $result, string $fallbackMessage, int $defaultStatus = 400)
    {
        $status = (int) ($result['http_status'] ?? $defaultStatus);

        if ($status < 400) {
            $status = $defaultStatus;
        }

        return Response::errorResponse(
            $result['message'] ?? $fallbackMessage,
            $result['data'] ?? null,
            $status
        );
    }

    protected function findUserTransaction(int $userId, string $merchantReference, string $type): ?RampTransaction
    {
        return RampTransaction::where('user_id', $userId)
            ->where('merchant_reference', $merchantReference)
            ->where('type', $type)
            ->first();
    }

    protected function getSafeHavenAccountNumber($user): ?string
    {
        return optional($this->resolveSafeHavenSubAccount($user))->account_number;
    }

    protected function resolveRampCustomerProfile($user): array
    {
        $resolvedName = $this->resolveRampCustomerName($user);

        return [
            'payload' => [
                'email' => $user->email,
                'first_name' => $resolvedName['first_name'],
                'last_name' => $resolvedName['last_name'],
            ],
            'debug' => [
                'source' => $resolvedName['source'],
                'full_name' => $resolvedName['full_name'],
                'first_name' => $resolvedName['first_name'],
                'last_name' => $resolvedName['last_name'],
                'canonical_name' => $this->canonicalizePersonName($resolvedName['full_name']),
                'first_last_canonical_name' => $this->canonicalizePersonName($resolvedName['full_name'], true),
            ],
        ];
    }

    protected function resolveOffRampCustomerProfile($user): array
    {
        $resolvedName = $this->resolveOffRampCustomerName($user);

        return [
            'payload' => [
                'email' => $user->email,
                'first_name' => $resolvedName['first_name'],
                'last_name' => $resolvedName['last_name'],
            ],
            'debug' => [
                'source' => $resolvedName['source'],
                'full_name' => $resolvedName['full_name'],
                'first_name' => $resolvedName['first_name'],
                'last_name' => $resolvedName['last_name'],
                'canonical_name' => $this->canonicalizePersonName($resolvedName['full_name']),
                'first_last_canonical_name' => $this->canonicalizePersonName($resolvedName['full_name'], true),
            ],
        ];
    }

    protected function resolveRampCustomerName($user): array
    {
        $fallbackFirstName = $this->normalizePersonName($user->firstname ?? $user->name ?? null) ?: 'Customer';
        $fallbackLastName = $this->normalizePersonName($user->lastname ?? $user->surname ?? null) ?: 'User';
        $fallbackFullName = $this->normalizePersonName($fallbackFirstName . ' ' . $fallbackLastName);

        $verifiedKycFullName = $this->resolveVerifiedKycFullName($user);
        if ($verifiedKycFullName) {
            $splitName = $this->splitFullNameForRamp($verifiedKycFullName, $fallbackLastName);

            if ($splitName) {
                return array_merge($splitName, ['source' => 'verified_kyc']);
            }
        }

        $safeHavenAccountName = $this->resolveSafeHavenAccountHolderName($user);
        if ($safeHavenAccountName) {
            $splitName = $this->splitFullNameForRamp($safeHavenAccountName, $fallbackLastName);

            if ($splitName) {
                return array_merge($splitName, ['source' => 'safehaven_account']);
            }
        }

        $splitName = $this->splitFullNameForRamp($fallbackFullName, $fallbackLastName);

        return array_merge($splitName ?? [
            'full_name' => trim($fallbackFirstName . ' ' . $fallbackLastName),
            'first_name' => $fallbackFirstName,
            'last_name' => $fallbackLastName,
        ], ['source' => 'profile']);
    }

    protected function resolveOffRampCustomerName($user): array
    {
        $fallbackFirstName = $this->normalizePersonName($user->firstname ?? $user->name ?? null) ?: 'Customer';
        $fallbackLastName = $this->normalizePersonName($user->lastname ?? $user->surname ?? null) ?: 'User';
        $fallbackFullName = $this->normalizePersonName($fallbackFirstName . ' ' . $fallbackLastName);

        $rawSafeHavenAccountName = $this->normalizePersonName(optional($this->resolveSafeHavenSubAccount($user))->account_name);
        if ($rawSafeHavenAccountName) {
            $splitName = $this->splitExactSafeHavenNameForRamp($rawSafeHavenAccountName, $fallbackLastName);

            if ($splitName) {
                return array_merge($splitName, ['source' => 'safehaven_account_raw']);
            }
        }

        $verifiedKycFullName = $this->resolveVerifiedKycFullName($user);
        if ($verifiedKycFullName) {
            $splitName = $this->splitFullNameForRamp($verifiedKycFullName, $fallbackLastName);

            if ($splitName) {
                return array_merge($splitName, ['source' => 'verified_kyc']);
            }
        }

        $safeHavenAccountName = $this->resolveSafeHavenAccountHolderName($user);
        if ($safeHavenAccountName) {
            $splitName = $this->splitFullNameForRamp($safeHavenAccountName, $fallbackLastName);

            if ($splitName) {
                return array_merge($splitName, ['source' => 'safehaven_account']);
            }
        }

        $splitName = $this->splitFullNameForRamp($fallbackFullName, $fallbackLastName);

        return array_merge($splitName ?? [
            'full_name' => trim($fallbackFirstName . ' ' . $fallbackLastName),
            'first_name' => $fallbackFirstName,
            'last_name' => $fallbackLastName,
        ], ['source' => 'profile']);
    }

    protected function resolveVerifiedKycFullName($user): ?string
    {
        try {
            if (!Schema::hasTable('kyc_verifications')) {
                return null;
            }

            $verifications = KycVerification::query()
                ->where('user_id', $user->id)
                ->where('status', 'verified')
                ->orderByDesc('level')
                ->orderByDesc('id')
                ->get();

            foreach ($verifications as $verification) {
                $fullName = $this->extractVerifiedKycFullNameFromData((array) ($verification->data ?? []));

                if ($fullName) {
                    return $fullName;
                }
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to resolve verified KYC name for ramp customer payload', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function extractVerifiedKycFullNameFromData(array $data): ?string
    {
        $candidateSources = array_filter([
            $data,
            is_array($data['verification_result'] ?? null) ? $data['verification_result'] : null,
            is_array(data_get($data, 'verification_result.providerResponse')) ? data_get($data, 'verification_result.providerResponse') : null,
            is_array(data_get($data, 'verification_result.data')) ? data_get($data, 'verification_result.data') : null,
        ]);

        foreach ($candidateSources as $source) {
            $fullName = $this->extractPersonNameFromDataSource($source);

            if ($fullName) {
                return $fullName;
            }
        }

        return null;
    }

    protected function extractPersonNameFromDataSource(array $source): ?string
    {
        $fullName = $this->normalizePersonName($source['fullName'] ?? $source['full_name'] ?? null);
        if ($fullName) {
            return $fullName;
        }

        return $this->normalizePersonName(implode(' ', array_filter([
            $source['firstName'] ?? $source['first_name'] ?? null,
            $source['middleName'] ?? $source['middle_name'] ?? null,
            $source['lastName'] ?? $source['last_name'] ?? null,
        ])));
    }

    protected function resolveSafeHavenAccountHolderName($user): ?string
    {
        $accountName = $this->normalizePersonName(optional($this->resolveSafeHavenSubAccount($user))->account_name);
        if (!$accountName) {
            return null;
        }

        $knownPrefixes = array_unique(array_filter(array_map(function ($value) {
            return $this->normalizePersonName($value);
        }, [
            config('app.name'),
            config('mail.from.name'),
            'CMART',
            'CRYPTOMART',
            'BITMONIE',
        ])));

        foreach ($knownPrefixes as $prefix) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '\s*\/\s*(.+)$/iu', $accountName, $matches)) {
                return $this->normalizePersonName($matches[1]);
            }
        }

        return $accountName;
    }

    protected function splitFullNameForRamp(?string $fullName, ?string $fallbackLastName = null): ?array
    {
        $normalizedFullName = $this->normalizePersonName($fullName);
        if (!$normalizedFullName) {
            return null;
        }

        $parts = preg_split('/\s+/u', $normalizedFullName, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts || empty($parts[0])) {
            return null;
        }

        $firstName = array_shift($parts);
        $lastName = $this->normalizePersonName(implode(' ', $parts));

        if (!$lastName) {
            $lastName = $this->normalizePersonName($fallbackLastName) ?: 'User';
        }

        return [
            'full_name' => trim($firstName . ' ' . $lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    protected function splitExactSafeHavenNameForRamp(?string $fullName, ?string $fallbackLastName = null): ?array
    {
        $normalizedFullName = $this->normalizePersonName($fullName);
        if (!$normalizedFullName) {
            return null;
        }

        if (preg_match('/^(.+?)\s*\/\s*(.+)$/u', $normalizedFullName, $matches)) {
            $prefix = $this->normalizePersonName($matches[1]);
            $accountHolderName = $this->normalizePersonName($matches[2]);

            if ($prefix && $accountHolderName) {
                $parts = preg_split('/\s+/u', $accountHolderName, -1, PREG_SPLIT_NO_EMPTY);

                if ($parts && count($parts) >= 2) {
                    $firstName = $this->normalizePersonName($prefix . ' / ' . array_shift($parts));
                    $lastName = $this->normalizePersonName(implode(' ', $parts));

                    if ($firstName && $lastName) {
                        return [
                            'full_name' => $normalizedFullName,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                        ];
                    }
                }
            }
        }

        return $this->splitFullNameForRamp($normalizedFullName, $fallbackLastName);
    }

    protected function normalizePersonName($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    protected function canonicalizePersonName(?string $value, bool $firstAndLastOnly = false): ?string
    {
        $normalized = $this->normalizePersonName($value);
        if (!$normalized) {
            return null;
        }

        $ascii = Str::ascii($normalized);
        $ascii = strtolower((string) preg_replace('/[^a-z0-9]+/', ' ', $ascii));
        $parts = preg_split('/\s+/', trim($ascii), -1, PREG_SPLIT_NO_EMPTY);

        if (!$parts) {
            return null;
        }

        if ($firstAndLastOnly && count($parts) > 2) {
            $parts = [$parts[0], $parts[count($parts) - 1]];
        }

        return implode(' ', $parts);
    }

    protected function maskAccountNumber($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
    }

    protected function normalizeProviderErrorData($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if ($data === null) {
            return [];
        }

        return ['provider_data' => $data];
    }

    protected function isNameMismatchRampError(array $result): bool
    {
        $haystacks = [
            strtolower((string) ($result['message'] ?? '')),
            strtolower(json_encode($result['data'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        ];

        foreach ($haystacks as $haystack) {
            if (Str::contains($haystack, ['name does not match', 'names do not match', 'name mismatch'])) {
                return true;
            }
        }

        return false;
    }

    protected function buildOffRampBankLogContext(RampTransaction $transaction, array $payload, array $displayOverrides = []): array
    {
        $metadata = $transaction->metadata ?? [];
        $customerProfile = is_array($metadata['customer_profile'] ?? null)
            ? $metadata['customer_profile']
            : null;

        if (!$customerProfile && $transaction->user) {
            $customerProfile = $this->resolveRampCustomerProfile($transaction->user)['debug'];
        }

        $bankAccountName = $displayOverrides['account_name']
            ?? $payload['account_name']
            ?? $transaction->account_name;

        return [
            'merchant_reference' => $transaction->merchant_reference,
            'user_id' => $transaction->user_id,
            'customer_name_source' => $customerProfile['source'] ?? null,
            'customer_full_name' => $customerProfile['full_name'] ?? null,
            'customer_canonical_name' => $customerProfile['canonical_name'] ?? null,
            'customer_first_last_canonical_name' => $customerProfile['first_last_canonical_name'] ?? null,
            'bank_account_name' => $bankAccountName,
            'bank_account_canonical_name' => $this->canonicalizePersonName($bankAccountName),
            'bank_account_first_last_canonical_name' => $this->canonicalizePersonName($bankAccountName, true),
            'bank_code' => $payload['bank_code'] ?? null,
            'account_number' => $this->maskAccountNumber($payload['account_number'] ?? null),
        ];
    }

    protected function getDefaultOffRampBankPayload($user): ?array
    {
        $account = $this->resolveSafeHavenSubAccount($user);

        if (!$account || !$account->account_number) {
            return null;
        }

        return [
            'bank_code' => $account->bank_code ?: (string) config('services.safeHeaven.bank_code', '090286'),
            'account_number' => $account->account_number,
            'currency_code' => 'ngn',
            'bank_name' => $account->bank_name,
            'account_name' => $account->account_name,
        ];
    }

    protected function attachOffRampBankAccount(RampTransaction $transaction, array $payload, array $displayOverrides = []): array
    {
        $logContext = $this->buildOffRampBankLogContext($transaction, $payload, $displayOverrides);

        Log::info('Ramp off-ramp bank-account attachment attempt', $logContext);

        $result = $this->rampService->addBankAccountOffRamp($transaction->merchant_reference, $payload);

        if (!$this->isSuccessfulRampResponse($result)) {
            $rawResult = $result;

            Log::warning('Ramp off-ramp bank-account attachment raw provider failure', array_merge($logContext, [
                'provider_status' => $rawResult['status'] ?? null,
                'provider_http_status' => $rawResult['http_status'] ?? null,
                'provider_message' => $rawResult['message'] ?? null,
                'provider_data' => $this->normalizeProviderErrorData($rawResult['data'] ?? null),
            ]));

            if ($this->isNameMismatchRampError($result)) {
                $result['message'] = 'The payout bank account name does not match the verified BitMonie profile for this sell order.';
                $result['data'] = array_merge($this->normalizeProviderErrorData($result['data'] ?? null), [
                    'error_code' => 'off_ramp_name_mismatch',
                    'hint' => 'Use a payout account that matches the verified legal name on the BitMonie profile used for this sell order.',
                ]);
            }

            Log::warning('Ramp off-ramp bank-account attachment failed', array_merge($logContext, [
                'provider_status' => $result['status'] ?? null,
                'provider_http_status' => $result['http_status'] ?? null,
                'provider_message' => $result['message'] ?? null,
                'provider_data' => $this->normalizeProviderErrorData($result['data'] ?? null),
            ]));

            return $result;
        }

        if ($this->isSuccessfulRampResponse($result)) {
            $transaction->update([
                'bank_code' => $payload['bank_code'],
                'account_number' => $payload['account_number'],
                'bank_name' => $displayOverrides['bank_name'] ?? $payload['bank_name'] ?? $transaction->bank_name,
                'account_name' => $displayOverrides['account_name'] ?? $payload['account_name'] ?? $transaction->account_name,
                'metadata' => $this->mergeMetadata($transaction->metadata, [
                    'bank_account_data' => $result['data'] ?? [],
                ]),
            ]);
        }

        Log::info('Ramp off-ramp bank-account attachment succeeded', array_merge($logContext, [
            'provider_status' => $result['status'] ?? null,
            'provider_http_status' => $result['http_status'] ?? null,
        ]));

        return $result;
    }

    protected function mergeMetadata(?array $existing, array $updates): array
    {
        return array_merge($existing ?? [], $updates);
    }

    protected function resolveSafeHavenSubAccount($user)
    {
        return $user->virtualAccounts()
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
    }

    protected function fetchOnRampStatusResponse(RampTransaction $transaction)
    {
        $result = $this->rampService->onRampTransaction($transaction->merchant_reference);

        if ($this->isSuccessfulRampResponse($result)) {
            $providerData = is_array($result['data']) ? $result['data'] : [];
            $transaction = $this->transactionSyncService()->syncOnRamp($transaction, $providerData);

            return Response::successResponse(
                $result['message'] ?? 'On-ramp transaction fetched successfully',
                $this->buildOnRampConfirmPayload($transaction)
            );
        }

        $transaction->refresh();

        return Response::successResponse('On-ramp transaction fetched locally. Provider status unavailable.', array_merge(
            $this->buildOnRampConfirmPayload($transaction),
            [
                'provider_status' => [
                    'available' => false,
                    'status' => $result['status'] ?? 'error',
                    'message' => $result['message'] ?? 'Provider status unavailable',
                    'http_status' => $result['http_status'] ?? null,
                ],
            ]
        ));
    }

    protected function fetchOffRampStatusResponse(RampTransaction $transaction)
    {
        $result = $this->rampService->offRampTransaction($transaction->merchant_reference);

        if ($this->isSuccessfulRampResponse($result)) {
            $providerData = is_array($result['data']) ? $result['data'] : [];
            $transaction = $this->transactionSyncService()->syncOffRamp($transaction, $providerData);

            return Response::successResponse(
                $result['message'] ?? 'Off-ramp transaction fetched successfully',
                $this->buildOffRampConfirmPayload($transaction)
            );
        }

        $transaction->refresh();

        return Response::successResponse('Off-ramp transaction fetched locally. Provider status unavailable.', array_merge(
            $this->buildOffRampConfirmPayload($transaction),
            [
                'provider_status' => [
                    'available' => false,
                    'status' => $result['status'] ?? 'error',
                    'message' => $result['message'] ?? 'Provider status unavailable',
                    'http_status' => $result['http_status'] ?? null,
                ],
            ]
        ));
    }

    protected function transactionSyncService(): RampTransactionSyncService
    {
        return app(RampTransactionSyncService::class);
    }

    protected function buildOnRampConfirmPayload(RampTransaction $transaction): array
    {
        $metadata = $transaction->metadata ?? [];
        $confirmData = is_array($metadata['confirm_data'] ?? null) ? $metadata['confirm_data'] : [];
        $transferData = is_array($metadata['transfer_data'] ?? null) ? $metadata['transfer_data'] : [];
        $providerTransaction = is_array($metadata['provider_transaction'] ?? null) ? $metadata['provider_transaction'] : [];
        $settlement = is_array($metadata['settlement'] ?? null) ? $metadata['settlement'] : [];

        return array_merge($this->formatTransactionSummary($transaction), [
            'payment_account' => [
                'public_id' => $confirmData['public_id'] ?? null,
                'reference' => $confirmData['reference'] ?? null,
                'account_name' => $transaction->account_name,
                'account_number' => $transaction->account_number,
                'bank_name' => $transaction->bank_name,
                'bank_code' => $transaction->bank_code,
                'amount' => $confirmData['amount'] ?? null,
                'amount_expected' => $confirmData['amount_expected'] ?? null,
                'processor_fee' => $confirmData['processor_fee'] ?? $this->formatAmount($transaction->processor_fee),
                'vat' => $confirmData['vat'] ?? $this->formatAmount($transaction->vat),
            ],
            'transfer' => [
                'status' => $transaction->status,
                'session_id' => $transaction->transfer_session_id,
                'details' => $transferData,
            ],
            'settlement' => [
                'fiat_deposit' => $settlement['fiat_deposit'] ?? null,
                'crypto_payout' => $settlement['crypto_payout'] ?? null,
            ],
            'provider_status' => [
                'available' => !empty($providerTransaction),
                'status' => $providerTransaction['status'] ?? null,
                'checked_at' => $metadata['provider_status_checked_at'] ?? null,
            ],
            'wallet' => $metadata['wallet_update'] ?? null,
        ]);
    }

    protected function buildOffRampConfirmPayload(RampTransaction $transaction): array
    {
        $metadata = $transaction->metadata ?? [];
        $confirmData = is_array($metadata['confirm_data'] ?? null) ? $metadata['confirm_data'] : [];
        $settlement = is_array($metadata['settlement'] ?? null) ? $metadata['settlement'] : [];
        $providerTransaction = is_array($metadata['provider_transaction'] ?? null) ? $metadata['provider_transaction'] : [];

        return array_merge($this->formatTransactionSummary($transaction), [
            'payout_account' => $this->formatBankAccountSummary($transaction),
            'deposit_address' => [
                'id' => $confirmData['id'] ?? null,
                'address' => $transaction->wallet_address,
                'tag' => $confirmData['tag'] ?? null,
                'network' => $confirmData['network'] ?? $transaction->network,
                'currency' => strtoupper((string) ($confirmData['currency'] ?? $transaction->from_currency)),
                'assigned_at' => $confirmData['assigned_at'] ?? null,
                'status' => $confirmData['status'] ?? null,
            ],
            'fees' => $metadata['fees'] ?? null,
            'settlement' => [
                'crypto_deposit' => $settlement['crypto_deposit'] ?? null,
                'fiat_payout' => $settlement['fiat_payout'] ?? null,
            ],
            'provider_status' => [
                'available' => !empty($providerTransaction),
                'status' => $providerTransaction['status'] ?? null,
                'checked_at' => $metadata['provider_status_checked_at'] ?? null,
            ],
            'wallet' => $metadata['wallet_update'] ?? null,
        ]);
    }

    protected function formatTransactionSummary(RampTransaction $transaction): array
    {
        $displayType = $transaction->type === 'off_ramp' ? 'sell' : 'buy';

        return [
            'merchant_reference' => $transaction->merchant_reference,
            'public_id' => $transaction->public_id,
            'reference' => $transaction->reference,
            'transaction_id' => $this->transactionProviderIdentifier($transaction),
            'transaction_hash' => $this->transactionHash($transaction),
            'type' => $transaction->type,
            'status' => $displayType === 'sell'
                ? $this->presentSellHistoryStatus((string) $transaction->status)
                : $this->presentBuyHistoryStatus((string) $transaction->status),
            'internal_status' => $transaction->status,
            'provider_status' => $this->transactionProviderStatus($transaction),
            'failure_reason' => $this->transactionFailureReason($transaction),
            'rate' => $this->calculateHistoryRate($transaction, $displayType),
            'created_at' => $transaction->created_at?->format('Y-m-d H:i:s'),
            'date_time' => $transaction->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->updated_at?->format('Y-m-d H:i:s'),
            'trade' => [
                'from_currency' => strtoupper((string) $transaction->from_currency),
                'to_currency' => strtoupper((string) $transaction->to_currency),
                'from_amount' => $this->formatAmount($transaction->from_amount),
                'to_amount' => $this->formatAmount($transaction->to_amount),
                'network' => $transaction->network,
                'wallet_address' => $transaction->wallet_address,
                'blockchain_fee' => $this->formatAmount($transaction->blockchain_fee),
                'processor_fee' => $this->formatAmount($transaction->processor_fee),
                'stamp_charge' => $this->formatAmount($transaction->stamp_charge),
                'vat' => $this->formatAmount($transaction->vat),
            ],
        ];
    }

    protected function formatBankAccountSummary(RampTransaction $transaction): ?array
    {
        if (!$transaction->bank_code && !$transaction->account_number && !$transaction->bank_name && !$transaction->account_name) {
            return null;
        }

        return [
            'bank_name' => $transaction->bank_name,
            'bank_code' => $transaction->bank_code,
            'account_name' => $transaction->account_name,
            'account_number' => $transaction->account_number,
        ];
    }

    protected function formatAmount($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function providerTrackingAttributesFromData(array $data, string $status): array
    {
        $attributes = [];

        if ($this->hasRampTransactionColumn('provider_transaction_id')) {
            $providerTransactionId = $this->firstProviderDataValue($data, [
                'id',
                'uuid',
                'public_id',
                'reference',
                'transaction_id',
                'transaction.id',
                'fiat_deposit.id',
                'crypto_payout.id',
                'crypto_deposit.id',
                'fiat_payout.id',
            ]);

            if ($providerTransactionId !== null && $providerTransactionId !== '') {
                $attributes['provider_transaction_id'] = (string) $providerTransactionId;
            }
        }

        if ($this->hasRampTransactionColumn('transaction_hash')) {
            $transactionHash = $this->firstProviderDataValue($data, [
                'txid',
                'tx_id',
                'hash',
                'transaction_hash',
                'transaction.hash',
                'transaction.txid',
                'crypto_payout.txid',
                'crypto_payout.tx_id',
                'crypto_payout.hash',
                'crypto_payout.transaction_hash',
                'crypto_deposit.txid',
                'crypto_deposit.tx_id',
                'crypto_deposit.hash',
                'crypto_deposit.transaction_hash',
            ]);

            if ($transactionHash !== null && $transactionHash !== '') {
                $attributes['transaction_hash'] = (string) $transactionHash;
            }
        }

        if ($this->hasRampTransactionColumn('provider_status')) {
            $providerStatus = $this->normalizeProviderStatus($this->firstProviderDataValue($data, [
                'status',
                'fiat_deposit.status',
                'crypto_payout.status',
                'crypto_deposit.status',
                'fiat_payout.status',
            ]));

            $attributes['provider_status'] = $providerStatus ?: $status;
        }

        if ($this->hasRampTransactionColumn('provider_status_checked_at')) {
            $attributes['provider_status_checked_at'] = now();
        }

        return $attributes;
    }

    protected function transactionProviderIdentifier(RampTransaction $transaction): ?string
    {
        $metadata = $transaction->metadata ?? [];

        return $transaction->provider_transaction_id
            ?? $this->firstProviderDataValue($metadata, [
                'provider_transaction.id',
                'provider_transaction.uuid',
                'provider_transaction.public_id',
                'provider_transaction.reference',
                'confirm_data.id',
                'confirm_data.public_id',
                'confirm_data.reference',
                'initiate_data.id',
                'initiate_data.public_id',
                'initiate_data.reference',
                'job.ramp_withdrawal.data.id',
                'job.ramp_withdrawal.data.reference',
                'job.main_account_withdrawal.data.id',
                'job.main_account_withdrawal.data.reference',
            ])
            ?? $transaction->public_id
            ?? $transaction->reference
            ?? $transaction->merchant_reference;
    }

    protected function transactionHash(RampTransaction $transaction): ?string
    {
        $metadata = $transaction->metadata ?? [];

        return $transaction->transaction_hash
            ?? $this->firstProviderDataValue($metadata, [
                'provider_transaction.txid',
                'provider_transaction.tx_id',
                'provider_transaction.hash',
                'provider_transaction.transaction_hash',
                'settlement.crypto_payout.txid',
                'settlement.crypto_payout.tx_id',
                'settlement.crypto_payout.hash',
                'settlement.crypto_payout.transaction_hash',
                'settlement.crypto_deposit.txid',
                'settlement.crypto_deposit.tx_id',
                'settlement.crypto_deposit.hash',
                'settlement.crypto_deposit.transaction_hash',
                'job.ramp_withdrawal.data.txid',
                'job.ramp_withdrawal.data.tx_id',
                'job.ramp_withdrawal.data.hash',
                'job.ramp_withdrawal.data.transaction_hash',
                'job.main_account_withdrawal.data.txid',
                'job.main_account_withdrawal.data.tx_id',
                'job.main_account_withdrawal.data.hash',
                'job.main_account_withdrawal.data.transaction_hash',
            ]);
    }

    protected function transactionProviderStatus(RampTransaction $transaction): ?string
    {
        $metadata = $transaction->metadata ?? [];

        return $transaction->provider_status
            ?? $this->normalizeProviderStatus($this->firstProviderDataValue($metadata, [
                'provider_transaction.status',
                'settlement.fiat_deposit.status',
                'settlement.crypto_payout.status',
                'settlement.crypto_deposit.status',
                'settlement.fiat_payout.status',
                'confirm_data.status',
                'initiate_data.status',
            ]));
    }

    protected function transactionFailureReason(RampTransaction $transaction): ?string
    {
        $metadata = $transaction->metadata ?? [];

        return $this->firstProviderDataValue($metadata, [
            'failure_reason',
            'provider_transaction.failure_reason',
            'provider_transaction.reason',
            'provider_transaction.message',
            'settlement.fiat_payout.failure_reason',
            'settlement.fiat_payout.reason',
            'settlement.crypto_payout.failure_reason',
            'settlement.crypto_payout.reason',
            'job.last_ramp_withdrawal_error.message',
            'job.automatic_reversal.message',
        ]);
    }

    protected function calculateHistoryRate(RampTransaction $transaction, string $type): ?string
    {
        $fromAmount = $this->decimalAmount($transaction->from_amount);
        $toAmount = $this->decimalAmount($transaction->to_amount);

        if ($this->compareDecimalAmounts($fromAmount, '0') <= 0 || $this->compareDecimalAmounts($toAmount, '0') <= 0) {
            return null;
        }

        return $type === 'sell'
            ? $this->formatDecimalAmount($this->bcDivAmount($toAmount, $fromAmount))
            : $this->formatDecimalAmount($this->bcDivAmount($fromAmount, $toAmount));
    }

    protected function firstProviderDataValue(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : $value;
            }
        }

        return null;
    }

    protected function normalizeProviderStatus($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $status = strtolower(str_replace([' ', '-', '.'], '_', trim((string) $value)));

        return $status !== '' ? $status : null;
    }

    protected function hasRampTransactionColumn(string $column): bool
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::hasTable('ramp_transactions')
                ? Schema::getColumnListing('ramp_transactions')
                : [];
        }

        return in_array($column, $columns, true);
    }

    protected function normalizeQuidaxWalletPayload(array $wallet): array
    {
        while (count($wallet) === 1) {
            $wrappedKey = array_key_first($wallet);

            if ((is_int($wrappedKey) || (is_string($wrappedKey) && ctype_digit($wrappedKey))) && is_array($wallet[$wrappedKey])) {
                $wallet = $wallet[$wrappedKey];
                continue;
            }

            break;
        }

        return $wallet;
    }

    protected function calculateSellFee($sourceAmount, $fee, ?string $feeType = 'flat'): string
    {
        $feeAmount = $this->decimalAmount($fee);

        if (strtolower((string) $feeType) !== 'flat') {
            return $this->bcDivAmount($this->bcMulAmount($sourceAmount, $feeAmount), '100');
        }

        return $feeAmount;
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

    protected function bcAddAmount($left, $right): string
    {
        return bcadd($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function bcSubAmount($left, $right): string
    {
        return bcsub($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function bcMulAmount($left, $right): string
    {
        return bcmul($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function bcDivAmount($left, $right): string
    {
        if ($this->compareDecimalAmounts($right, '0') === 0) {
            return '0';
        }

        return bcdiv($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function compareDecimalAmounts($left, $right): int
    {
        return bccomp($this->decimalAmount($left), $this->decimalAmount($right), 8);
    }

    protected function formatDecimalAmount($value, int $scale = 8): string
    {
        $formatted = bcadd($this->decimalAmount($value), '0', $scale);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    protected function extractSafeHavenSessionId(array $response): ?string
    {
        return $response['sessionId']
            ?? $response['sessionID']
            ?? $response['data']['sessionId']
            ?? $response['data']['sessionID']
            ?? null;
    }

    protected function syncOnRampWalletBalance($user, string $currencyCode, string $amount, string $merchantReference, array $transferData = []): array
    {
        $wallet = $this->findUserWallet($user, $currencyCode);
        $previousBalance = $wallet ? (string) $wallet->balance : null;
        $syncedBalance = $this->safehavenService->syncUserWalletBalance($user, $currencyCode);

        if ($syncedBalance !== null && ($previousBalance === null || bccomp($syncedBalance, $previousBalance, 8) !== 0)) {
            return [
                'currency' => $currencyCode,
                'debited_amount' => $amount,
                'balance_after' => $this->formatAmount($syncedBalance),
                'sync_method' => 'safehaven_account',
            ];
        }

        if (!$wallet) {
            Log::warning('On-ramp wallet sync skipped because the local wallet could not be found.', [
                'user_id' => $user->id,
                'merchant_reference' => $merchantReference,
                'currency' => $currencyCode,
            ]);

            return [
                'currency' => $currencyCode,
                'debited_amount' => $amount,
                'balance_after' => null,
                'sync_method' => 'wallet_not_found',
            ];
        }

        if (bccomp((string) $wallet->balance, $amount, 8) < 0) {
            Log::warning('On-ramp wallet sync skipped because the local wallet balance is lower than the provider debit amount.', [
                'user_id' => $user->id,
                'merchant_reference' => $merchantReference,
                'currency' => $currencyCode,
                'wallet_balance' => (string) $wallet->balance,
                'debit_amount' => $amount,
            ]);

            return [
                'currency' => $currencyCode,
                'debited_amount' => $amount,
                'balance_after' => $this->formatAmount($wallet->balance),
                'sync_method' => 'insufficient_local_wallet',
            ];
        }

        $updatedWallet = WalletService::debit($wallet->id, $amount, $merchantReference . ':on_ramp', [
            'type' => 'ramp_on_ramp_purchase',
            'merchant_reference' => $merchantReference,
            'provider' => 'quidax_ramp',
            'payment_reference' => $transferData['paymentReference'] ?? null,
        ]);

        return [
            'currency' => $currencyCode,
            'debited_amount' => $amount,
            'balance_after' => $this->formatAmount($updatedWallet->balance),
            'sync_method' => 'local_wallet_debit',
        ];
    }

    protected function findUserWallet($user, string $currencyCode): ?UserWallet
    {
        $normalizedCurrency = strtoupper($currencyCode);

        return $user->wallets()
            ->where(function ($query) use ($normalizedCurrency) {
                $query->where('currency_code', $normalizedCurrency)
                    ->orWhereHas('currency', function ($currencyQuery) use ($normalizedCurrency) {
                        $currencyQuery->where('code', $normalizedCurrency);
                    });
            })
            ->first();
    }

    // =========================================================================
    // HISTORY ENDPOINTS
    // =========================================================================

    /**
     * GET /api/user/buy-sell/buy/history
     * Returns paginated on-ramp (buy) transaction history for the authenticated user.
     */
    public function buyHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = min((int) $request->get('per_page', 15), 50);

            $transactions = RampTransaction::where('user_id', $user->id)
                ->where('type', 'on_ramp')
                ->latest()
                ->paginate($perPage);

            $formatted = $transactions->getCollection()->map(function ($trx) {
                $rate = $this->calculateHistoryRate($trx, 'buy');
                $providerStatus = $this->transactionProviderStatus($trx);

                return [
                    'id'                 => $trx->id,
                    'type'               => 'buy',
                    'transaction_id'     => $this->transactionProviderIdentifier($trx),
                    'transaction_hash'   => $this->transactionHash($trx),
                    'fiat_currency'      => strtoupper((string) $trx->from_currency),
                    'crypto_currency'    => strtoupper((string) $trx->to_currency),
                    'fiat_amount'        => $this->formatAmount($trx->from_amount),
                    'crypto_amount'      => $this->formatAmount($trx->to_amount),
                    'rate'               => $rate,
                    'network'            => $trx->network,
                    'wallet_address'     => $trx->wallet_address,
                    'status'             => $this->presentBuyHistoryStatus((string) $trx->status),
                    'internal_status'    => (string) $trx->status,
                    'provider_status'    => $providerStatus,
                    'failure_reason'     => $this->transactionFailureReason($trx),
                    'merchant_reference' => $trx->merchant_reference,
                    'created_at'         => $trx->created_at?->format('Y-m-d H:i:s'),
                    'date_time'          => $trx->created_at?->format('Y-m-d H:i:s'),
                    'details_endpoint'   => "/api/user/buy-sell/buy/{$trx->merchant_reference}/details",
                ];
            });

            return Response::successResponse('Buy history fetched successfully', [
                'transactions' => $formatted,
                'pagination'   => [
                    'current_page' => $transactions->currentPage(),
                    'last_page'    => $transactions->lastPage(),
                    'per_page'     => $transactions->perPage(),
                    'total'        => $transactions->total(),
                ],
            ]);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    /**
     * GET /api/user/buy-sell/sell/history
     * Returns paginated off-ramp (sell) transaction history for the authenticated user.
     */
    public function sellHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = min((int) $request->get('per_page', 15), 50);

            $transactions = RampTransaction::where('user_id', $user->id)
                ->where('type', 'off_ramp')
                ->latest()
                ->paginate($perPage);

            $settlementMatches = $this->matchSettledSellTransactions($transactions->getCollection());

            $formatted = $transactions->getCollection()->map(function ($trx) use ($settlementMatches) {
                $internalStatus = (string) $trx->status;
                $rate = $this->calculateHistoryRate($trx, 'sell');
                $providerStatus = $this->transactionProviderStatus($trx);

                return [
                    'id'                 => $trx->id,
                    'type'               => 'sell',
                    'transaction_id'     => $this->transactionProviderIdentifier($trx),
                    'transaction_hash'   => $this->transactionHash($trx),
                    'fiat_currency'      => strtoupper((string) $trx->to_currency),
                    'crypto_currency'    => strtoupper((string) $trx->from_currency),
                    'fiat_amount'        => $this->formatAmount($trx->to_amount),
                    'crypto_amount'      => $this->formatAmount($trx->from_amount),
                    'rate'               => $rate,
                    'network'            => $trx->network,
                    'bank_account'       => [
                        'bank_name'      => $trx->bank_name,
                        'account_name'   => $trx->account_name,
                        'account_number' => $trx->account_number,
                    ],
                    'status'             => $this->presentSellHistoryStatus($internalStatus, array_key_exists($trx->id, $settlementMatches)),
                    'internal_status'    => $internalStatus,
                    'provider_status'    => $providerStatus,
                    'failure_reason'     => $this->transactionFailureReason($trx),
                    'merchant_reference' => $trx->merchant_reference,
                    'created_at'         => $trx->created_at?->format('Y-m-d H:i:s'),
                    'date_time'          => $trx->created_at?->format('Y-m-d H:i:s'),
                    'details_endpoint'   => "/api/user/buy-sell/sell/{$trx->merchant_reference}/details",
                ];
            });

            return Response::successResponse('Sell history fetched successfully', [
                'transactions' => $formatted,
                'pagination'   => [
                    'current_page' => $transactions->currentPage(),
                    'last_page'    => $transactions->lastPage(),
                    'per_page'     => $transactions->perPage(),
                    'total'        => $transactions->total(),
                ],
            ]);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function buyDetails(string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'on_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            return $this->fetchOnRampStatusResponse($transaction);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    public function sellDetails(string $merchantReference)
    {
        try {
            $user = auth()->user();
            $transaction = $this->findUserTransaction($user->id, $merchantReference, 'off_ramp');

            if (!$transaction) {
                return Response::errorResponse('Transaction not found locally.', null, 404);
            }

            return $this->fetchOffRampStatusResponse($transaction);
        } catch (Exception $e) {
            return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
        }
    }

    protected function matchSettledSellTransactions($transactions): array
    {
        $candidates = $transactions->filter(function ($transaction) {
            return $transaction instanceof RampTransaction
                && $this->isSellStatusAwaitingSettlement((string) $transaction->status)
                && !empty($transaction->account_number)
                && !empty($transaction->created_at);
        });

        if ($candidates->isEmpty()) {
            return [];
        }

        $accountNumbers = $candidates->pluck('account_number')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $windowStart = $candidates->min('created_at')?->copy()->subHour();
        $windowEnd = $candidates->max('created_at')?->copy()->addDay();

        if (!$windowStart || !$windowEnd || empty($accountNumbers)) {
            return [];
        }

        $events = SafeHavenWebhookEvent::query()
            ->where('status', 'processed')
            ->whereIn('account_number', $accountNumbers)
            ->whereNotNull('processed_at')
            ->whereBetween('processed_at', [$windowStart, $windowEnd])
            ->orderBy('processed_at')
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        $matches = [];
        $usedEventIds = [];

        foreach ($candidates->sortBy('created_at') as $transaction) {
            $event = $events->first(function (SafeHavenWebhookEvent $event) use ($transaction, $usedEventIds) {
                if (in_array($event->id, $usedEventIds, true)) {
                    return false;
                }

                if (!empty($event->user_id) && (int) $event->user_id !== (int) $transaction->user_id) {
                    return false;
                }

                if ((string) $event->account_number !== (string) $transaction->account_number) {
                    return false;
                }

                if (!$event->processed_at || $event->processed_at->lt($transaction->created_at)) {
                    return false;
                }

                if ($event->processed_at->gt($transaction->created_at->copy()->addDay())) {
                    return false;
                }

                return $this->safeHavenEventMatchesSellAmount($transaction, $event);
            });

            if ($event) {
                $matches[$transaction->id] = $event->id;
                $usedEventIds[] = $event->id;
            }
        }

        return $matches;
    }

    protected function presentSellHistoryStatus(string $status, bool $hasProcessedSettlement = false): string
    {
        $normalizedStatus = strtolower(trim($status));

        if (in_array($normalizedStatus, ['completed', 'success', 'successful'], true) || $hasProcessedSettlement) {
            return 'successful';
        }

        if (in_array($normalizedStatus, ['awaiting_payout', 'processing', 'confirmed'], true)) {
            return 'processing';
        }

        if (in_array($normalizedStatus, ['failed', 'error', 'cancelled', 'rejected'], true)) {
            return 'failed';
        }

        return $normalizedStatus !== '' ? $normalizedStatus : 'pending';
    }

    protected function presentBuyHistoryStatus(string $status): string
    {
        $normalizedStatus = strtolower(trim($status));

        if (in_array($normalizedStatus, ['completed', 'success', 'successful'], true)) {
            return 'successful';
        }

        if (in_array($normalizedStatus, ['failed', 'error', 'cancelled', 'rejected'], true)) {
            return 'failed';
        }

        if (in_array($normalizedStatus, ['confirmed', 'processing', 'pending'], true)) {
            return 'processing';
        }

        return $normalizedStatus !== '' ? $normalizedStatus : 'pending';
    }

    protected function isSellStatusAwaitingSettlement(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['awaiting_payout', 'processing', 'confirmed'], true);
    }

    protected function safeHavenEventMatchesSellAmount(RampTransaction $transaction, SafeHavenWebhookEvent $event): bool
    {
        if (!is_numeric($transaction->to_amount) || !is_numeric($event->credit_amount)) {
            return true;
        }

        $expectedAmount = (float) $transaction->to_amount;
        $creditedAmount = (float) $event->credit_amount;
        $tolerance = max(500.0, min(5000.0, $expectedAmount * 0.15));

        return abs($expectedAmount - $creditedAmount) <= $tolerance;
    }

    public function getOffRampBanks(Request $request)
{
    try {
        $banks = $this->rampService->getOffRampBanks();

        if (
            !is_array($banks) ||
            !($banks['ok'] ?? false) ||
            !isset($banks['data']) ||
            !is_array($banks['data'])
        ) {
            return Response::errorResponse(
                $banks['message'] ?? 'Unable to fetch off-ramp banks.'
            );
        }

        // Look up Safe Haven MFB (case-insensitive)
        $safeHaven = collect($banks['data'])->first(function ($bank) {
            $name = strtolower(trim($bank['name'] ?? ''));

            return str_contains($name, 'safe haven');
        });

        if (!$safeHaven) {
            return Response::errorResponse('Safe Haven Microfinance Bank is not supported for off-ramp payouts.');
        }

        return Response::successResponse('Safe Haven Microfinance Bank found.', [
            'bank' => $safeHaven,
        ]);
    } catch (\Exception $e) {
        \Log::error('Failed to fetch off-ramp banks.', [
            'message' => $e->getMessage(),
        ]);

        return Response::errorResponse(
    is_string($e->getMessage())
        ? $e->getMessage()
        : 'An unexpected error occurred.',
    null,
    500
);
    }
}
}
