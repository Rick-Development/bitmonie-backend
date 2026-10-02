<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\Withdrawals;
use App\Mail\CryptoWithdrawalNotificationMail;
use App\Models\CryptoNotificationLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Http\Helpers\Response;
use App\Services\QuidaxService;
use App\Services\CryptoTransactionService;
use App\Services\QuidaxSpendableBalanceService;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Cache;


class QuidaxController extends Controller
{
    public $quidax;
    protected QuidaxSpendableBalanceService $quidaxSpendableBalanceService;
    protected int $walletFetchRetryAttempts = 3;
    protected int $walletFetchRetryDelayMicroseconds = 300000;

    public function __construct(
        QuidaxService $quidax,
        QuidaxSpendableBalanceService $quidaxSpendableBalanceService,
        protected CryptoTransactionService $cryptoTransactionService
    ) {
        $this->quidax = $quidax;
        $this->quidaxSpendableBalanceService = $quidaxSpendableBalanceService;
    }


    public function getUser()
    {
        $response = $this->quidax->getUser();

        return Response::success('User data fetch successfully!', $response['data']);
    }



    public function fetchUserWallets(Request $request)
    {
        
        $response = $this->quidax->fetchUserWallets(auth()->user()->quidax_id);
        
        // \Log::info($response);
        if (!$this->isSuccessfulQuidaxResponse($response) || !isset($response['data']) || !is_array($response['data'])) {
            return Response::errorResponse($response['message'] ?? 'Failed to fetch wallets.', $response['data'] ?? $response, 400);
        }
       $exchangeRate = Cache::remember('quidax:usdtngn:last', 60, function () {
       $ticker = $this->quidax->getTicker('usdtngn');

    return data_get($ticker, 'data.usdtngn.ticker.last');
});


        $wallets = $this->prepareWalletListForResponse(auth()->user(), $response['data'],null, $exchangeRate);
    
        
        return Response::success('Wallets fetch successfully!', $wallets);
    }

    // fetchUserWallet($quidax_id,$currency)
    public function fetchUserWallet(Request $request)
    {
        $response = $this->quidax->fetchUserWallet(auth()->user()->quidax_id, $request->currency);
        // \Log::info($response);
        if (!$this->isSuccessfulQuidaxResponse($response) || !isset($response['data']) || !is_array($response['data'])) {
            return Response::errorResponse($response['message'] ?? 'Failed to fetch wallet.', $response['data'] ?? $response, 400);
        }

        $wallets = $this->prepareWalletListForResponse(auth()->user(), $response['data'], $request->currency);
        
        // Preserve the legacy mobile contract where a single-wallet lookup still returns a list in `data`.
        return Response::success(
            'Wallet fetch successfully!',
            $wallets
        );
    }


    public function fetchPaymentAddress(Request $request)
    {
        
        $response = $this->quidax->fetchPaymentAddress(auth()->user()->quidax_id, $request->currency);
    
        return Response::success('Fetch successfully!', $response['data']);
    }

    public function fetchAddress(Request $request)
{
        
    $validator = \Validator::make($request->all(), [
        'currency' => 'required',
        'network' => 'required|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Validation failed',
            'data' => $validator->errors()
        ], 422);
    }

    $response = $this->quidax->fetchPaymentAddressses(auth()->user()->quidax_id, $request->currency);


    $data = collect($response['data'])->first(function ($item) use ($request) {
        return strcasecmp($item['network'], $request->network) === 0;
    });

    if (empty($data)) {
        return response()->json([
            'status' => 'failed',
            'message' => 'No address found',
            'data' => []
        ], 422);
    }

