<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\CreateCardHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\BasicControl;
use App\Models\PayscribeVirtualCardDetails;
use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use App\Traits\Notify;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $user = auth()->user();
        $user_as_card = PayscribeVirtualCardDetails::where('user_id', $user['id'])->value('card_id');
        if (!!$user_as_card) {
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
        $quidaxBalance = 0;
        if (isset($quidaxWalletResponse['status']) && $quidaxWalletResponse['status'] === 'success') {
            $quidaxBalance = $quidaxWalletResponse['data']['balance'];
        }

        if ($quidaxBalance < $usdtToDeduct) {
            return Response::errorResponse('Insufficient USDT balance to perform this action. Required: ' . round($usdtToDeduct, 2) . ' USDT');
        }

        $data['customer_id'] = auth()->user()->payscribe_customer_id;
        // Generate a UUID
        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-cardIssuing';
        $data = array_merge($data, ['ref' => $referenceIdString]);

        $response = json_decode($this->createCardHelper->createCard($data), true);

        if ($response['status'] === true) {
            // Debit from Quidax USDT
            $quidaxTransfer = $quidaxService->transferToEscrow($user->quidax_id, $usdtToDeduct, 'usdt');
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
        }

        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card created successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? $response['message'] ?? 'Failed to create card', $response['errors'] ?? []);
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
