<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\CreateCardHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\BasicControl;
use App\Models\PayscribeVirtualCardDetails;
use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use App\Models\VirtualCardSetting;
use App\Traits\Notify;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\KycVerification;

class PayscribeCreateCardController extends Controller
{
    use Notify;

    private $modelPath = 'PayscribeCardIssuing';

    public function __construct(private CreateCardHelper $createCardHelper, private PayscribeBalanceHelper $payscribeBalanceHelper)
    {
    }

    public function createCard(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'customer_id' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'brand' => 'required|string',
            'amount' => 'required',
            'type' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only(
            [
                'customer_id',
                "currency",
                "brand",
                "amount",
                "type",
            ]
        );

        $brand = strtolower(trim($data['brand'] ?? ''));

        // Reject domestic card schemes for USD Virtual Cards
        if (in_array($brand, ['verve', 'afrigo'])) {
            return Response::errorResponse(
                ucfirst($brand) . ' cards are not supported for USD Virtual Cards. For NGN Domestic Cards, please use Crypto Cards.',
                [],
                422
            );
        }

        if (!in_array($brand, ['visa', 'mastercard'])) {
            return Response::errorResponse(
                'Invalid card brand: ' . ($data['brand'] ?? '') . '. Supported brands are Visa and Mastercard.',
                [],
                422
            );
        }

        try {
            $settings = VirtualCardSetting::getSettings();
            if (!$settings->card_creation_enabled) {
                return Response::errorResponse('Virtual card creation is temporarily disabled. Please check back later.', [], 400);
            }
            if ($brand === 'visa' && !$settings->visa_enabled) {
                return Response::errorResponse('Visa virtual cards are currently unavailable. Please check back later or use Crypto Cards.', [], 400);
            }
            if ($brand === 'mastercard' && !$settings->mastercard_enabled) {
                return Response::errorResponse('Mastercard virtual cards are currently unavailable. Please check back later or use Crypto Cards.', [], 400);
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not load VirtualCardSetting in createCard: ' . $e->getMessage());
        }

        $user = auth()->user();

        // $kycVerification = KycVerification::where("user_id", $user->id)->first();
       
        if($user->payscribe_tier != 2){
            return Response::errorResponse("Kindly Confirm your kyc to proceed!!!");
        }

        $user_as_card = PayscribeVirtualCardDetails::where('user_id', $user['id'])->value('card_id');
        if ($user_as_card) {
            return Response::errorResponse("Customer card Exist");
        }

        // Validate the user's Quidax USDT balance before proceeding
        $cardIssuingRate = (int) BasicControl::first()->card_issuing_rate;
        $cardDepositRate = (int) BasicControl::first()->card_deposit_rate;
        $depositAmountNgn = $data['amount'] * $cardDepositRate;

        // Total equivalent in USDT = requested USD amount + (issuing rate converted to USD)
        $usdtToDeduct = $data['amount'] + ($cardIssuingRate / ($cardDepositRate > 0 ? $cardDepositRate : 1));

        $quidaxService = new \App\Services\QuidaxService();
        $quidaxWalletResponse = $quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        $walletData = $quidaxWalletResponse['data'] ?? [];
        if (isset($walletData[0]) && is_array($walletData[0])) {
            $walletData = $walletData[0];
        }
        if (isset($quidaxWalletResponse['status']) && $quidaxWalletResponse['status'] === 'success') {
            $quidaxBalance = (float) ($walletData['balance'] ?? 0);
        }

        if ($quidaxBalance < $usdtToDeduct) {
            return Response::errorResponse('Insufficient USDT balance to perform this action. Required: ' . round($usdtToDeduct, 2) . ' USDT, Available: ' . round($quidaxBalance, 2) . ' USDT');
        }

        $data['customer_id'] = auth()->user()->payscribe_customer_id;
        // Generate a UUID
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-cardIssuing';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        $rawResponse = $this->createCardHelper->createCard($data);
        $response = json_decode($rawResponse, true);

        if (!is_array($response)) {
            \Log::error('Payscribe createCard invalid response: ' . $rawResponse);
            return Response::errorResponse('Card issuing service is temporarily unavailable. Please try again later or use Crypto Cards.', [], 503);
        }

        if (isset($response['status']) && $response['status'] === true) {
            // Debit from Quidax USDT
            $quidaxTransfer = $quidaxService->transferToEscrow($user->quidax_id, $usdtToDeduct, 'usdt', 'Payscribe card issuing');
            if (!isset($quidaxTransfer['status']) || $quidaxTransfer['status'] !== 'success') {
                return Response::errorResponse('Failed to deduct USDT from your Quidax wallet');
            }

            // Create a transaction record for the card issuing
            $this->cardIssuingTransaction($data, $response, $usdtToDeduct);
            $this->virtualCardDetails($response, $data);
            $params = [
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'transaction' => 'processing',
            ];
            $this->mail($user, 'VIRTUAL_CARD_APPLY', $params);

            return Response::successResponse('Card created successfully', $response);
        }

        $errorMessage = $response['description'] ?? $response['message'] ?? 'Failed to create card';
        if (preg_match('/(available at the moment|no available|not available)/i', $errorMessage)) {
            $errorMessage = ucfirst($brand ?: 'Virtual') . ' card creation is temporarily unavailable from the provider network. Please check back later or use Crypto Cards.';
        }

        return Response::errorResponse($errorMessage, $response['errors'] ?? [], 400);
    }


    public function virtualCardDetails($response, $request)
    {
        PayscribeVirtualCardDetails::create([
            'user_id' => auth()->user()->id,
            'card_id' => $response['message']['details']['card']['id'],
            'card_type' => $response['message']['details']['card']['card_type'],
            'currency' => $response['message']['details']['card']['currency'],
            'brand' => $response['message']['details']['card']['brand'],
            'card_name' => $response['message']['details']['card']['name'],
            'masked' => $response['message']['details']['card']['masked'],
            'card_number' => $response['message']['details']['card']['number'],
            'expiry_date' => $response['message']['details']['card']['expiry'],
            'ccv' => $response['message']['details']['card']['ccv'],
            'billing_address' => $response['message']['details']['card']['billing'],
            'trans_id' => $response['message']['details']['trans_id'],
            'ref' => $response['message']['details']['ref'],
            'balance' => $request['amount'],
        ]);
    }
    private function createTransaction($request, $response, $totalamount)
    {
        $transId = $response['message']['details']['trans_id'];
        Transaction::create([
            'transactional_type' => 'Card Issuing',
            'user_id' => auth()->user()->id,
            'amount' => $totalamount,
            'currency' => 'NGN',
            'trx_type' => '-',
            'remarks' => 'You have successfully funded your card with ' . $request['amount'] . ' USD',
            'trx_id' => $transId,
            'transaction_status' => 'proccessing',
        ]);
    }

    private function cardIssuingTransaction($request, $response, $totalamount)
    {
        $transId = $response['message']['details']['trans_id'];
        Transaction::create([
            'transactional_type' => 'Card Issuing',
            'user_id' => auth()->user()->id,
            'amount' => $totalamount,
            'currency' => 'USDT',
            'trx_type' => '+',
            'remarks' => 'Card Issuing at ' . $totalamount . ' USDT',
            'trx_id' => $transId,
            'ref_id' => $request['ref'],
            'transaction_status' => 'processing',
        ]);
    }



    public function cardIssuingRate()
    {
        $cardIssuingRate = BasicControl::first()->card_issuing_rate;
        return Response::successResponse('Card Issuing Rate', $cardIssuingRate);
    }

    public function cardDepositRate()
    {
        $cardDepositRate = BasicControl::first()->card_deposit_rate;
        return Response::successResponse('Card Issuing Rate', $cardDepositRate);
    }

    public function cardWithdrawalRate()
    {
        $cardWithdarwalRate = BasicControl::first()->card_withdrawal_rate;
        return Response::successResponse('Card Issuing Rate', $cardWithdarwalRate);
    }



    public function customerTransactions(string $cardId)
    {

        $transactions = PayscribeVirtualCardTransaction::where('card_id', $cardId)->paginate(10);
        return Response::successResponse('Card Transactions', $transactions);
    }

    public function customerCardDetails()
    {

        $transactions = PayscribeVirtualCardDetails::where('user_id', auth()->id())->paginate(10);
        return Response::successResponse('Card Details', $transactions);
    }
}