    return Response::success('Fetch successfully!', $data);
}
    public function fetchPaymentAddressses(Request $request)
    {
        $response = $this->quidax->fetchPaymentAddressses(auth()->user()->quidax_id, $request->currency);
        return Response::success($response['message'], $response['data']);
    }

    public function createCryptoPaymentAddress(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required',
            'network' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $user = auth()->user()->quidax_id;
        if (!$user) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Unknown quidax id',
                'data' => $user,
            ]);
        }

        $response = $this->quidax->createCryptoPaymentAddress($user, $request->currency, $request->network);
        return Response::success($response['message'], $response['data']);
    }
    public function createSwapQuotation(Request $request)
    {
        $data = [
            'from_currency' => strtolower($request->from_currency),
            'to_currency' => strtolower($request->to_currency),
            'from_amount' => "{$request->from_amount}",
            // 'to_amount' => '11'
        ];
        $response = $this->quidax->createSwapQuotation(auth()->user()->quidax_id, $data);
        return Response::success($response['message'], $response['data']);
    }
    public function swap(Request $request)
    {
        $response = $this->quidax->swap(auth()->user()->quidax_id, $request->quotation_id);
       if($response['status'] == 'error'){
        return Response::error( $response['message'], $response['data']);
       }
       if($response['status'] == 'Error'){
            return Response::error('Swap failed', $response['error']);
        }else{
            return Response::success($response['message'], $response['data']);
        }
    }

    public function fetch_withdraws(Request $request)
    {
        if (!$request->status || !$request->currency) {
            return response()->json([
                'message' => 'status or currency param required',
            ]);
        }

        $user = auth()->user();
        $requestedCurrency = strtolower((string) $request->currency);
        $requestedStatus = strtolower((string) $request->status);

        $response = $this->quidax->fetch_withdraws($user->quidax_id, $requestedCurrency, $requestedStatus);
        $providerWithdrawals = $this->isSuccessfulQuidaxResponse($response) && isset($response['data']) && is_array($response['data'])
            ? array_values($response['data'])
            : [];

        $localWithdrawals = $this->getLocalWithdrawalReservations($user, $requestedCurrency, $requestedStatus);

        if (!$this->isSuccessfulQuidaxResponse($response) && $localWithdrawals === []) {
            return Response::errorResponse($response['message'] ?? 'Failed to fetch withdrawals.', $response['data'] ?? $response, 400);
        }

        return Response::success(
            $this->isSuccessfulQuidaxResponse($response) ? $response['message'] : 'Withdrawals fetched successfully!',
            array_values(array_merge($localWithdrawals, $providerWithdrawals))
        );
    }

    public function cancel_withdrawal(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'withdrawal_id' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $response = $this->quidax->cancel_withdrawal($request->withdrawal_id);

        if (!$this->isSuccessfulQuidaxResponse($response)) {
            return Response::errorResponse($response['message'] ?? 'Unable to cancel withdrawal.', $response['data'] ?? $response, 400);
        }

        Withdrawals::query()
            ->where('user_id', auth()->id())
            ->where(function ($query) use ($request) {
                $query->where('reference', $request->withdrawal_id)
                    ->orWhere('trans_id', 'pending:' . $request->withdrawal_id);
            })
            ->get()
            ->each(function (Withdrawals $withdrawal) {
                $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
                $walletMeta['status'] = 'cancelled';

                $withdrawal->update([
                    'trans_id' => 'cancelled:' . ($withdrawal->reference ?? $withdrawal->id),
                    'wallet' => $walletMeta,
                ]);
            });

        return Response::success($response['message'], $response['data']);
    }

    public function getWithdrawalFee(Request $request) {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required|string',
            'network' => 'required|string',
           'amount'=>'required|numeric|min:0.00000001',
        ]);
    

        if ($validator->fails()) {
           return Response::error('Validation failed',$validator->errors()->all());
        }

        try {
            $currency = strtolower(trim((string) $request->currency));
            $network = $this->resolveWithdrawalNetwork((string) $request->network);
              $sourceAmount = $this->decimalAmount($request->amount);
            $feeResponse = $this->quidax->getWithdrawalFee($currency,$sourceAmount, $network);

            if (!$this->isSuccessfulQuidaxResponse($feeResponse) || !isset($feeResponse['data']['fee'])) {
                return Response::error($feeResponse['message'] ?? 'Failed to fetch withdrawal fee.', $feeResponse['data'] ?? $feeResponse, 400);
            }
            
            $feeAmount = $feeResponse['data']['fee'] ?? 0;
            $feeType = $feeResponse['data']['type'] ?? 'flat'; 

            // If fee is percentage (not likely checking docs but handled), usually crypto fees are flat
            // But if logic required:
            // if($feeType != 'flat'){ ... } 
            
            // Apply 2x Markup
            $markupFee = $feeAmount * 2;

            $data = [
                'currency' => $currency,
                'network' => $network,
                'original_fee' => $feeAmount,
                'total_fee' => $markupFee,
                'type' => $feeType
            ];

            return Response::success('Withdrawal fee fetched', $data);

        } catch (\Exception $e) {
            return Response::error('Failed to fetch fee: ' . $e->getMessage(),[]);
        }
    }

    public function create_withdrawal(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required|string',
            'network' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
            'fund_uid' => 'required|string',
           
            'narration' => 'nullable|string',
        ]);



        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $sourceCurrency = strtolower(trim((string) $request->currency));
        $network = $this->resolveWithdrawalNetwork((string) $request->network);
        $sourceAmount = $this->decimalAmount($request->amount);
        $addressValidation = $this->normalizeWithdrawalAddress((string) $request->fund_uid, $network);
     

        if (!($addressValidation['valid'] ?? false)) {
            return Response::errorResponse($addressValidation['message'], $addressValidation['data'], 422);
        }
    
        $fundUid = (string) $addressValidation['address'];

        if (($addressValidation['normalized'] ?? false) === true) {
            Log::info('Crypto withdrawal address normalized before provider request.', [
                'user_id' => auth()->id(),
                'coin' => $sourceCurrency,
                'network' => $network,
                'input_address' => $this->maskCryptoAddress((string) $request->fund_uid),
                'normalized_address' => $this->maskCryptoAddress($fundUid),
            ]);
        }

        $data = [
            'currency' => $sourceCurrency,
            'network' => $network,
            'amount' => $this->formatCryptoAmount($sourceAmount),
            'fund_uid' => $fundUid,
        ];
        $referenceId = Str::uuid();
        $refIdString = (string) $referenceId . '-auto_bill';
        $data = array_merge($data, ['reference' => $refIdString]);

        $quidax_id = auth()->user()->quidax_id;
        if (is_null($quidax_id) || empty($quidax_id)) {
            return Response::error([
                'status' => 'failed',
                'message' => 'Invalid quidax id',
                'data' => [
                    'Quidax id' => $quidax_id
                ],
            ]);
        }

        // 1. Fee Calculation & Balance Check
      
        $feeResponse = $this->quidax->getWithdrawalFee(strtolower($sourceCurrency),$sourceAmount, strtolower($network));
        

        if (!$this->isSuccessfulQuidaxResponse($feeResponse) || !isset($feeResponse['data']['fee'])) {
            return Response::errorResponse($feeResponse['message'] ?? 'Could not determine withdrawal fee.', $feeResponse['data'] ?? $feeResponse, 400);
        }
        $feeAmount = $this->decimalAmount($feeResponse['data']['fee'] ?? '0');
        $feeType = $feeResponse['data']['type'] ?? 'flat'; // Default to flat if undefined

        if($feeType != 'flat'){
            $feeAmount = $this->divideCryptoAmounts($this->multiplyCryptoAmounts($sourceAmount, $feeAmount), '100');
        }
        $feeAmount = $this->multiplyCryptoAmounts($feeAmount, '2'); // Platform charges 2x the Quidax fee
        
        $totalAmount = $this->addCryptoAmounts($sourceAmount, $feeAmount);
        

        $walletResponse = $this->fetchProviderWalletWithRetry($quidax_id, strtolower($sourceCurrency));
        if (!$this->isSuccessfulQuidaxResponse($walletResponse) || !isset($walletResponse['data']) || !is_array($walletResponse['data'])) {
            return Response::errorResponse($walletResponse['message'] ?? 'Could not fetch your crypto wallet.', $walletResponse['data'] ?? $walletResponse, 400);
        }

        $normalizedWallets = $this->normalizeWalletCollection($walletResponse['data']);
        $sourceWallet = $normalizedWallets[0] ?? [];

        if ($sourceWallet === []) {
            return Response::errorResponse('Could not resolve your crypto wallet details.', $walletResponse['data'], 400);
        }

    
        $networkValidation = $this->validateWithdrawalNetworkAvailability($sourceWallet, $sourceCurrency, $network);
        if ($networkValidation !== null) {
            return Response::errorResponse($networkValidation['message'], $networkValidation['data'], 422);
        }
    
 $user = auth()->user();
        $mainAccountResponse = $this->quidax->getUser();
        if (!$this->isSuccessfulQuidaxResponse($mainAccountResponse) || empty($mainAccountResponse['data']['id'])) {
            return Response::errorResponse(
                $mainAccountResponse['message'] ?? 'Could not resolve the Quidax main account.',
                $mainAccountResponse['data'] ?? $mainAccountResponse,
                400
            );
        }

        $mainAccountId = (string) $mainAccountResponse['data']['id'];

        $walletData = [];
        $availableBalance = '0';
        $availableBalanceAfterReservation = '0';
        $pendingWithdrawal = null;
  

        try {
            DB::transaction(function () use (
                $user,
                $walletResponse,
                $sourceCurrency,
                $network,
                $sourceAmount,
                $feeAmount,
                $totalAmount,
                $refIdString,
                $fundUid,
                &$walletData,
                &$availableBalance,
                &$availableBalanceAfterReservation,
                &$pendingWithdrawal
            ) {
                \App\Models\User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
 
                $walletData = $this->augmentWalletPayload($user, $walletResponse['data'], $sourceCurrency);
                

// \Log::info($walletResponse['data'] );
                $availableBalance = $this->decimalAmount($walletData['available_balance'] ?? '0');

                Log::info('Crypto withdrawal balance validation before reservation.', [
                    'user_id' => $user->id,
                    'coin' => $sourceCurrency,
                    'network' => $network,
                    'requested_amount' => $this->formatCryptoAmount($sourceAmount),
                    'fee' => $this->formatCryptoAmount($feeAmount),
                    'total_required' => $this->formatCryptoAmount($totalAmount),
                    'provider_balance' => $this->formatCryptoAmount($walletData['provider_balance'] ?? '0'),
                    'reserved_outgoing_balance' => $this->formatCryptoAmount($walletData['reserved_outgoing_balance'] ?? '0'),
                    'available_balance' => $this->formatCryptoAmount($availableBalance),
                    'reference' => $refIdString,
                    'recipient_address' => $this->maskCryptoAddress($fundUid),
                ]);

                if ($this->compareCryptoAmounts($availableBalance, $totalAmount) < 0) {
                    throw new \RuntimeException(json_encode([
                        'message' => 'Insufficient available balance. Required: ' . $this->formatCryptoAmount($totalAmount) . " {$sourceCurrency} (including " . $this->formatCryptoAmount($feeAmount) . " fee) but available: " . $this->formatCryptoAmount($availableBalance) . " {$sourceCurrency}.",
                        'data' => [
                            'currency' => $sourceCurrency,
                            'network' => $network,
                            'amount' => $this->formatCryptoAmount($sourceAmount),
                            'fee' => $this->formatCryptoAmount($feeAmount),
                            'total' => $this->formatCryptoAmount($totalAmount),
                            'available_balance' => $this->formatCryptoAmount($availableBalance),
                            'locked_balance' => $this->formatCryptoAmount($walletData['locked_balance'] ?? '0'),
                            'provider_balance' => $this->formatCryptoAmount($walletData['provider_balance'] ?? $availableBalance),
                            'reserved_outgoing_balance' => $this->formatCryptoAmount($walletData['reserved_outgoing_balance'] ?? '0'),
                            'total_balance' => $this->formatCryptoAmount($walletData['total_balance'] ?? $availableBalance),
                        ],
                    ]));
                }

                $availableBalanceAfterReservation = $this->maxZeroCryptoAmount($this->subtractCryptoAmounts($availableBalance, $totalAmount));

                $pendingWithdrawal = Withdrawals::create([
                    'user_id' => $user->id,
                    'reference' => $refIdString,
                    'type' => 'coin_address',
                    'currency' => $sourceCurrency,
                    'amount' => $this->formatCryptoAmount($sourceAmount),
                    'fee' => $this->formatCryptoAmount($feeAmount),
                    'total' => $this->formatCryptoAmount($totalAmount),
                    'trans_id' => 'pending:' . $refIdString,
                    'transaction_note' => 'Crypto withdrawal processing',
                    'recipient_data' => [
                        'type' => 'coin_address',
                        'details' => [
                            'address' => $fundUid,
                            'network' => $network,
                        ],
                    ],
                    'wallet' => [
                        'status' => 'processing',
                        'reservation_type' => 'crypto_withdrawal',
                        'provider_balance' => $this->formatCryptoAmount($walletData['provider_balance'] ?? '0'),
                        'provider_locked_balance' => $this->formatCryptoAmount($walletData['provider_locked_balance'] ?? '0'),
                        'reserved_outgoing_balance' => $this->formatCryptoAmount($this->addCryptoAmounts($walletData['reserved_outgoing_balance'] ?? '0', $totalAmount)),
                        'network' => $network,
                    ],
                    'user' => [
                        'status' => 'processing',
                    ],
                ]);
            });
        } catch (\RuntimeException $e) {
            $decoded = json_decode($e->getMessage(), true);
            if (is_array($decoded) && isset($decoded['message'])) {
                return Response::errorResponse($decoded['message'], $decoded['data'] ?? [], 422);
            }

            throw $e;
        }

        if (!$pendingWithdrawal) {
            return Response::errorResponse('Unable to create withdrawal reservation right now.', [], 500);
        }

        
        // Capture the withdrawal in the shared crypto transaction history.
