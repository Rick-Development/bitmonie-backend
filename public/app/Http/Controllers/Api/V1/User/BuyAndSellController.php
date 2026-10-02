<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Jobs\ProcessRampSell;
use App\Models\Bank;
use App\Models\KycVerification;
use App\Models\RampTransaction;
use App\Models\UserWallet;
use App\Notifications\RampBuyNotification;
use App\Notifications\RampSellNotification;
use App\Services\QuidaxRampService;
use App\Services\QuidaxService;
use App\Services\RampTransactionSyncService;
use App\Services\SafeHavenService;
use App\Services\WalletService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BuyAndSellController extends Controller
{
    protected QuidaxRampService $rampService;
    protected QuidaxService $quidaxService;
    protected SafeHavenService $safehavenService;

    public function __construct(
        QuidaxRampService $rampService,
        QuidaxService $quidaxService,
        SafeHavenService $safehavenService
    ) {
        $this->rampService = $rampService;
        $this->quidaxService = $quidaxService;
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
            return Response::errorResponse($e->getMessage());
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
            return Response::errorResponse($e->getMessage());
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

            $transaction = RampTransaction::create([
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
            ]);

            return Response::successResponse($result['message'] ?? 'On-ramp transaction initiated successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
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

            $transaction->update([
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'blockchain_fee' => $data['blockchain_fee'] ?? null,
                'stamp_charge' => $data['stamp_charge'] ?? $transaction->stamp_charge,
                'metadata' => $this->mergeMetadata($transaction->metadata, ['refresh_data' => $data]),
            ]);

            $transaction->refresh();

            return Response::successResponse($result['message'] ?? 'On-ramp transaction refreshed successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
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
            return Response::errorResponse($e->getMessage());
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

            $transaction->update([
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
            ]);

            $transaction->refresh();
            $user->notify(new RampBuyNotification($transaction));

            return Response::successResponse($result['message'] ?? 'On-ramp transaction confirmed successfully', $this->buildOnRampConfirmPayload($transaction));
        } catch (Exception $e) {
            if (isset($transaction) && $transaction instanceof RampTransaction && $transaction->status === 'processing') {
                $transaction->update(['status' => 'pending']);
            }

            return Response::errorResponse($e->getMessage());
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

            $transaction = RampTransaction::create([
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
            ]);

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
            return Response::errorResponse($e->getMessage());
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

            $transaction->update([
                'from_amount' => $data['from_amount'] ?? $request->from_amount,
                'to_amount' => $data['to_amount'] ?? null,
                'network' => strtolower($request->network),
                'metadata' => $this->mergeMetadata($transaction->metadata, ['refresh_data' => $data]),
            ]);

            $transaction->refresh();

            return Response::successResponse($result['message'] ?? 'Off-ramp transaction refreshed successfully', array_merge(
                $this->formatTransactionSummary($transaction),
                ['quote' => $data]
            ));
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
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
            return Response::errorResponse($e->getMessage());
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
            return Response::errorResponse($e->getMessage());
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

            if (in_array($transaction->status, ['processing', 'awaiting_payout', 'completed'], true)) {
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

            $result = $this->rampService->confirmOffRamp($merchantReference);

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

            $sourceCurrency = strtolower((string) $transaction->from_currency);
            $sourceAmount = (float) $transaction->from_amount;
            $network = strtolower((string) ($data['network'] ?? $transaction->network));
            $reference = $transaction->merchant_reference;

            if (!$user->quidax_id) {
                throw new Exception('User does not have a linked crypto trading account.');
            }

            $quidaxWalletResponse = $this->quidaxService->fetchUserWallet($user->quidax_id, $sourceCurrency);
            if (($quidaxWalletResponse['status'] ?? '') !== 'success') {
                throw new Exception($quidaxWalletResponse['message'] ?? "Could not fetch crypto wallet for {$sourceCurrency}.");
            }

            $feeResponse = $this->quidaxService->getWithdrawalFee($sourceCurrency, $network);
            if (($feeResponse['status'] ?? '') !== 'success' && !isset($feeResponse['data']['fee'])) {
                throw new Exception($feeResponse['message'] ?? 'Could not determine withdrawal fee.');
            }

            $quidaxFee = (float) ($feeResponse['data']['fee'] ?? 0);
            $feeType = $feeResponse['data']['type'] ?? 'flat';

            if ($feeType !== 'flat') {
                $quidaxFee = $sourceAmount * ($quidaxFee / 100);
            }

            $platformFee = $quidaxFee + ($quidaxFee * 0.25);
            $totalAmountToWithdrawFromUser = $sourceAmount + $platformFee;
            $quidaxBalance = (float) ($quidaxWalletResponse['data']['balance'] ?? 0);

            if ($quidaxBalance < $totalAmountToWithdrawFromUser) {
                throw new Exception("Insufficient balance. Required: {$totalAmountToWithdrawFromUser} {$sourceCurrency} (including {$platformFee} fee) but available: {$quidaxBalance} {$sourceCurrency}");
            }

            $mainAccountResponse = $this->quidaxService->getUser();
            $mainAccountId = $mainAccountResponse['data']['id'] ?? 'me';

            if (!$mainAccountId) {
                throw new Exception('Could not determine the Quidax main account identifier.');
            }

            DB::beginTransaction();

            $transaction->update([
                'status' => 'processing',
                'wallet_address' => $rampAddress,
                'network' => $network,
                'blockchain_fee' => $quidaxFee,
                'processor_fee' => $platformFee - $quidaxFee,
                'metadata' => $this->mergeMetadata($transaction->metadata, [
                    'confirm_data' => $data,
                    'fees' => [
                        'quidax_fee' => $quidaxFee,
                        'platform_fee' => $platformFee,
                        'total' => $totalAmountToWithdrawFromUser,
                    ],
                ]),
            ]);

            $mainAccountWithdrawalData = [
                'currency' => $sourceCurrency,
                'network' => $network,
                'amount' => $totalAmountToWithdrawFromUser,
                'fund_uid' => $mainAccountId,
                'transaction_note' => "Off-ramp move to main account: {$reference}",
                'narration' => "Off-ramp move to main account: {$reference}",
            ];

            ProcessRampSell::dispatchAfterResponse(
                $user,
                $reference,
                $mainAccountWithdrawalData,
                $sourceCurrency,
                $sourceAmount,
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

            return Response::errorResponse($e->getMessage());
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
        return [
            'merchant_reference' => $transaction->merchant_reference,
            'public_id' => $transaction->public_id,
            'reference' => $transaction->reference,
            'type' => $transaction->type,
            'status' => $transaction->status,
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
                return [
                    'id'                 => $trx->id,
                    'type'               => 'buy',
                    'fiat_currency'      => strtoupper((string) $trx->from_currency),
                    'crypto_currency'    => strtoupper((string) $trx->to_currency),
                    'fiat_amount'        => (float) $trx->from_amount,
                    'crypto_amount'      => $trx->to_amount !== null ? (float) $trx->to_amount : null,
                    'rate'               => ($trx->from_amount > 0 && $trx->to_amount > 0)
                                            ? round((float) $trx->from_amount / (float) $trx->to_amount, 2)
                                            : null,
                    'network'            => $trx->network,
                    'wallet_address'     => $trx->wallet_address,
                    'status'             => $trx->status,
                    'merchant_reference' => $trx->merchant_reference,
                    'created_at'         => $trx->created_at?->format('Y-m-d H:i:s'),
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
            return Response::errorResponse($e->getMessage());
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

            $formatted = $transactions->getCollection()->map(function ($trx) {
                return [
                    'id'                 => $trx->id,
                    'type'               => 'sell',
                    'fiat_currency'      => strtoupper((string) $trx->to_currency),
                    'crypto_currency'    => strtoupper((string) $trx->from_currency),
                    'fiat_amount'        => $trx->to_amount !== null ? (float) $trx->to_amount : null,
                    'crypto_amount'      => (float) $trx->from_amount,
                    'rate'               => ($trx->from_amount > 0 && $trx->to_amount > 0)
                                            ? round((float) $trx->to_amount / (float) $trx->from_amount, 2)
                                            : null,
                    'network'            => $trx->network,
                    'bank_account'       => [
                        'bank_name'      => $trx->bank_name,
                        'account_name'   => $trx->account_name,
                        'account_number' => $trx->account_number,
                    ],
                    'status'             => $trx->status,
                    'merchant_reference' => $trx->merchant_reference,
                    'created_at'         => $trx->created_at?->format('Y-m-d H:i:s'),
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
            return Response::errorResponse($e->getMessage());
        }
    }
    public function getOffRampBanks(Request $request)
    {
        try {
            $result = '';
            //$this->rampService->getOffRampBanks();

            if (!$this->isSuccessfulRampResponse($result)) {
                return Response::errorResponse($result['message'] ?? 'Failed to fetch off-ramp banks', $result['data'] ?? []);
            }

            $banks = is_array($result['data']) ? $result['data'] : [];

            return Response::successResponse('Off-ramp banks fetched successfully', [
                'banks' => $banks,
            ]);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }
}
