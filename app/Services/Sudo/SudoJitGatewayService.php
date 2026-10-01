<?php

namespace App\Services\Sudo;

use App\Models\CryptoCardHolders;
use App\Models\CryptoCardsModel;
use App\Models\CryptoCardTransactions;
use App\Models\CryptoCardWebhookLog;
use App\Services\Sudo\Dto\AuthorizationDecision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SudoJitGatewayService
{
    private string $expectedToken;

    public function __construct()
    {
        $this->expectedToken = (string) config('services.sudo.jit_gateway_token');
    }

    public function verifyAuthorizationHeader(Request $request): bool
    {
        $header = $request->header('Authorization');

        if (blank($header) || blank($this->expectedToken)) {
            return false;
        }

        $token = Str::startsWith($header, 'Bearer ')
            ? Str::after($header, 'Bearer ')
            : $header;

        return hash_equals($this->expectedToken, $token);
    }

    /**
     * authorization.request
     */
    public function handleAuthorizationRequest(array $payload, Request $request): AuthorizationDecision
    {
        $startedAt = microtime(true);
        $eventId   = $payload['_id'] ?? null;
        $object    = data_get($payload, 'data.object', []);
        $pending   = data_get($object, 'pendingRequest', []);

        $providerCardId     = data_get($object, 'card._id');
        $providerCustomerId = data_get($object, 'customer._id');
        $amount             = (float) data_get($pending, 'amount', 0);
        $currency           = data_get($pending, 'currency', 'NGN');
        $merchantName       = data_get($object, 'merchant.name', 'Unknown');
        $channel            = data_get($object, 'transactionMetadata.channel');

        // 1. Find local card
        $card = CryptoCardsModel::where('card_provider', 'sudo')
            ->where('card_provider_id', $providerCardId)
            ->first();

        $userId = $card?->user_id;

        // 2. Idempotency
        if ($eventId && Cache::has($this->cacheKey($eventId))) {
            $this->logWebhook($payload, $request, $card, $userId, 'authorization.request', 'processed', '00', 'approved', $amount, $currency, $startedAt);
            return AuthorizationDecision::approve();
        }

        // 3. Make decision
        $decision = $this->decide($card, $amount, $currency, $merchantName, $channel, $object);

        // 4. Persist authorization as a pending transaction (optional but recommended)
        if ($card && $decision->approve) {
            $this->createPendingTransaction($card, $object, $amount, $currency);
        }

        // 5. Cache for idempotency
        if ($eventId) {
            Cache::put($this->cacheKey($eventId), true, now()->addMinutes(15));
        }

        // 6. Log everything
        $this->logWebhook(
            $payload,
            $request,
            $card,
            $userId,
            'authorization.request',
            'processed',
            $decision->responseCode,
            $decision->approve ? 'approved' : 'declined',
            $amount,
            $currency,
            $startedAt,
            $decision->message
        );

        return $decision;
    }

    /**
     * card.balance
     */
    public function handleBalanceRequest(array $payload, Request $request): array
    {
        $startedAt = microtime(true);
        $object    = data_get($payload, 'data.object', []);

        $providerCardId = data_get($object, '_id');
        $card = CryptoCardsModel::where('card_provider', 'sudo')
            ->where('card_provider_id', $providerCardId)
            ->first();

        $balance = $this->getSpendableBalance($card);

        $this->logWebhook(
            $payload,
            $request,
            $card,
            $card?->user_id,
            'card.balance',
            'processed',
            '00',
            null,
            null,
            null,
            $startedAt
        );

        return ['balance' => $balance];
    }

    /**
     * Core decision logic – keep this fast
     */
    protected function decide(
        ?CryptoCardsModel $card,
        float $amount,
        string $currency,
        string $merchant,
        ?string $channel,
        array $fullObject
    ): AuthorizationDecision {
        // Card must exist and be active
        if (!$card || $card->card_status !== 'active' || $card->is_deleted) {
            return AuthorizationDecision::decline('14', 'Invalid or inactive card');
        }

        // Check spending limits if you store them
        if ($card->spending_limits_amount && $amount > $card->spending_limits_amount) {
            // You may want more sophisticated daily/weekly tracking here
            return AuthorizationDecision::decline('61', 'Exceeds spending limit');
        }

        // Balance check (replace with your real wallet logic)
        $available = $this->getSpendableBalance($card);
        if ($available < $amount) {
            return AuthorizationDecision::decline('51', 'Insufficient funds');
        }

        // Optional: block certain merchants / categories
        // if ($this->isBlockedMerchant($merchant)) {
        //     return AuthorizationDecision::decline('57', 'Transaction not permitted');
        // }

        return AuthorizationDecision::approve([
            'internal_reference' => 'JIT-' . now()->format('YmdHis') . '-' . Str::random(6),
            'card_id'            => $card->id,
            'user_id'            => $card->user_id,
        ]);
    }

    /**
     * Get spendable balance for the card.
     * Replace this with your real wallet / ledger logic.
     */
   protected function getSpendableBalance(?CryptoCardsModel $card): float
{
    if (!$card) {
        return 0.0;
    }

    if (
        $card->card_status !== 'active' ||
        $card->is_deleted
    ) {
        return 0.0;
    }

    return max(
        0.0,
        (float) ($card->card_balance ?? 0)
    );
}    /**
     * Create a pending transaction record when we approve an authorization
     */
    protected function createPendingTransaction(
        CryptoCardsModel $card,
        array $object,
        float $amount,
        string $currency
    ): void {
        try {
            CryptoCardTransactions::create([
                'user_id'                   => $card->user_id,
                'crypto_card_id'            => $card->id,
                'card_provider'             => 'sudo',
                'provider_authorization_id' => $object['_id'] ?? null,
                'provider_card_id'          => $card->card_provider_id,
                'provider_customer_id'      => $card->card_provider_customer_id,
                'transaction_type'          => 'authorization',
                'transaction_status'        => 'pending',
                'entry_type'                => 'debit',
                'amount'                    => $amount,
                'currency'                  => $currency,
                'merchant_name'             => data_get($object, 'merchant.name'),
                'merchant_id'               => data_get($object, 'merchant.merchantId'),
                'merchant_category_code'    => data_get($object, 'merchant.category'),
                'merchant_city'             => data_get($object, 'merchant.city'),
                'merchant_country'          => data_get($object, 'merchant.country'),
                'channel'                   => data_get($object, 'transactionMetadata.channel'),
                'masked_pan'                => $card->masked_pan,
                'card_last_four'            => $card->card_last_four,
                'authorized_at'             => now(),
                'transaction_date'          => now(),
                'raw_response'              => $object,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create pending transaction from JIT', [
                'error' => $e->getMessage(),
                'card_id' => $card->id,
            ]);
        }
    }

    /**
     * Centralized webhook logging
     */
    protected function logWebhook(
        array $payload,
        Request $request,
        ?CryptoCardsModel $card,
        ?int $userId,
        string $eventType,
        string $status,
        ?string $responseCode,
        ?string $decision,
        ?float $amount,
        ?string $currency,
        float $startedAt,
        ?string $errorMessage = null
    ): void {
        try {
            CryptoCardWebhookLog::create([
                'provider'            => 'sudo',
                'event_type'          => $eventType,
                'event_id'            => $payload['_id'] ?? null,
                'provider_card_id'    => $card?->card_provider_id ?? data_get($payload, 'data.object.card._id') ?? data_get($payload, 'data.object._id'),
                'provider_customer_id'=> $card?->card_provider_customer_id,
                'crypto_card_id'      => $card?->id,
                'user_id'             => $userId,
                'direction'           => 'inbound',
                'status'              => $status,
                'response_code'       => $responseCode,
                'decision'            => $decision,
                'amount'              => $amount,
                'currency'            => $currency,
                'processing_time_ms'  => (int) round((microtime(true) - $startedAt) * 1000),
                'ip_address'          => $request->ip(),
                'payload'             => $payload,
                'error_message'       => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write webhook log', ['error' => $e->getMessage()]);
        }
    }

    private function cacheKey(string $eventId): string
    {
        return "sudo:jit:auth:{$eventId}";
    }
}