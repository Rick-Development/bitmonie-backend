<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GraphCustomer;
use App\Models\GraphTransaction;
use App\Models\GraphWallet;
use App\Services\GraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GraphWebhookController extends Controller
{
    protected GraphService $graphService;

    public function __construct(GraphService $graphService)
    {
        $this->graphService = $graphService;
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-graph-signature')
            ?: $request->header('x-oval-signature')
            ?: $request->header('x-signature');
        $secret = config('graph.webhook_secret');

        if (!$this->verifySignature($payload, $signature, $secret)) {
            Log::warning('Graph webhook rejected due to invalid signature.');

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event_type') ?? $request->input('event');
        $data = $request->input('data', []);

        Log::info('Graph webhook received', [
            'event' => $event,
            'data' => $data,
        ]);

        try {
            switch ($event) {
                case 'account.credit':
                case 'deposit.successful':
                    $this->handleAccountCredit($data);
                    break;

                case 'payout.success':
                case 'payout.updated':
                case 'payout.failed':
                    $this->handlePayoutUpdate($event, $data);
                    break;

                case 'conversion.success':
                case 'conversion.failed':
                    $this->handleConversionUpdate($event, $data);
                    break;

                case 'kyc.verification.successful':
                case 'kyc.verification.failed':
                    $this->handleKycUpdate($event, $data);
                    break;

                default:
                    Log::info("Graph webhook event ignored: {$event}");
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Graph webhook processing failed: ' . $e->getMessage(), [
                'event' => $event,
                'data' => $data,
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    protected function verifySignature(string $payload, ?string $signature, ?string $secret): bool
    {
        if (empty($secret)) {
            Log::warning('Graph webhook secret is not configured; signature verification skipped.');

            return true;
        }

        if (empty($signature)) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }

    protected function handleAccountCredit(array $data): void
    {
        $walletId = $data['account_id']
            ?? $data['virtual_account_id']
            ?? data_get($data, 'bank_account.id');
        $transactionId = $data['id'] ?? $data['deposit_id'] ?? data_get($data, 'deposit.id');

        if (!$walletId || !$transactionId) {
            Log::warning('Graph account credit webhook missing wallet or transaction identifier.', ['data' => $data]);

            return;
        }

        $wallet = GraphWallet::where('wallet_id', $walletId)->first();

        if (!$wallet) {
            Log::warning("Graph webhook wallet not found for account ID {$walletId}.");

            return;
        }

        if (GraphTransaction::where('transaction_id', $transactionId)->exists()) {
            Log::info("Graph webhook duplicate account credit ignored for transaction {$transactionId}.");

            return;
        }

        $amount = $this->graphService->normalizeMoneyFromGraph($data['amount'] ?? data_get($data, 'deposit.amount') ?? 0);
        $balanceAfter = $data['balance_after'] ?? data_get($data, 'bank_account.balance');

        if ($balanceAfter !== null) {
            $wallet->balance = $this->graphService->normalizeMoneyFromGraph($balanceAfter);
        } else {
            $wallet->balance = (float) $wallet->balance + $amount;
        }

        if (!empty($data['currency'])) {
            $wallet->currency = strtoupper((string) $data['currency']);
        }

        $wallet->data = array_merge((array) $wallet->data, ['last_webhook_credit' => $data]);
        $wallet->save();

        GraphTransaction::create([
            'user_id' => $wallet->user_id,
            'graph_wallet_id' => $wallet->id,
            'transaction_id' => $transactionId,
            'type' => 'deposit',
            'amount' => $amount,
            'currency' => strtoupper((string) ($data['currency'] ?? $wallet->currency)),
            'status' => $this->graphService->normalizeTransactionStatus($data['status'] ?? 'successful'),
            'reference' => $data['custom_reference'] ?? $data['reference'] ?? data_get($data, 'deposit.id'),
            'description' => $data['description'] ?? 'USD account credit',
            'metadata' => $data,
        ]);

        Log::info("Graph account credit processed for wallet {$walletId}.", [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);
    }

    protected function handlePayoutUpdate(string $event, array $data): void
    {
        $candidateIds = array_filter([
            $data['payout_id'] ?? null,
            $data['id'] ?? null,
            $data['custom_reference'] ?? null,
        ]);

        $transaction = GraphTransaction::whereIn('transaction_id', $candidateIds)
            ->orWhere(function ($query) use ($candidateIds) {
                $query->whereIn('reference', $candidateIds);
            })
            ->first();

        if (!$transaction) {
            Log::warning('Graph payout webhook could not match a local transaction.', [
                'event' => $event,
                'candidate_ids' => $candidateIds,
            ]);

            return;
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $metadata['last_payout_webhook'] = [
            'event' => $event,
            'data' => $data,
            'received_at' => now()->toIso8601String(),
        ];

        $transaction->update([
            'status' => $this->graphService->normalizeTransactionStatus($data['status'] ?? ($event === 'payout.failed' ? 'failed' : 'successful')),
            'metadata' => $metadata,
        ]);

        $walletId = $data['account_id'] ?? null;

        if ($walletId) {
            $wallet = GraphWallet::where('wallet_id', $walletId)->first();
            if ($wallet && array_key_exists('balance_after', $data)) {
                $wallet->update([
                    'balance' => $this->graphService->normalizeMoneyFromGraph($data['balance_after']),
                    'data' => array_merge((array) $wallet->data, ['last_payout_webhook' => $data]),
                ]);
            }
        }

        Log::info('Graph payout webhook applied to local transaction.', [
            'transaction_id' => $transaction->transaction_id,
            'event' => $event,
        ]);
    }

    protected function handleConversionUpdate(string $event, array $data): void
    {
        $candidateIds = array_filter([
            $data['conversion_id'] ?? null,
            $data['id'] ?? null,
        ]);

        $transaction = GraphTransaction::whereIn('transaction_id', $candidateIds)->first();

        if (!$transaction) {
            return;
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $metadata['last_conversion_webhook'] = [
            'event' => $event,
            'data' => $data,
            'received_at' => now()->toIso8601String(),
        ];

        $transaction->update([
            'status' => $this->graphService->normalizeTransactionStatus($data['status'] ?? ($event === 'conversion.failed' ? 'failed' : 'successful')),
            'metadata' => $metadata,
        ]);
    }

    protected function handleKycUpdate(string $event, array $data): void
    {
        $personId = $data['person_id'] ?? $data['id'] ?? null;

        if (!$personId) {
            return;
        }

        $customer = GraphCustomer::where('graph_id', $personId)->first();

        if (!$customer) {
            Log::warning("Graph KYC webhook customer not found for Graph ID {$personId}.");

            return;
        }

        $status = $event === 'kyc.verification.successful' ? 'verified' : 'failed';
        $dataPayload = is_array($customer->data) ? $customer->data : [];
        $dataPayload['kyc_update'] = $data;

        $customer->update([
            'kyc_status' => $status,
            'data' => $dataPayload,
        ]);
    }
}
