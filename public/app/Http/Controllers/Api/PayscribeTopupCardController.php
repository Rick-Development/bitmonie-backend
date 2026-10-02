<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\TopupCardHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\BasicControl;
use App\Models\PayscribeVirtualCardDetails;
use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeTopupCardController extends Controller
{

    private $modelPath = 'PayscribeTopupCard';

    public function __construct(private TopupCardHelper $cardTopupHelper, private PayscribeBalanceHelper $payscribeBalanceHelper){}

    public function topupCard(Request $request){
        $request->validate(
            [
                'amount' => 'required | numeric | min:0.1',
                'card_id' => 'required | string',
            ]
        );

        $usdtToDeduct = $request['amount'];
        $user = auth()->user();

        $quidaxService = new \App\Services\QuidaxService();
        $quidaxWalletResponse = $quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
        $quidaxBalance = 0;
        if (isset($quidaxWalletResponse['status']) && $quidaxWalletResponse['status'] === 'success') {
            $quidaxBalance = $quidaxWalletResponse['data']['balance'];
        }

        if ($quidaxBalance < $usdtToDeduct) {
            return Response::errorResponse('Insufficient USDT balance to perform this topup. Required: ' . round($usdtToDeduct, 2) . ' USDT');
        }

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-cardtopup';
        $data = [
            'amount' => $request['amount'],
            'ref' => $referenceIdString,
        ];
        $cardId = $request['card_id'];


        $response = json_decode($this->cardTopupHelper->topupCard($data, $cardId), true);

        if($response['status'] === true){
            // Debit from Quidax USDT and transfer to Escrow
            $quidaxTransfer = $quidaxService->transferToEscrow($user->quidax_id, $usdtToDeduct, 'usdt');
            if (!isset($quidaxTransfer['status']) || $quidaxTransfer['status'] !== 'success') {
                return Response::errorResponse('Failed to deduct USDT from your Quidax wallet');
            }

            // Create a transaction record for the card issuing
            $this->createTransaction($data, $response, $usdtToDeduct);

            $this->cardDepositTransaction($data, $response);
            $this->sendCardDepositEmail($request['amount'], $response['message']['details']['card'], $response['message']['details']['trans_id']);
        }

        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card topped up successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to topup card', $response);
    }


    private function createTransaction($request, $response, $depositAmount) {
        // $balance = auth()->user()->account_balance - $depositAmount;
        $transId = $response['message']['details']['trans_id'];
        Transaction::create([
            'transactional_type' => 'Card Topup',
            'user_id' => auth()->user()->id,
            'amount' => $depositAmount,
            'currency' => 'USDT',
            'trx_type' => '-',
            'remarks' => 'You have successfully funded your card with ' . $request['amount'] . ' USD',
            'trx_id' => $transId,
            'ref_id' => $request['ref'],
            'transaction_status' => 'processing',
        ]);
    }


    private function cardDepositTransaction($request, $response) {
        $balance = $response['message']['details']['card']['balance'];
        $transId = $response['message']['details']['trans_id'];
        $refId = $response['message']['details']['ref_id'];
        $cardId = $response['message']['details']['card']['id'];

        PayscribeVirtualCardTransaction::create([
            'transactional_type' => 'Card Topup',
            'user_id' => auth()->user()->id,
            'card_id' => $cardId,
            'amount' => $request['amount'],
            'currency' => 'USD',
            'balance' => $balance,
            'charge' => 0.0,
            'trx_type' => '+',
            'remarks' => $response['description'],
            'trx_id' => $transId,
            'ref' => $refId,
            'event_id' => $response['message']['details']['event_id'],
            'action' => $response['message']['details']['action'],
        ]);

    }

    private function virtualCardDetails($response, $request) {
        $cardId = $response['message']['details']['card']['id'];
        $card = PayscribeVirtualCardDetails::where('card_id', $cardId)->first();
        $card->update([
            'balance' => $response['message']['details']['card']['balance'],
            'prev_balance' => $response['message']['details']['card']['prev_balance'],
            'updated_at' => $response['message']['details']['created_at'],
        ]);
    }
}