<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\GraphCustomer;
use App\Models\GraphWallet;
use App\Services\GraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Traits\PinValidationTrait;

class GraphController extends Controller
{
    use PinValidationTrait;
    protected GraphService $graphService;

    public function __construct(GraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    public function createCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dob' => 'required|date',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip_code' => 'required|string',
            'id_number' => 'required|string',
            'id_type' => 'required|string|in:passport,drivers_license,national_id,voters_card,nin',
            'id_image' => 'required|file|mimes:jpeg,png,jpg,pdf',
            'bank_statement' => 'required|file|mimes:jpeg,png,jpg,pdf',
            'bvn' => 'required|string',
            'background_information' => 'required|array',
            'background_information.employment_status' => 'required|string|in:employed,self_employed,unemployed,student,retired',
            'background_information.occupation' => 'required|string',
            'background_information.primary_purpose' => 'required|string|in:business,personal,salary,freelance',
            'background_information.source_of_funds' => 'required|string|in:salary,savings,business,freelance,investment,government_benefits,pension',
            'background_information.expected_monthly_inflow' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->all());
        }

        try {
            $user = auth()->user();

            if (GraphCustomer::where('user_id', $user->id)->exists()) {
                return Response::errorResponse('Customer already exists');
            }

            $validatedData = $validator->validated();

            if ($request->hasFile('id_image')) {
                $file = $request->file('id_image');
                $filename = time() . '_id_' . $file->getClientOriginalName();
                $file->storeAs('documents', $filename, 'public');
                $validatedData['id_image_url'] = 'https://res.cloudinary.com/rapidpay-africa/image/upload/v1682887473/sandbox/DRIVERS_LICENSE_dpf491.png';
            } else {
                $validatedData['id_image_url'] = null;
            }

            if ($request->hasFile('bank_statement')) {
                $file = $request->file('bank_statement');
                $filename = time() . '_stmt_' . $file->getClientOriginalName();
                $file->storeAs('documents', $filename, 'public');
                $validatedData['bank_statement_url'] = 'https://res.cloudinary.com/rapidpay-africa/image/upload/v1742226747/dummy_q5dttk.pdf';
            } else {
                $validatedData['bank_statement_url'] = null;
            }

            $customer = $this->graphService->createPerson($user, $validatedData);

            return Response::successResponse('Customer created successfully', ['customer' => $customer]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function createWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|in:USD,EUR,GBP',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $currency = strtoupper((string) $request->currency);
            $existing = GraphWallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->first();

            if ($existing) {
                $this->graphService->updateWalletBalance($existing->wallet_id);

                return Response::successResponse('Wallet already exists', ['wallet' => $existing->fresh()]);
            }

            $wallet = $this->graphService->createWallet($user, $currency);

            return Response::successResponse('Wallet created successfully', ['wallet' => $wallet]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not a registered Graph customer')) {
                return Response::errorResponse('Please create a Graph profile/customer first.');
            }

            return Response::errorResponse($e->getMessage());
        }
    }

    public function getWallet()
    {
        try {
            $wallets = GraphWallet::where('user_id', auth()->id())->get();

            return Response::successResponse('Wallets fetched successfully', ['wallets' => $wallets]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $transactions = $this->graphService->getTransactions($request->wallet_id);

            return Response::successResponse('Transactions fetched successfully', ['transactions' => $transactions]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function createDepositAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
            'currency' => 'required|in:USDT,USDC',
            'network' => 'required|in:ERC20,TRC20,POL',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $address = $this->graphService->createDepositAddress($user, $request->wallet_id, $request->currency, $request->network);

            return Response::successResponse('Deposit address created successfully', ['address' => $address['data'] ?? $address]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getDeposits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $deposits = $this->graphService->getDeposits($request->wallet_id);

            return Response::successResponse('Deposits fetched successfully', ['deposits' => $deposits]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function mockDeposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:USD,EUR,GBP',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $deposit = $this->graphService->mockDeposit($request->wallet_id, $request->amount, $request->currency);
            $this->graphService->updateWalletBalance($request->wallet_id);

            return Response::successResponse('Deposit simulated successfully', ['deposit' => $deposit['data'] ?? $deposit]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function listBanks(Request $request)
    {
        try {
            $country = strtoupper((string) $request->get('country', 'NG'));
            $banks = $this->graphService->listBanks($country);

            return Response::successResponse('Banks fetched successfully', ['banks' => $banks['data'] ?? $banks]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function verifyBankAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_code' => 'required|string',
            'account_number' => 'required|string|size:10',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $accountDetails = $this->graphService->resolveBankAccount($request->bank_code, $request->account_number);

            return Response::successResponse('Account verified successfully', ['account' => $accountDetails['data'] ?? $accountDetails]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function createPayoutDestination(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
            'type' => 'required|in:ach,wire,nip,stablecoin,internal',
            'currency' => 'nullable|string|max:10',
            'label' => 'nullable|string|max:100',
            'source_type' => 'nullable|in:wallet_account,bank_account',
            'details' => 'required|array',
        ]);

        $validator->after(function ($validator) use ($request) {
            $details = (array) $request->input('details', []);
            $type = strtolower((string) $request->input('type'));

            if ($type === 'nip') {
                if (empty($details['bank_code'])) {
                    $validator->errors()->add('details.bank_code', 'The bank code field is required for NIP payouts.');
                }
                if (empty($details['account_number'])) {
                    $validator->errors()->add('details.account_number', 'The account number field is required for NIP payouts.');
                }
                if (empty($details['beneficiary_name']) && empty($details['account_name'])) {
                    $validator->errors()->add('details.beneficiary_name', 'The beneficiary name field is required for NIP payouts.');
                }
            }

            if (in_array($type, ['ach', 'wire'], true)) {
                if (empty($details['account_number'])) {
                    $validator->errors()->add('details.account_number', 'The account number field is required for USD payouts.');
                }
                if (empty($details['beneficiary_name']) && empty($details['account_name'])) {
                    $validator->errors()->add('details.beneficiary_name', 'The beneficiary name field is required for USD payouts.');
                }
                if (empty($details['routing_number']) && empty($details['swift_code'])) {
                    $validator->errors()->add('details.routing_number', 'The routing number or swift code field is required for USD payouts.');
                }
            }

            if ($type === 'stablecoin') {
                if (empty($details['address_code']) && empty($details['address'])) {
                    $validator->errors()->add('details.address_code', 'The wallet address field is required for stablecoin payouts.');
                }
                if (empty($details['address_network']) && empty($details['network'])) {
                    $validator->errors()->add('details.address_network', 'The network field is required for stablecoin payouts.');
                }
            }

            if ($type === 'internal' && empty($details['destination_account_id']) && empty($details['account_id'])) {
                $validator->errors()->add('details.destination_account_id', 'The destination account ID field is required for internal payouts.');
            }
        });

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $payload = $validator->validated();
            $payload['currency'] = $payload['currency'] ?? $wallet->currency;
            $destination = $this->graphService->createPayoutDestination($user, $payload);

            return Response::successResponse('Payout destination created successfully', ['destination' => $destination['data'] ?? $destination]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function listPayoutDestinations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'nullable|string',
            'type' => 'nullable|in:ach,wire,nip,stablecoin,internal',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $filters = $validator->validated();

            if (!empty($filters['wallet_id'])) {
                $wallet = GraphWallet::where('wallet_id', $filters['wallet_id'])
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$wallet) {
                    return Response::errorResponse('Wallet not found');
                }
            }

            $destinations = $this->graphService->listPayoutDestinations($filters);

            return Response::successResponse('Payout destinations fetched successfully', ['destinations' => $destinations['data'] ?? $destinations]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function withdrawUSD(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
            'destination_id' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'pin' => 'required|digits:4',
            'description' => 'nullable|string|max:100',
            'narration' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();

            if (!$this->validateTransactionPin($user, $request->pin)) {
                return Response::errorResponse('Invalid Transaction PIN', [], 403);
            }

            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', $user->id)
                ->where('currency', 'USD')
                ->first();

            if (!$wallet) {
                return Response::errorResponse('USD wallet not found');
            }

            $this->graphService->updateWalletBalance($request->wallet_id);
            $wallet->refresh();

            if ((float) $wallet->balance < (float) $request->amount) {
                return Response::errorResponse('Insufficient USD balance', [
                    'available_balance' => (float) $wallet->balance,
                ], 422);
            }

            $data = [
                'destination_id' => $request->destination_id,
                'amount' => (float) $request->amount,
                'currency' => 'USD',
                'reference' => 'WD_USD_' . time() . '_' . $user->id,
                'description' => $request->description ?? $request->narration ?? 'USD Withdrawal',
            ];

            $payout = $this->graphService->createPayout($user, $request->wallet_id, $data);
            $this->graphService->updateWalletBalance($request->wallet_id);

            return Response::successResponse('USD withdrawal initiated successfully', ['payout' => $payout['data'] ?? $payout]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function convertAndWithdrawNGN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
            'destination_id' => 'required|string',
            'usd_amount' => 'required|numeric|min:1',
            'pin' => 'required|digits:4',
            'description' => 'nullable|string|max:100',
            'narration' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $user = auth()->user();

            if (!$this->validateTransactionPin($user, $request->pin)) {
                return Response::errorResponse('Invalid Transaction PIN', [], 403);
            }

            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', $user->id)
                ->where('currency', 'USD')
                ->first();

            if (!$wallet) {
                return Response::errorResponse('USD wallet not found');
            }

            $this->graphService->updateWalletBalance($request->wallet_id);
            $wallet->refresh();

            if ((float) $wallet->balance < (float) $request->usd_amount) {
                return Response::errorResponse('Insufficient USD balance', [
                    'available_balance' => (float) $wallet->balance,
                ], 422);
            }

            $conversion = $this->graphService->convertCurrency($user, $request->wallet_id, [
                'from_currency' => 'USD',
                'to_currency' => 'NGN',
                'amount' => (float) $request->usd_amount,
            ]);

            $convertedAmount = $this->extractConvertedAmount($conversion);

            if ($convertedAmount <= 0) {
                return Response::errorResponse('Unable to determine converted NGN amount from Graph response.');
            }

            $payout = $this->graphService->createPayout($user, $request->wallet_id, [
                'destination_id' => $request->destination_id,
                'amount' => $convertedAmount,
                'currency' => 'NGN',
                'reference' => 'WD_NGN_' . time() . '_' . $user->id,
                'description' => $request->description ?? $request->narration ?? 'NGN Withdrawal (converted from USD)',
            ]);

            $this->graphService->updateWalletBalance($request->wallet_id);

            return Response::successResponse('Conversion and withdrawal successful', [
                'conversion' => $conversion['data'] ?? $conversion,
                'payout' => $payout['data'] ?? $payout,
            ]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getWithdrawals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $withdrawals = $this->graphService->getPayouts($request->wallet_id);

            return Response::successResponse('Withdrawals fetched successfully', ['withdrawals' => $withdrawals]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function refreshBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $wallet = GraphWallet::where('wallet_id', $request->wallet_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$wallet) {
                return Response::errorResponse('Wallet not found');
            }

            $updatedWallet = $this->graphService->updateWalletBalance($request->wallet_id);

            return Response::successResponse('Balance refreshed successfully', ['wallet' => $updatedWallet]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function getExchangeRate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|in:USD,NGN,EUR,GBP',
            'to' => 'required|in:USD,NGN,EUR,GBP',
        ]);

        if ($validator->fails()) {
            return Response::error($validator->errors()->all());
        }

        try {
            $rate = $this->graphService->getExchangeRate($request->from, $request->to);

            return Response::successResponse('Exchange rate fetched successfully', ['rate' => $rate['data'] ?? $rate]);
        } catch (\Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    protected function extractConvertedAmount(array $conversion): float
    {
        $data = $conversion['data'] ?? $conversion;
        $candidates = [
            data_get($data, 'amount_destination'),
            data_get($data, 'to_amount'),
            data_get($data, 'destination_amount'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            return $this->graphService->normalizeMoneyFromGraph($candidate);
        }

        return 0.0;
    }
}
