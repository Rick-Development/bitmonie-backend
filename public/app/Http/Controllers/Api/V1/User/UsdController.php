<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Response;
use App\Models\GraphTransaction;
use App\Models\GraphWallet;
use App\Services\GraphService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use App\Traits\PinValidationTrait;

class UsdController extends Controller
{
    use PinValidationTrait;
    protected GraphService $graphService;

    public function __construct(GraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    /**
     * POST /api/usd/receive
     *
     * Sandbox: simulate a deposit into the user's USD wallet.
     * Production: return the user's actual USD funding account instructions.
     */
    public function receive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:USD',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();
            $amount = (float) $request->amount;
            $currency = strtoupper((string) $request->currency);

            $wallet = GraphWallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->first();

            if (!$wallet) {
                return Response::errorResponse(
                    'You do not have a USD wallet. Please create one first via /api/user/graph/create-wallet.',
                    null,
                    404
                );
            }

            $appMode = env('APP_MODE', 'live');

            if ($appMode === 'sandbox' || $appMode === 'local' || config('app.env') === 'local') {
                $result = $this->graphService->mockDeposit($wallet->wallet_id, $amount, $currency);
                $this->graphService->updateWalletBalance($wallet->wallet_id);
                $wallet->refresh();

                $reference = 'USD_RCV_' . Str::upper(Str::random(10)) . '_' . $user->id;
                GraphTransaction::updateOrCreate(
                    ['transaction_id' => $result['data']['deposit_id'] ?? $result['data']['id'] ?? $reference],
                    [
                        'user_id' => $user->id,
                        'graph_wallet_id' => $wallet->id,
                        'type' => 'deposit',
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'completed',
                        'reference' => $reference,
                        'description' => "USD deposit of {$amount} {$currency}",
                        'metadata' => $result,
                    ]
                );

                Log::info('USD receive simulated in sandbox', [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->wallet_id,
                    'amount' => $amount,
                ]);

                return Response::successResponse('USD deposit received successfully', [
                    'wallet_id' => $wallet->wallet_id,
                    'amount_credited' => $amount,
                    'currency' => $currency,
                    'balance_after' => (float) $wallet->balance,
                    'reference' => $reference,
                    'receive_method' => 'mock_deposit',
                    'mode' => 'sandbox',
                ]);
            }

            $reference = 'USD_RCV_INFO_' . Str::upper(Str::random(8)) . '_' . $user->id;
            $bankAccount = $this->graphService->getReceiveInstructions($wallet->wallet_id);

            Log::info('USD receive instructions fetched', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->wallet_id,
            ]);

            return Response::successResponse('USD receiving instructions fetched successfully', [
                'wallet_id' => $wallet->wallet_id,
                'currency' => $currency,
                'expected_amount' => $amount,
                'bank_account' => $bankAccount,
                'deposit_address' => null,
                'reference' => $reference,
                'receive_method' => 'bank_transfer',
                'instructions' => [
                    'Send the USD transfer to the funding account shown below.',
                    'Use the routing number exactly as provided by Graph/Oval.',
                    'The wallet balance updates after Graph confirms the incoming transfer.',
                ],
                'funding_options' => [
                    'bank_transfer' => [
                        'available' => true,
                        'type' => 'usd_bank_account',
                    ],
                    'stablecoin' => [
                        'available' => false,
                        'reason' => 'User-level automatic stablecoin allocation is not enabled in this flow. Use the USD bank funding account for production receive.',
                    ],
                ],
                'mode' => 'production',
            ]);
        } catch (Exception $e) {
            Log::error('USD Receive error: ' . $e->getMessage(), ['user_id' => auth()->id()]);

            return Response::errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/usd/send
     *
     * Send USD from the user's Graph wallet to a saved payout destination.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'recipient' => 'required_without:destination_id|string',
            'destination_id' => 'required_without:recipient|string',
            'pin' => 'required|digits:4',
            'narration' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return Response::errorResponse($validator->errors()->first());
        }

        try {
            $user = auth()->user();

            if (!$this->validateTransactionPin($user, $request->pin)) {
                return Response::errorResponse('Invalid Transaction PIN', [], 403);
            }

            $amount = (float) $request->amount;
            $destinationId = $request->recipient ?: $request->destination_id;
            $description = $request->description ?? $request->narration ?? 'USD Transfer';

            $wallet = GraphWallet::where('user_id', $user->id)
                ->where('currency', 'USD')
                ->first();

            if (!$wallet) {
                return Response::errorResponse(
                    'You do not have a USD wallet. Please create one first via /api/user/graph/create-wallet.',
                    null,
                    404
                );
            }

            $this->graphService->updateWalletBalance($wallet->wallet_id);
            $wallet->refresh();

            if ((float) $wallet->balance < $amount) {
                return Response::errorResponse(
                    "Insufficient USD balance. Available: {$wallet->balance} USD, Required: {$amount} USD.",
                    ['available_balance' => (float) $wallet->balance],
                    422
                );
            }

            $reference = 'USD_SEND_' . Str::upper(Str::random(10)) . '_' . $user->id;
            $result = $this->graphService->createPayout($user, $wallet->wallet_id, [
                'destination_id' => $destinationId,
                'amount' => $amount,
                'currency' => 'USD',
                'reference' => $reference,
                'description' => $description,
            ]);

            $this->graphService->updateWalletBalance($wallet->wallet_id);
            $wallet->refresh();

            Log::info('USD send initiated', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->wallet_id,
                'destination_id' => $destinationId,
                'amount' => $amount,
                'reference' => $reference,
            ]);

            return Response::successResponse('USD transfer initiated successfully', [
                'reference' => $reference,
                'amount_sent' => $amount,
                'currency' => 'USD',
                'recipient' => $destinationId,
                'destination_id' => $destinationId,
                'balance_after' => (float) $wallet->balance,
                'status' => $result['data']['status'] ?? 'pending',
                'transaction_id' => $result['data']['payout_id'] ?? $result['data']['id'] ?? null,
                'transaction' => $result['data'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error('USD Send error: ' . $e->getMessage(), ['user_id' => auth()->id()]);

            return Response::errorResponse($e->getMessage());
        }
    }
}
