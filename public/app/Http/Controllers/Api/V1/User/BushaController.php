<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BushaTransaction;
use App\Models\UserWallet;
use App\Services\BushaService;
use App\Http\Helpers\Response;
use App\Services\QuidaxService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\BushaPaymentDetail;
use App\Services\SafeHavenService;
use App\Notifications\User\BushaTradeNotification;

class BushaController extends Controller
{
    protected $bushaService;
    public $quidaxService;
    protected $safehavenService;

    public function __construct(BushaService $bushaService, QuidaxService $quidaxService, SafeHavenService $safehavenService)
    {
        $this->bushaService = $bushaService;
        $this->quidaxService = $quidaxService;
        $this->safehavenService = $safehavenService;
    }



    /**
     * Get Quote
     * Returns a valid quote ID that can be used to execute the trade.
     * 
     * Expected Request Parameters:
     * - source_currency: The currency being spent (e.g., 'BTC' for sell, 'NGN' for buy)
     * - target_currency: The currency being received (e.g., 'NGN' for sell, 'BTC' for buy)
     * - amount: The amount (will be interpreted as source_amount for sell, target_amount for buy)
     * - side: 'buy' or 'sell'
     * - user_address (optional): User's crypto wallet address for receiving crypto
     * - network (optional): Blockchain network (e.g., 'BTC', 'ETH')
     */
    public function quote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source_currency' => 'required|string', 
            'target_currency' => 'required|string', 
            'amount' => 'required|numeric|min:0',
            'side' => 'required|in:buy,sell',
            'user_address' => 'nullable|string', // User's wallet address for crypto
            'network' => 'nullable|string', // Blockchain network
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->all());
        }

        try {
            $user = auth()->user();
            $reference = 'QUO_' . Str::random(10);
            $feeAmount = 0;
            $feeType = '';
            
            // Determine if source or target is crypto
            $cryptoCurrencies = ['BTC', 'ETH', 'USDT', 'USDC', 'LTC', 'DOGE', 'XRP', 'BNB'];
            $sourceCurrency = strtoupper($request->source_currency);
            $targetCurrency = strtoupper($request->target_currency);
            
            $sourceIsCrypto = in_array($sourceCurrency, $cryptoCurrencies);
            $targetIsCrypto = in_array($targetCurrency, $cryptoCurrencies);
            
            // Build pay_in and pay_out based on transaction type
            $pay_in = [];
            $pay_out = [];
            
            if ($request->side == 'buy') {
                // BUY: User spends FIAT (or crypto) to get CRYPTO
                // Pay In: Source currency (what user pays with)
                // Pay Out: Target currency (crypto user receives)
                
                if ($sourceIsCrypto) {
                    // Paying with crypto (e.g., BTC to buy ETH)
                    $pay_in = [
                        "type" => "balance", // Using Busha balance
                    ];
                } else {
                    // Paying with fiat (e.g., NGN to buy BTC)
                    $pay_in = [
                        "type" => "temporary_bank_account", // Using Busha fiat balance
                    ];
                }
                
                // $response = $this->quidaxService = 
                
                $response = $this->quidaxService->fetchPaymentAddressses(auth()->user()->quidax_id, strtolower($request->target_currency));
                \Log::info('Quidax Payment Addresses Response:', ['response' => $response]);
                $data = $response['data'];
                $data = array_filter($data, function ($item) use ($request) {
                    return $item['network'] === strtolower($request->network);
                });

                if (empty($data)) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'No address found',
                        'data' => []
                    ], 422);
                }
                
                // Get the first (and only) matching address
                $data = reset($data);
                $network = $request->network == 'BEP20' ? 'BSC' : ($request->network == 'TRC20' ? 'TRX' : ($request->network == 'ERC20' ? 'Ethereum' : $request->network));
                
                
                // Receiving crypto
                $pay_out = [
                    "type" => "address",
                    "address" => $data['address'], // Use user's address or email
                    "network" => $network,
                ];
                
            } else {
                $network = $request->network == 'BEP20' ? 'BSC' : ($request->network == 'TRC20' ? 'TRX' : ($request->network == 'ERC20' ? 'Ethereum' : $request->network));


            $feeResponse = $this->quidaxService->getWithdrawalFee(strtolower($sourceCurrency), strtolower($request->network));
            $feeAmount = $feeResponse['data']['fee'];
            $feeType = $feeResponse['data']['type'];
            if($feeType != 'flat'){
                $feeAmount = $request->amount * ($feeAmount / 100);
                // $feeAmount = $feeAmount * 2;
            }else{
                $feeAmount = $feeAmount;// * 2;
            }
                // SELL: User spends CRYPTO to get FIAT (or another crypto)
                // Pay In: Source currency (crypto user sells)
                // Pay Out: Target currency (fiat/crypto user receives)
                
                // Paying with crypto
                $pay_in = [
                    "type" => "address",
                    "network" => $request->network ?  $network : $sourceCurrency,
                ];
                
                if ($targetIsCrypto) {
                    // Receiving crypto (e.g., sell BTC for ETH)
                    $pay_out = [
                        "type" => "address",
                        "address" => $request->user_address ?? $user->email,
                        "network" => $targetCurrency,
                    ];
                } else {
                    if ($user->busha_recipient_id == null) {
                      $recipient = $this->bushaService->createRecipient($user);
                      if ($recipient == false) {
                        return Response::errorResponse('Failed to create trading account');
                      }
                        
                    }
                    // Receiving fiat (e.g., sell BTC for NGN)
                    $pay_out = [
                        "type" => "bank_transfer", // Credit to Busha fiat balance
                        'recipient_id' => $user->busha_recipient_id,
                    ];
                }
            }
            
            // Build the payload
            $payload = [
                "source_currency" => $sourceCurrency,
                "target_currency" => $targetCurrency,
                "reference" => $reference,
                "pay_in" => $pay_in,
                "pay_out" => $pay_out
            ];
            
            // Add amount based on side
            // For BUY: User specifies how much crypto they want to receive (target_amount)
            // For SELL: User specifies how much crypto they want to sell (source_amount)
            if ($request->side == 'buy') {
                $payload['target_amount'] = (string)$request->amount;
            } else {
                $payload['source_amount'] = (string)$request->amount;
            }
            
            // Call Busha API
            $bushaResponse = $this->bushaService->quote($payload);

            //\Log::info('Busha quote response', $bushaResponse);
            
            // Check for errors
            if (isset($bushaResponse['error'])) {
                return Response::errorResponse(
                    $bushaResponse['error']['message'] ?? 'Failed to create quote',
                    $bushaResponse['fields'] ?? []
                );
            }
            
            $data = $bushaResponse['data'];
            $data['fee_amount'] = $feeAmount;
            $data['fee_type'] = $feeType;

            return Response::successResponse($bushaResponse['message'], $data);
            
        } catch (Exception $e) {
            //\Log::error('Quote creation failed', [
            //     'error' => $e->getMessage(),
            //     'request' => $request->all()
            // ]);
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Execute Trade
     * Accepts a quote_id returned from quote endpoint.
     */
    /**
     * Execute Trade
     * Accepts a quote_id returned from quote endpoint.
     */
    public function trade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quote_id' => 'required|string',
            'side' => 'required|in:buy,sell',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->all());
        }

        $user = auth()->user();
        $reference = Str::uuid();

        DB::beginTransaction();
        try {
            // 0. Fetch Quote Details
            $quoteDetails = $this->bushaService->getQuoteDetails($request->quote_id)['data'];
            
            $side = $quoteDetails['rate']['side'];
            $sourceCurrency = $quoteDetails['source_currency'];
            $targetCurrency = $quoteDetails['target_currency'];
            $sourceAmount = $quoteDetails['source_amount'];
            $targetAmount = $quoteDetails['target_amount'];
            $totalAmount = 0.00;
            $feeAmount = 0.00;
            
            // 1. Get wallet for balance check
            $wallet = UserWallet::where('user_id', $user->id)->whereHas('currency', function($q) use ($sourceCurrency) {
                $q->where('code', $sourceCurrency);
            })->first();

            if ($side == 'sell') {
                // SELL: User sells crypto for fiat
                // Process via Job Queue to handle timing
                
                $pay_in = $quoteDetails['pay_in'];
                $network = $this->normalizeNetwork($pay_in['network']);
                
                // Check Quidax balance
                $quidaxWallet = $this->quidaxService->fetchUserWallet($user->quidax_id, strtolower($sourceCurrency));
                if($quidaxWallet['status'] !== 'success'){
                    throw new Exception($quidaxWallet['message']);
                }
                
                // Calculate fee
                $feeResponse = $this->quidaxService->getWithdrawalFee(strtolower($sourceCurrency), strtolower($network));
                $feeAmount = $feeResponse['data']['fee'];
                $feeType = $feeResponse['data']['type'];
                
                if($feeType != 'flat'){
                    $feeAmount = $sourceAmount * ($feeAmount / 100);
                }
                $feeAmount = $feeAmount * 2; // Platform charges 2x the Quidax fee
                
                $totalAmount = $sourceAmount + $feeAmount;
                $balance = $quidaxWallet['data']['balance'];
                
                if($balance < $totalAmount){
                    throw new Exception("Insufficient balance. Required: $totalAmount $sourceCurrency (including $feeAmount $sourceCurrency fee) but available: $balance $sourceCurrency");
                }
                
                // Debit local wallet for consistency
                if ($wallet && $wallet->balance >= $sourceAmount) {
                    $wallet->balance -= $sourceAmount;
                    $wallet->save();
                }
                
                // Construct Main Account Data
                $mainAccountId = $this->quidaxService->getUser()['data']['id'];
                $mainAccountData = [
                    'currency' => strtolower($sourceCurrency),
                    'network' => strtolower($network),
                    'amount' => $totalAmount,
                    'fund_uid' => $mainAccountId,
                    'transaction_note' => "Trading $sourceCurrency to $targetCurrency",
                    'narration' => "Trading $sourceCurrency to $targetCurrency",
                ];
                
                // Create Transaction Record (Pending)
                $transaction = BushaTransaction::create([
                    'id' => $reference,
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'busha_order_id' => null, // Will be updated by job
                    'type' => $request->side,
                    'pair' => $sourceCurrency . '-' . $targetCurrency,
                    'amount' => ($request->side === 'buy' ? $targetAmount : $sourceAmount),
                    'total' => ($request->side === 'buy' ? $sourceAmount : $targetAmount),
                    'rate' => ($sourceAmount > 0 ? $targetAmount / $sourceAmount : 0),
                    'status' => 'pending',
                    'metadata' => ['quote' => $quoteDetails],
                ]);
                
                // Dispatch Job
                \App\Jobs\ProcessBushaSell::dispatch(
                    $user, 
                    $request->quote_id, 
                    $reference, 
                    $mainAccountData, 
                    $sourceCurrency, 
                    $targetCurrency, 
                    $sourceAmount, 
                    $totalAmount
                );
                
                // Notify User
                $user->notify(new BushaTradeNotification($transaction));
                
                DB::commit();
                //  return Response::successResponse($bushaResponse['message'], $data);
                return Response::successResponse('Trade initiated successfully. Status is processing.',[]);
                
            } else {
                // BUY: User buys crypto with fiat (Synchronous)
                if (!$wallet || $wallet->balance < $sourceAmount) {
                    throw new Exception("Insufficient $sourceCurrency balance. Required: $sourceAmount");
                }
                
                // Debit the user
                $wallet->balance -= $sourceAmount;
                $wallet->save();
                
                // Execute Transfer on Busha
                $transfer = $this->bushaService->executeQuote($request->quote_id, $reference);
                $pay_in = $transfer['data']['pay_in'];
                
                // Transfer NGN to Busha bank account
                $expires_at = $pay_in['expires_at'];
                $recipient_details = $pay_in['recipient_details'];
                
                $account_number = $recipient_details['account_number'];
                $bank_code = $recipient_details['bank_code'];
                
                // Name Enquiry
                $enquiry = $this->safehavenService->nameEnquiry($bank_code, $account_number);
                $sessionId = $enquiry['sessionId'] ?? $enquiry['data']['sessionId'] ?? null;
                
                if (!$sessionId) {
                    throw new Exception("Failed to verify payment account.");
                }
                
                // Get User Debit Account
                $debitAccountNumber = optional($this->safehavenService->getUserSubAccount($user))->account_number;
                if (!$debitAccountNumber) {
                    throw new Exception("User does not have a SafeHaven account to debit.");
                }
                
                // Transfer
                $transferResponse = $this->safehavenService->transfer([
                    "saveBeneficiary" => false,
                    "nameEnquiryReference" => $sessionId,
                    "debitAccountNumber" => $debitAccountNumber,
                    "beneficiaryBankCode" => $bank_code,
                    "beneficiaryAccountNumber" => $account_number,
                    "amount" => (float)$sourceAmount,
                    "narration" => "Trading $targetCurrency to $sourceCurrency",
                    "paymentReference" => "busha_trade_" . Str::random(12)
                ]);
                
                // Record Transaction
                $transaction = BushaTransaction::create([
                    'id' => $reference,
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'busha_order_id' => $transfer['id'] ?? null,
                    'type' => $request->side,
                    'pair' => $sourceCurrency . '-' . $targetCurrency,
                    'amount' => ($request->side === 'buy' ? $targetAmount : $sourceAmount),
                    'total' => ($request->side === 'buy' ? $sourceAmount : $targetAmount),
                    'rate' => ($sourceAmount > 0 ? $targetAmount / $sourceAmount : 0),
                    'status' => 'pending',
                    'metadata' => array_merge($transfer, ['quote' => $quoteDetails]),
                ]);

                // Notify User
                $user->notify(new BushaTradeNotification($transaction));
                
                DB::commit();
                return Response::successResponse('Trade executed successfully', $transfer['data']);
            }

        } catch (Exception $e) {
            DB::rollBack();
            return Response::errorResponse($e->getMessage());
        }
    }
    public function getTransfer($id) {
        // If it looks like a Quote ID, try to find the Transfer ID from our records
        if (Str::startsWith($id, 'QUO_')) {
            $transaction = BushaTransaction::where('metadata->quote->id', $id)->first();
            
            if (!$transaction) {
                // Fallback: Maybe it's in the root of metadata (depending on how it was saved previously)
                // or maybe the user hasn't executed it yet.
                return Response::errorResponse('Transaction not found for this Quote ID. Please ensure the trade was executed.');
            }
            
            if (!$transaction->busha_order_id) {
                return Response::errorResponse('Trade found but no Transfer ID recorded.');
            }
            
            $id = $transaction->busha_order_id;
        }

        try {
            $transfer = $this->bushaService->getTransfer($id);
            return Response::successResponse('Transfer fetched', $transfer['data']);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    public function banks() {
        try {
            $banks = $this->bushaService->getBanks();
            return Response::successResponse('Banks fetched', ['banks' => $banks]);
        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }
    
    public function history() {
        $history = BushaTransaction::where('user_id', auth()->id())->orderByDesc('created_at')->paginate(20);
        return Response::successResponse('History fetched', ['transactions' => $history]);
    }

    /**
     * Add a new Bank Account for Payouts
     */
    public function addBankAccount(Request $request) {
        // return auth()->user()->busha_recipient_id;
        $validator = Validator::make($request->all(), [
            'bank_name'      => 'required|string',
            'bank_code'      => 'required|string',
            'account_number' => 'required|string',
            'account_name'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->all());
        }

        try {
            $user = auth()->user();

            // 1. Create Recipient on Busha
            $recipientData = $this->bushaService->createPayoutRecipient([
                'bank_name'      => $request->bank_name,
                'bank_code'      => $request->bank_code,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name
            ]);

            $recipientId = $recipientData['data']['id'] ?? $recipientData['id'] ?? null;

            if (!$recipientId) {
                return Response::errorResponse('Failed to generate recipient ID from Busha.');
            }

            $user->busha_recipient_id = $recipientId;
            $user->save();
            // 2. Save to DB
            $account = BushaPaymentDetail::create([
                'user_id'        => $user->id,
                'bank_name'      => $request->bank_name,
                'bank_code'      => $request->bank_code,
                'account_number' => $request->account_number,
                'account_name'   => $request->account_name,
                'recipient_id'   => $recipientId,
                'currency'       => 'NGN',
                'is_default'     => BushaPaymentDetail::where('user_id', $user->id)->count() === 0 // Make default if first one
            ]);

            return Response::successResponse('Bank account added successfully', ['account' => $account]);

        } catch (Exception $e) {
            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * Get Saved Bank Accounts
     */
    public function getBankAccounts() {
        $accounts = BushaPaymentDetail::where('user_id', auth()->id())->first();
        if(!$accounts){
            return Response::errorResponse('Bank account not found');
        }
        return Response::successResponse('Bank accounts fetched', $accounts);
    }

    /**
     * Delete Bank Account
     */
    public function deleteBankAccount($id) {
        $account = BushaPaymentDetail::where('id', $id)->where('user_id', auth()->id())->first();
        if (!$account) {
            return Response::errorResponse('Bank account not found');
        }
        $account->delete();
        return Response::successResponse('Bank account deleted successfully');
    }


    public function getCurrencies(Request $request) {
        $validator = Validator::make($request->all(), [
            'side' => 'required|in:buy,sell',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse("Invalid side", $validator->errors()->all());
        }

        $side = $request->side;
        if($side == 'buy'){
            $side = 'is_ramp_buy_supported';
        }else{
            $side = 'is_ramp_sell_supported';
        }

        $response = $this->bushaService->getCurrencies();
        $currencies = [];
        foreach ($response['data'] as $key => $value) {
            if($value[$side] == true){
                $currencies[$key] = $value;
            }
        }
        
        return Response::successResponse($response['message'],  $currencies);
    }

    public function getNetworks(Request $request) {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse("Invalid currency", $validator->errors()->all());
        }

        $response = $this->bushaService->getNetworks($request->currency);
        return Response::successResponse($response['message'],  $response['data']['supported_networks']);
    }

    /**
     * Normalize network name from Busha/Quidax format to standard format
     */
    private function normalizeNetwork($network) {
        return match($network) {
            'BSC' => 'BEP20',
            'TRX' => 'TRC20',
            'Ethereum' => 'ERC20',
            default => $network
        };
    }

}
