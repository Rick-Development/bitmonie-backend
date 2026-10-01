<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BushaTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\UserWallet;
use App\Models\User;
use App\Notifications\User\BushaTradeNotification;

class BushaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Verify Signature
        $signature = $request->header('X-BC-Signature');
        $secret = config('services.busha.webhook_secret');

        if (!$this->verifySignature($request->getContent(), $signature, $secret)) {
            Log::warning('Busha webhook signature verification failed.');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('Busha Webhook Received: ' . $event, $payload);

        if ($event === 'order.updated') {
            $this->handleOrderUpdate($data);
        }

        return response()->json(['message' => 'Webhook received']);
    }

    protected function verifySignature($payload, $signature, $secret)
    {
        if (empty($signature) || empty($secret)) {
            return false;
        }

        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }

    protected function handleOrderUpdate($data)
    {
        $reference = $data['client_order_id'] ?? null;
        $status = $data['status'] ?? null; // filled, cancelled, etc.
        
        if (!$reference) return;

        $transaction = BushaTransaction::where('reference', $reference)->first();
        if (!$transaction) return;

        if ($transaction->status === 'successful' || $transaction->status === 'failed') {
            return; // Already processed
        }

        // Logic for "filled" (successful)
        if ($status === 'filled') {
            $transaction->status = 'successful';
            $transaction->save();
            
            // If it was a BUY order:
            // We already debited NGN. Now we Credit Crypto.
            if ($transaction->type === 'buy') {
                 // Credit Crypto Wallet Logic
                 $this->creditWallet($transaction->user_id, explode('-', $transaction->pair)[0], $transaction->amount);
            }
            // If it was a SELL order:
            // We debited Crypto (conceptually). Now we Credit NGN.
            if ($transaction->type === 'sell') {
                 $this->creditWallet($transaction->user_id, explode('-', $transaction->pair)[1], $transaction->total);
            }

            // Notify User
            $user = User::find($transaction->user_id);
            if ($user) {
                $user->notify(new BushaTradeNotification($transaction));
            }

        } 
        
        // Logic for "cancelled" or "failed"
        elseif ($status === 'cancelled' || $status === 'failed') {
            $transaction->status = 'failed';
            $transaction->save();

            // Refund logic
            if ($transaction->type === 'buy') {
                // Refund NGN
                $this->creditWallet($transaction->user_id, explode('-', $transaction->pair)[1], $transaction->total);
            }
            if ($transaction->type === 'sell') {
                // Refund Crypto (if execution involved debiting crypto balance first)
                 $this->creditWallet($transaction->user_id, explode('-', $transaction->pair)[0], $transaction->amount);
            }

            // Notify User
            $user = User::find($transaction->user_id);
            if ($user) {
                $user->notify(new BushaTradeNotification($transaction));
            }
        }
    }

    protected function creditWallet($userId, $currencyCode, $amount) {
        // Find wallet by currency code and credit it
        $wallet = UserWallet::where('user_id', $userId)->whereHas('currency', function($q) use ($currencyCode) {
            $q->where('code', $currencyCode);
        })->first();

        if ($wallet) {
            $wallet->balance += $amount;
            $wallet->save();
        } else {
             Log::error("Wallet not found for user $userId currency $currencyCode to credit $amount");
        }
    }
}