$cryptoTransaction = $this->cryptoTransactionService->create([
    'internal_trx_type'   => 'crypto_withdrawal',
    'internal_trx_ref_id' => $pendingWithdrawal->id,
    'transaction_type'    => 'withdrawal',
    'sender_address'      => (string) $quidax_id,          // Quidax sub-account id (sender)
    'receiver_address'    => $fundUid,                     // destination crypto address
    'amount'              => $this->formatCryptoAmount($sourceAmount),
    'asset'               => strtoupper($sourceCurrency),
    'block_number'        => null,
    'txn_hash'            => null,                         // will be filled later when Quidax returns txId
    'chain'               => $network,
    'status'              => 'pending',
    'callback_response'   => [
        'source'                => 'quidax_withdrawal',
        'withdrawal_reference'  => $refIdString,
        'type'                  => 'coin_address',
        'currency'              => $sourceCurrency,
        'amount'                => $this->formatCryptoAmount($sourceAmount),
        'fee'                   => $this->formatCryptoAmount($feeAmount),
        'total'                 => $this->formatCryptoAmount($totalAmount),
        'network'               => $network,
        'recipient'             => [
            'type' => 'coin_address',
            'details' => [
                'address'         => $fundUid,
                'name'            => 'N/A',
                'destination_tag' => '',
            ],
        ],
        'status'                => 'processing',
        // These will be updated later from the real Quidax response
        'quidax_id'             => null,
        'txId'                  => null,
    ],
]);

        $this->queueWithdrawalStatusNotification($user, $pendingWithdrawal, 'initiated', [
            'amount' => $this->formatCryptoAmount($sourceAmount),
            'coin' => $sourceCurrency,
            'network' => $network,
            'recipient_address' => $fundUid,
            'transaction_hash' => null,
            'transaction_reference' => $refIdString,
            'status' => 'initiated',
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        // Main Account Withdrawal Data (Move total amount to main)
        $mainAccountData = [
            'currency' => $sourceCurrency,
            'amount' => $this->formatCryptoAmount($totalAmount),
            'network' => $network,
            'recipient_id' => $mainAccountId,
            'transaction_note' => 'Internal transfer to main account',
            'narration' => 'Internal transfer to main account',
            'reference' => $refIdString . '-main',
        ];

        // Dispatch Job
        // Pass fee and total for recording
        // We inject them into destinationData or pass separate args. 
        // Let's pass them as separate constructor args to the Job.
        $destinationData = $data; // Original dest data
        
        \App\Jobs\ProcessQuidaxWithdrawal::dispatchAfterResponse($user, $mainAccountData, $destinationData, $feeAmount, $totalAmount, $pendingWithdrawal->id);

        return response()->json([
            'message' => 'Withdrawal request accepted and is processing. Do not mark this transfer as completed until the final withdrawal status is confirmed.',
            'data' => [
                'status' => 'processing',
                'reference' => $refIdString,
                'currency' => $sourceCurrency,
                'network' => $network,
                'amount' => $this->formatCryptoAmount($sourceAmount),
                'fee' => $this->formatCryptoAmount($feeAmount),
                'total' => $this->formatCryptoAmount($totalAmount),
                'available_balance' => $this->formatCryptoAmount($availableBalanceAfterReservation),
                'provider_balance' => $this->formatCryptoAmount($walletData['provider_balance'] ?? '0'),
                'reserved_outgoing_balance' => $this->formatCryptoAmount($this->addCryptoAmounts($walletData['reserved_outgoing_balance'] ?? '0', $totalAmount)),
            ],
            'type' => 'pending',
        ], 202);
    }

    public function initiate_ramp_transaction(Request $request)
    {
        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount,
            'merchant_reference' => $request->merchant_reference,

            'customer' => [
                'email' => $request->customer_email,
                'first_name' => $request->customer_first_name,
                'last_name' => $request->customer_last_name,
            ],

            'wallet_address' => [
                'address' => $request->wallet_address,
                'network' => $request->wallet_network,
            ],
        ];
        $response = $this->quidax->initiate_ramp_transaction($data);
        // dd($response);
        return Response::success(
            $response['message'],
            [
                'api_data' => $response['data'],
                'user_data' => $data,
            ]
        );
    }

    public function refresh_instant_swap_quotation(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'quotation_id' => 'required | string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ], 422);
        }

        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount
        ];

        $response = $this->quidax->refresh_instant_swap_quotation(auth()->user()->quidax_id, $request->quotation_id, $data);
        // dd($response);
        return Response::success($response['message'], $response['data']);
    }

    public function fetch_swap_transaction(Request $request)
    {
        if (!$request->transaction_id) {
            return response()->json([
                'message' => 'transaction_id param required',
            ]);
        }

        $response = $this->quidax->fetch_swap_transaction(auth()->user()->quidax_id, $request->transaction_id);
        // dd($response);
        return Response::success($response['message'], $response['data']);
    }

    public function get_swap_transaction()
    {
        $response = $this->quidax->get_swap_transacdtion(auth()->user()->quidax_id);
        return Response::success($response['message'], $response['data']);
    }

    public function temporary_swap_quotation(Request $request)
    {
        $data = [
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'from_amount' => $request->from_amount
        ];

        $response = $this->quidax->temporary_swap_quotation(auth()->user()->quidax_id, $data);
        return Response::success($response['message'], $response['data']);
    }
    // createCryptoPaymentAddress($quidax_id,$currency,$data)

    // fetchPaymentAddress($quidax_id,$currency){
    public function fetch_deposits(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'currency' => 'required | string',
            'state' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->fetch_deposits(auth()->user()->quidax_id, $request->currency, $request->state);
        return Response::success($response['message'], $response['data']);
    }

    public function fetch_a_deposit(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'deposit_id' => 'required | string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->fetch_a_deposit(auth()->user()->quidax_id, $request->deposit_id);
        return Response::success($response['message'], $response['data']);
    }

    public function get_all_public_adverts(Request $request)
    {
        $data = $request->only('side');
        $response = $this->quidax->get_all_public_adverts($data);
        return Response::success($response['message'], $response['data']);
    }

    public function get_single_public_advert(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'advert_id' => 'required | string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation failed',
                'data' => $validator->errors()
            ]);
        }

        $response = $this->quidax->get_single_public_advert($request->advert_id);
        return Response::success($response['message'], $response['data']);
    }

    protected function isSuccessfulQuidaxResponse($response): bool
    {
        if (!is_array($response)) {
            return false;
        }

        return in_array(strtolower((string) ($response['status'] ?? '')), ['success', 'ok'], true);
    }

  protected function buildWalletList($user,array $walletData,string $currency = null,?float $exchangeRate = null
): array{
        $wallets = $this->normalizeWalletCollection($walletData);
        $currencyForAugmentation = count($wallets) === 1 ? $currency : null;

        return array_values(array_map(function (array $wallet) use ($user, $currencyForAugmentation, $exchangeRate) {
            return $this->augmentWalletPayload($user, $wallet, $currencyForAugmentation,$exchangeRate);
        }, $wallets));
    }

    protected function prepareWalletListForResponse($user,array $walletData,?string $currency = null,?float $exchangeRate = null): array{

        return array_values(array_map(function (array $wallet) {
            return $this->roundWalletNumberFields($wallet);
        }, $this->buildWalletList($user, $walletData, $currency,$exchangeRate)));
    }

    protected function normalizeWalletCollection(array $walletData): array
    {
        if (!$this->looksLikeWalletCollection($walletData)) {
            return [$this->normalizeWalletPayload($walletData)];
        }

        return array_values(array_map(function ($wallet) {
            return $this->normalizeWalletPayload(is_array($wallet) ? $wallet : []);
        }, array_values($walletData)));
    }

    protected function normalizeWalletPayload(array $wallet): array
    {
        while ($this->isNumericWrappedWallet($wallet)) {
            $wrappedKey = array_key_first($wallet);
            $wallet = is_array($wallet[$wrappedKey]) ? $wallet[$wrappedKey] : [];
        }

        return $wallet;
    }

    protected function looksLikeWalletCollection(array $walletData): bool
    {
        if ($walletData === []) {
            return true;
        }

        foreach (array_keys($walletData) as $key) {
            if (!is_int($key) && !(is_string($key) && ctype_digit($key))) {
                return false;
            }
        }

        return true;
    }

    protected function isNumericWrappedWallet(array $wallet): bool
    {
        if (count($wallet) !== 1) {
            return false;
        }

        $wrappedKey = array_key_first($wallet);
        if ($wrappedKey === null) {
            return false;
        }

        if (!is_int($wrappedKey) && !(is_string($wrappedKey) && ctype_digit($wrappedKey))) {
            return false;
        }

        return is_array($wallet[$wrappedKey]);
    }

    protected function augmentWalletPayload($user, array $wallet, ?string $currency = null, $exchangeRate =null): array
    {
        
        return $this->quidaxSpendableBalanceService->augmentWalletPayload($user, $wallet, $currency,  $exchangeRate);
    }

    protected function roundWalletNumberFields(array $wallet): array
    {
        foreach ([
            'balance',
            'locked',
            'staked',
            'converted_balance',
            'provider_balance',
            'provider_locked_balance',
            'provider_converted_balance',
            'reserved_outgoing_balance',
            'available_balance',
            'available_converted_balance',
            'locked_balance',
            'locked_converted_balance',
            'total_balance',
        ] as $field) {
            if (array_key_exists($field, $wallet) && is_numeric($wallet[$field])) {
                $wallet[$field] = $this->formatCryptoAmount($wallet[$field]);
            }
        }

        return $wallet;
    }

    protected function validateWithdrawalNetworkAvailability(array $wallet, string $currency, string $network): ?array
    {
        $normalizedCurrency = strtolower(trim($currency));
        $normalizedNetwork = $this->resolveWithdrawalNetwork($network);
        $networks = is_array($wallet['networks'] ?? null) ? array_values(array_filter($wallet['networks'], 'is_array')) : [];

        if (array_key_exists('is_crypto', $wallet) && !$wallet['is_crypto']) {
            return [
                'message' => strtoupper($normalizedCurrency) . ' is not a crypto asset.',
                'data' => [
                    'currency' => $normalizedCurrency,
                    'network' => $normalizedNetwork,
                ],
            ];
        }

        if (array_key_exists('blockchain_enabled', $wallet) && !$wallet['blockchain_enabled']) {
            return [
                'message' => 'Withdrawals are not enabled for ' . strtoupper($normalizedCurrency) . ' right now.',
                'data' => [
                    'currency' => $normalizedCurrency,
                    'network' => $normalizedNetwork,
                ],
            ];
        }

        if ($networks === []) {
            return null;
        }

        $matchedNetwork = null;
        foreach ($networks as $networkMeta) {
            if (strtolower((string) ($networkMeta['id'] ?? '')) === $normalizedNetwork) {
                $matchedNetwork = $networkMeta;
                break;
            }
        }

        if ($matchedNetwork === null) {
            return [
                'message' => 'Network ' . strtoupper($normalizedNetwork) . ' is not available for ' . strtoupper($normalizedCurrency) . '.',
                'data' => [
                    'currency' => $normalizedCurrency,
                    'network' => $normalizedNetwork,
                    'available_networks' => array_values(array_map(function (array $networkMeta) {
                        return [
                            'id' => strtolower((string) ($networkMeta['id'] ?? '')),
                            'name' => $networkMeta['name'] ?? null,
                            'withdraws_enabled' => (bool) ($networkMeta['withdraws_enabled'] ?? false),
                        ];
                    }, $networks)),
                ],
            ];
        }

        if (!($matchedNetwork['withdraws_enabled'] ?? false)) {
            return [
                'message' => 'Withdrawals are currently unavailable on ' . ($matchedNetwork['name'] ?? strtoupper($normalizedNetwork)) . ' for ' . strtoupper($normalizedCurrency) . '.',
                'data' => [
                    'currency' => $normalizedCurrency,
                    'network' => $normalizedNetwork,
                    'network_name' => $matchedNetwork['name'] ?? null,
                    'withdraws_enabled' => false,
                ],
            ];
        }

        return null;
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

    protected function fetchProviderWalletWithRetry(?string $quidaxId, ?string $currency): array
    {
        $normalizedCurrency = strtolower(trim((string) $currency));
        $lastResponse = [
            'status' => 'error',
            'message' => 'Unable to reach Quidax wallet service right now. Please try again.',
            'data' => null,
        ];

        for ($attempt = 1; $attempt <= $this->walletFetchRetryAttempts; $attempt++) {
            $response = $this->quidax->fetchUserWallet($quidaxId, $normalizedCurrency);

            if ($this->isSuccessfulQuidaxResponse($response) && isset($response['data']) && is_array($response['data'])) {
                return $response;
            }

            $lastResponse = $this->normalizeProviderWalletFailure($response, $lastResponse['message']);

            Log::warning('Quidax wallet fetch attempt failed.', [
                'quidax_id' => $quidaxId,
                'currency' => $normalizedCurrency,
                'attempt' => $attempt,
                'response' => $lastResponse,
            ]);

            if (!$this->shouldRetryProviderWalletFetch($response) || $attempt === $this->walletFetchRetryAttempts) {
                break;
            }

            if (!$this->appIsRunningUnitTests()) {
                usleep($this->walletFetchRetryDelayMicroseconds);
            }
        }

        return $lastResponse;
    }

    protected function shouldRetryProviderWalletFetch($response): bool
    {
        return is_array($response) && (($response['retryable'] ?? false) === true);
    }

    protected function normalizeProviderWalletFailure($response, string $fallbackMessage): array
    {
        if (!is_array($response)) {
            return [
                'status' => 'error',
                'message' => $fallbackMessage,
                'data' => null,
            ];
        }

        $message = trim((string) ($response['message'] ?? ''));
        if ($message === '') {
            $message = $fallbackMessage;
        }

        return [
            'status' => strtolower((string) ($response['status'] ?? 'error')),
            'message' => $message,
            'data' => $response['data'] ?? null,
            'error' => $response['error'] ?? null,
            'retryable' => (bool) ($response['retryable'] ?? false),
        ];
    }

    protected function appIsRunningUnitTests(): bool
    {
        return app()->runningUnitTests();
    }

    protected function queueWithdrawalStatusNotification($user, Withdrawals $withdrawal, string $status, array $details = []): void
    {
        try {
            $reference = (string) ($details['transaction_reference'] ?? $withdrawal->reference ?? $withdrawal->id);
            $dedupeKey = $this->withdrawalNotificationDedupeKey($reference, $status, 'user');
            $existing = CryptoNotificationLog::where('dedupe_key', $dedupeKey)->first();

            if ($existing) {
                $existing->forceFill([
                    'duplicate' => true,
                    'duplicate_count' => $existing->duplicate_count + 1,
                ])->save();

                Log::info('Duplicate crypto withdrawal email suppressed.', [
                    'user_id' => $user->id ?? null,
                    'withdrawal_id' => $withdrawal->id,
                    'reference' => $reference,
                    'status' => $status,
                    'dedupe_key' => $dedupeKey,
                    'duplicate_count' => $existing->duplicate_count,
                ]);

                return;
            }

            $recipientData = is_array($withdrawal->recipient_data) ? $withdrawal->recipient_data : [];
            $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
            $transactionHash = $this->validStoredTransactionHash($walletMeta['transaction_hash'] ?? null)
                ?? $this->validStoredTransactionHash($walletMeta['txid'] ?? null)
                ?? $this->validStoredTransactionHash($withdrawal->trans_id ?? null, [
                    $walletMeta['provider_withdrawal_id'] ?? null,
                    $withdrawal->reference ?? null,
                ]);
            $details = array_merge([
                'user_id' => $user->id ?? $withdrawal->user_id,
                'amount' => (string) ($withdrawal->amount ?? '0'),
                'coin' => (string) ($withdrawal->currency ?? ''),
                'network' => (string) ($this->extractNetwork($walletMeta) ?? $this->extractNetwork($recipientData) ?? ''),
                'recipient_address' => $this->extractRecipientAddress($recipientData) ?? $this->extractRecipientAddress($walletMeta),
                'transaction_hash' => $transactionHash,
                'transaction_reference' => $reference,
                'status' => $status,
                'timestamp' => now()->toDateTimeString(),
            ], $details);

            if (!$this->shouldSendUserWithdrawalNotification($status, $details)) {
                Log::info('Local crypto withdrawal email skipped until terminal details are complete.', [
                    'user_id' => $user->id ?? null,
                    'withdrawal_id' => $withdrawal->id,
                    'reference' => $reference,
                    'status' => $status,
                    'has_transaction_hash' => !empty($details['transaction_hash']),
                ]);

                return;
            }

            $log = CryptoNotificationLog::create([
                'provider' => 'quidax',
                'event_name' => "local.withdrawal.{$status}",
                'event_id' => $reference,
                'dedupe_key' => $dedupeKey,
                'transaction_reference' => $reference,
                'user_id' => $user->id ?? null,
                'payload' => [
                    'source' => 'local_withdrawal_flow',
                    'withdrawal_id' => $withdrawal->id,
                    'details' => $details,
                ],
                'notification_type' => "crypto_withdrawal_{$status}",
                'status' => 'processing',
                'duplicate' => false,
            ]);

            if (!$user || empty($user->email)) {
                $log->update([
                    'status' => 'ignored',
                    'error_message' => 'User email is missing for withdrawal notification.',
                ]);

                return;
            }

            Mail::to($user->email)->queue(new CryptoWithdrawalNotificationMail($user, $details));
            $this->logMailFailures("crypto_withdrawal_{$status}", $reference);

            $log->update([
                'status' => 'queued',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Crypto withdrawal email queue failed.', [
                'user_id' => $user->id ?? null,
                'withdrawal_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'status' => $status,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function withdrawalNotificationDedupeKey(string $reference, string $status, string $scope = 'user'): string
    {
        return sha1('quidax|withdrawal|' . strtolower($scope) . '|' . strtolower($reference) . '|' . strtolower($status));
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

    protected function logMailFailures(string $notificationType, ?string $reference = null): void
    {
        $mailer = Mail::getFacadeRoot();

        if (!method_exists($mailer, 'failures')) {
            return;
        }

        $failures = Mail::failures();
        if (!empty($failures)) {
            Log::error('Mail failures reported after crypto notification queue.', [
                'notification_type' => $notificationType,
                'reference' => $reference,
                'failures' => $failures,
            ]);
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

    protected function addCryptoAmounts($left, $right): string
    {
        return bcadd($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function subtractCryptoAmounts($left, $right): string
    {
        return bcsub($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function multiplyCryptoAmounts($left, $right): string
    {
        return bcmul($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function divideCryptoAmounts($left, $right): string
    {
        if ($this->compareCryptoAmounts($right, '0') === 0) {
            return '0';
        }

        return bcdiv($this->decimalAmount($left), $this->decimalAmount($right), 18);
    }

    protected function compareCryptoAmounts($left, $right): int
    {
        return bccomp($this->decimalAmount($left), $this->decimalAmount($right), 8);
    }

    protected function maxZeroCryptoAmount(string $value): string
    {
        return $this->compareCryptoAmounts($value, '0') < 0 ? '0' : $value;
    }

    protected function roundCryptoAmount(float $value): float
    {
        return round($value, 8);
    }

    protected function formatCryptoAmount($value): string
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

        $formatted = bcadd($value, '0', 8);
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    protected function getLocalWithdrawalReservations($user, string $currency, string $requestedStatus): array
    {
        return Withdrawals::query()
            ->where('user_id', $user->id)
            ->where('currency', $currency)
            ->latest('id')
            ->get()
            ->filter(function (Withdrawals $withdrawal) use ($requestedStatus) {
                $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];

                if (($walletMeta['reservation_type'] ?? null) !== 'crypto_withdrawal') {
                    return false;
                }

                return $this->matchesLocalWithdrawalStatus((string) ($walletMeta['status'] ?? ''), $requestedStatus);
            })
            ->map(fn (Withdrawals $withdrawal) => $this->formatLocalWithdrawalReservation($withdrawal))
            ->values()
            ->all();
    }

    protected function matchesLocalWithdrawalStatus(string $localStatus, string $requestedStatus): bool
    {
        $normalizedLocalStatus = strtolower(trim($localStatus));
        $normalizedRequestedStatus = strtolower(trim($requestedStatus));

        if ($normalizedRequestedStatus === '' || in_array($normalizedRequestedStatus, ['all', '*'], true)) {
            return true;
        }

        return match ($normalizedRequestedStatus) {
            'processing', 'pending' => $normalizedLocalStatus === 'processing',
            'failed', 'rejected', 'error' => $normalizedLocalStatus === 'failed',
            'cancelled', 'canceled' => $normalizedLocalStatus === 'cancelled',
            default => $normalizedLocalStatus === $normalizedRequestedStatus,
        };
    }

    protected function formatLocalWithdrawalReservation(Withdrawals $withdrawal): array
    {
        $walletMeta = is_array($withdrawal->wallet) ? $withdrawal->wallet : [];
        $userMeta = is_array($withdrawal->user) ? $withdrawal->user : [];
        $recipientData = is_array($withdrawal->recipient_data) ? $withdrawal->recipient_data : [];
        $status = strtolower((string) ($walletMeta['status'] ?? 'processing'));
        $transactionHash = $this->validStoredTransactionHash($walletMeta['transaction_hash'] ?? null)
            ?? $this->validStoredTransactionHash($walletMeta['txid'] ?? null)
            ?? $this->validStoredTransactionHash($withdrawal->trans_id ?? null, [
                $walletMeta['provider_withdrawal_id'] ?? null,
                $withdrawal->reference ?? null,
            ]);
        $recipientAddress = $this->extractRecipientAddress($recipientData) ?? $this->extractRecipientAddress($walletMeta);
        $network = $this->extractNetwork($walletMeta) ?? $this->extractNetwork($recipientData);

        return [
            'id' => $withdrawal->reference ?? (string) $withdrawal->id,
            'reference' => $withdrawal->reference,
            'type' => $withdrawal->type,
            'currency' => $withdrawal->currency,
            'amount' => (string) ($withdrawal->amount ?? 0),
            'fee' => (string) ($withdrawal->fee ?? 0),
            'total' => (string) ($withdrawal->total ?? $withdrawal->amount ?? 0),
            'network' => $network,
            'recipient_address' => $recipientAddress,
            'txid' => $transactionHash,
            'transaction_hash' => $transactionHash,
            'transaction_note' => $withdrawal->transaction_note,
            'narration' => $withdrawal->transaction_note,
            'status' => ucfirst($status),
            'reason' => $walletMeta['failure_reason'] ?? $userMeta['failure_reason'] ?? null,
            'created_at' => optional($withdrawal->created_at)->toIso8601String(),
            'done_at' => $status === 'failed' || $status === 'cancelled'
                ? optional($withdrawal->updated_at)->toIso8601String()
                : null,
            'recipient' => $withdrawal->recipient_data,
            'wallet' => array_merge($walletMeta, ['status' => ucfirst($status)]),
            'user' => $userMeta,
        ];
    }

    protected function extractRecipientAddress(array $data): ?string
    {
        foreach ([
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
        ] as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '' && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function extractNetwork(array $data): ?string
    {
        foreach ([
            'network',
            'blockchain',
            'chain',
            'recipient.network',
            'recipient.details.network',
            'destination.network',
            'details.network',
            'data.network',
        ] as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '' && is_scalar($value)) {
                return strtolower((string) $value);
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

}
