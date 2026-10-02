<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\WithdrawFromCardHelper;
use App\Http\Helpers\Payscribe\PayscribeBalanceHelper;
use App\Models\BasicControl;
use App\Models\PayscribeVirtualCardDetails;
use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeWithdrawFromCardController extends Controller
{
    public function __construct(private WithdrawFromCardHelper $withdrawFromCardHelper, private PayscribeBalanceHelper $payscribeBalanceHelper){}
    public function withdraw(Request $request){
        $request->validate(
            [
                'amount' => 'required | numeric | min:0.1',
                'card_id' => 'required | string',
            ]
        );
        $cardId = $request['card_id'];

        $usdtToAdd = $request['amount'];

        $referenceId = Str::uuid();
        $referenceIdString = (string) $referenceId . '-cardwithdrawal';
        $data = [
            'amount' => $request['amount'],
            'ref' => $referenceIdString,
        ];
        $response = json_decode($this->withdrawFromCardHelper->withdrawFromCard($data, $cardId), true);

        if($response['status'] === true){
            $user = auth()->user();
            $quidaxService = new \App\Services\QuidaxService();
            // Move USDT from Escrow back to user's Quidax Wallet
            $quidaxTransfer = $quidaxService->fundSubAccount($user->quidax_id, $usdtToAdd, 'usdt');
            
            if (!isset($quidaxTransfer['status']) || $quidaxTransfer['status'] !== 'success') {
                return Response::errorResponse('Failed to credit USDT to your Quidax wallet');
            }

            $this->createTransaction($data, $response, $usdtToAdd);

            $this->cardWithdrawalTransaction($data, $response);
            $this->sendCardWithdrawalEmail($request['amount'], $response['message']['details']['card'], $response['message']['details']['trans_id']);
        }
        if (isset($response['status']) && $response['status'] === true) {
            return Response::successResponse('Card withdrawal successfully', $response);
        }
        return Response::errorResponse($response['description'] ?? 'Failed to withdraw from card', $response);
    }

    private function createTransaction($request, $response, $usdtToAdd) {
        $transId = $response['message']['details']['trans_id'];
        Transaction::create([
            'transactional_type' => 'Card Withdrawal',
            'user_id' => auth()->user()->id,
            'amount' => $usdtToAdd,
            'currency' => 'USDT',
            'trx_type' => '+',
            'remarks' => 'You have successfully credited your wallet with ' . $usdtToAdd . ' USDT',
            'trx_id' => $transId,
            'ref_id' => $request['ref'],
            'transaction_status' => 'processing',
        ]);

    }


    private function cardWithdrawalTransaction($request, $response) {
        $balance = $response['message']['details']['card']['balance'];
        $transId = $response['message']['details']['trans_id'];
        $refId = $response['message']['details']['ref_id'];
        $cardId = $response['message']['details']['card']['id'];
        PayscribeVirtualCardTransaction::create([
            'transactional_type' => 'Card Withdrawal',
            'user_id' => auth()->user()->id,
            'card_id' => $cardId,
            'amount' => $request['amount'],
            'currency' => 'USD',
            'balance' => $balance,
            'charge' => 0.0,
            'trx_type' => '-',
            'remarks' => $response['description'],
            'trx_id' => $transId,
            'ref' => $refId,
            'event_id' => $response['message']['details']['event_id'],
            'action' => $response['message']['details']['action'],
        ]);

    }
}