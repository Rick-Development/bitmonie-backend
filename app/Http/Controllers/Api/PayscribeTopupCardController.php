<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Payscribe\CardIssusing\TopupCardHelper;
use App\Models\PayscribeVirtualCardDetails;
use App\Models\PayscribeVirtualCardTransaction;
use App\Models\Transaction;
use App\Http\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayscribeTopupCardController extends Controller
{
    private $modelPath = 'PayscribeTopupCard';

    public function __construct(private TopupCardHelper $cardTopupHelper) {}

    public function topupCard(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.1',
            'card_id' => 'required|string',
        ]);

        $usdtToDeduct = $request->amount;
        $user = auth()->user();

        if (!$user->quidax_id) {
            return Response::errorResponse('User Quidax account is not configured.');
        }

        $quidaxService = new \App\Services\QuidaxService();
        
        try {
            // 1. Fetch Quidax Wallet Balance
            $quidaxWalletResponse = $quidaxService->fetchUserWallet($user->quidax_id, 'usdt');
            $quidaxBalance = 0;
            
            if (isset($quidaxWalletResponse['status']) && $quidaxWalletResponse['status'] === 'success') {
                $quidaxBalance = $quidaxWalletResponse['data']['balance'] ?? 0;
            }

            if ($quidaxBalance < $usdtToDeduct) {
                return Response::errorResponse('Insufficient USDT balance to perform this topup. Required: ' . round($usdtToDeduct, 2) . ' USDT');
            }

            // 2. Debit from Quidax USDT and transfer to Escrow FIRST before hitting the card provider
            $quidaxTransfer = $quidaxService->transferToEscrow($user->quidax_id, $usdtToDeduct, 'usdt');
            if (!isset($quidaxTransfer['status']) || $quidaxTransfer['status'] !== 'success') {
                return Response::errorResponse('Failed to deduct USDT from your Quidax wallet');
            }

            // 3. Prepare payload for External Topup
            $referenceId = Str::uuid();
            $referenceIdString = (string) $referenceId . '-cardtopup';
            $data = [
                'amount' => $usdtToDeduct,
                'ref' => $referenceIdString,
            ];
            $cardId = $request->card_id;

            // 4. Call Card Topup Helper
            $apiResponse = $this->cardTopupHelper->topupCard($data, $cardId);
            $response = json_decode($apiResponse, true);

            if (!isset($response['status']) || $response['status'] !== true) {
                // Note: If you need to refund escrow on failure, handle it here.
                return Response::errorResponse($response['description'] ?? 'Failed to topup card', $response);
            }

            // 5. Record transactions and update local state
            $this->createTransaction($data, $response, $usdtToDeduct);
            $this->cardDepositTransaction($data, $response);
            $this->virtualCardDetails($response);

            // Optional: Implement mail dispatch if notification class exists
            // $this->sendCardDepositEmail($usdtToDeduct, $response['message']['details']['card'], $response['message']['details']['trans_id']);

            return Response::successResponse('Card topped up successfully', $response);

        } catch (\Exception $e) {
            return Response::errorResponse('An error occurred during card topup: ' . $e->getMessage(), null, 500);
        }
    }

    private function createTransaction($request, $response, $depositAmount) {
        $transId = $response['message']['details']['trans_id'] ?? null;
        Transaction::create([
            'transactional_type' => 'Card Topup',
            'user_id' => auth()->id(),
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
        $details = $response['message']['details'] ?? [];
        $card = $details['card'] ?? [];

        PayscribeVirtualCardTransaction::create([
            'transactional_type' => 'Card Topup',
            'user_id' => auth()->id(),
            'card_id' => $card['id'] ?? null,
            'amount' => $request['amount'],
            'currency' => 'USD',
            'balance' => $card['balance'] ?? 0,
            'charge' => 0.0,
            'trx_type' => '+',
            'remarks' => $response['description'] ?? 'Card topup successful',
            'trx_id' => $details['trans_id'] ?? null,
            'ref' => $details['ref_id'] ?? $request['ref'],
            'event_id' => $details['event_id'] ?? null,
            'action' => $details['action'] ?? null,
        ]);
    }

    private function virtualCardDetails($response) {
        $details = $response['message']['details'] ?? [];
        $cardData = $details['card'] ?? [];
        
        if (!empty($cardData['id'])) {
            $card = PayscribeVirtualCardDetails::where('card_id', $cardData['id'])->first();
            if ($card) {
                $card->update([
                    'balance' => $cardData['balance'] ?? $card->balance,
                    'prev_balance' => $cardData['prev_balance'] ?? $card->prev_balance,
                    'updated_at' => $details['created_at'] ?? now(),
                ]);
            }
        }
    }
}