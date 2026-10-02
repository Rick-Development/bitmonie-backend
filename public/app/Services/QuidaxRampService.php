<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Throwable;

class QuidaxRampService
{
    protected string $baseUrl;
    protected string $privateKey;

    public function __construct()
    {
        $this->baseUrl    = config('services.quidax_ramp.base_url', 'https://ramp-be.quidax.io/api/v1');
        $this->privateKey = config('services.quidax_ramp.private_key', '');
    }

    // -------------------------------------------------------------------------
    // HTTP Client
    // -------------------------------------------------------------------------

    protected function client()
    {
        return Http::withHeaders([
            'x-private-key' => $this->privateKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout((int) config('services.quidax_ramp.timeout', 20));
    }

    protected function sendRequest(string $method, string $endpoint, array $payload = []): array
    {
        $requestId = (string) Str::uuid();
        $logContext = [
            'request_id' => $requestId,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'payload' => $this->sanitizeForLogs($payload),
        ];

        Log::info('Quidax ramp request', $logContext);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $this->client()->get("{$this->baseUrl}/{$endpoint}", $payload),
                'PUT' => $this->client()->put("{$this->baseUrl}/{$endpoint}", $payload),
                default => $this->client()->post("{$this->baseUrl}/{$endpoint}", $payload),
            };
        } catch (Throwable $exception) {
            Log::error('Quidax ramp transport error', array_merge($logContext, [
                'error' => $exception->getMessage(),
            ]));

            return [
                'ok' => false,
                'status' => 'service_unavailable',
                'message' => $exception->getMessage(),
                'data' => null,
                'http_status' => 503,
                'raw' => null,
            ];
        }

        $normalized = $this->normalizeResponse($response);
        $responseContext = array_merge($logContext, [
            'http_status' => $normalized['http_status'] ?? $response->status(),
            'status' => $normalized['status'] ?? null,
            'message' => $normalized['message'] ?? null,
            'data' => $this->sanitizeForLogs($normalized['data'] ?? null),
            'raw' => $this->sanitizeForLogs($normalized['raw'] ?? null),
        ]);

        if ($normalized['ok'] ?? false) {
            Log::info('Quidax ramp response', $responseContext);
        } else {
            Log::warning('Quidax ramp response', $responseContext);
        }

        return $normalized;
    }

    protected function normalizeResponse(HttpResponse $response): array
    {
        $body = $response->json();

        if (!is_array($body)) {
            $body = [];
        }

        $status = $this->normalizeStatus($body['status'] ?? null, $response->status());
        $data = $body['data'] ?? null;

        if ($data === null && !$response->successful()) {
            $data = $body['errors'] ?? $body['error'] ?? $body;
        }

        $message = $this->extractMessage($body['message'] ?? null)
            ?? $this->extractMessage($body['error'] ?? null)
            ?? $this->extractMessage($body['errors'] ?? null)
            ?? $response->reason();

        return [
            'ok' => $response->successful() && in_array($status, ['ok', 'success'], true),
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'http_status' => $response->status(),
            'raw' => $body,
        ];
    }

    protected function normalizeStatus(?string $status, int $httpStatus): string
    {
        if (is_string($status) && $status !== '') {
            return strtolower(str_replace([' ', '-'], '_', $status));
        }

        return match (true) {
            $httpStatus >= 200 && $httpStatus < 300 => 'ok',
            $httpStatus === 400 => 'bad_request',
            $httpStatus === 401 => 'unauthorized',
            $httpStatus === 403 => 'forbidden',
            $httpStatus === 404 => 'not_found',
            $httpStatus === 409 => 'conflict',
            $httpStatus === 422 => 'unprocessable_entity',
            $httpStatus >= 500 => 'server_error',
            default => 'error',
        };
    }

    // =========================================================================
    // ON-RAMP (NGN → Crypto)
    // =========================================================================

    /**
     * Initiate an on-ramp transaction.
     * POST /merchants/custodial/on_ramp_transactions/initiate
     *
     * @param array $payload {
     *   from_currency, to_currency, from_amount, merchant_reference,
     *   customer: { email, first_name, last_name },
     *   wallet_address: { address, network }
     * }
     */
    public function initiateOnRamp(array $payload): array
    {
        return $this->sendRequest('POST', 'merchants/custodial/on_ramp_transactions/initiate', $payload);
    }

    public function onRampTransaction(string $merchantReference): array
    {
        return $this->sendRequest('GET', "merchants/custodial/on_ramp_transactions/{$merchantReference}");
    }

    /**
     * Refresh an on-ramp transaction (update amount / rate).
     * PUT /merchants/custodial/on_ramp_transactions/{merchant_reference}/refresh
     *
     * @param string $merchantReference
     * @param array  $payload { from_currency, to_currency, from_amount }
     */
    public function refreshOnRamp(string $merchantReference, array $payload): array
    {
        return $this->sendRequest('PUT', "merchants/custodial/on_ramp_transactions/{$merchantReference}/refresh", $payload);
    }

    /**
     * Confirm an on-ramp transaction — generates the bank account for fiat deposit.
     * POST /merchants/custodial/on_ramp_transactions/{merchant_reference}/confirm
     *
     * @param string $merchantReference
     */
    public function confirmOnRamp(string $merchantReference): array
    {
        return $this->sendRequest('POST', "merchants/custodial/on_ramp_transactions/{$merchantReference}/confirm");
    }

    // =========================================================================
    // OFF-RAMP (Crypto → NGN)
    // =========================================================================

    /**
     * Initiate an off-ramp transaction.
     * POST /merchants/custodial/off_ramp_transactions/initiate
     *
     * @param array $payload {
     *   from_currency, to_currency, from_amount, network, merchant_reference,
     *   customer: { email, first_name, last_name }
     * }
     */
    public function initiateOffRamp(array $payload): array
    {
        return $this->sendRequest('POST', 'merchants/custodial/off_ramp_transactions/initiate', $payload);
    }

    /**
     * Refresh an off-ramp transaction.
     * PUT /merchants/custodial/off_ramp_transactions/{merchant_reference}/refresh
     *
     * @param string $merchantReference
     * @param array  $payload { from_currency, to_currency, from_amount, network }
     */
    public function refreshOffRamp(string $merchantReference, array $payload): array
    {
        return $this->sendRequest('PUT', "merchants/custodial/off_ramp_transactions/{$merchantReference}/refresh", $payload);
    }

    /**
     * Add a bank account to an off-ramp transaction.
     * POST /merchants/custodial/off_ramp_transactions/{merchant_reference}/bank_account
     *
     * @param string $merchantReference
     * @param array  $payload { bank_code, account_number, currency_code? }
     */
    public function addBankAccountOffRamp(string $merchantReference, array $payload): array
    {
        return $this->sendRequest('POST', "merchants/custodial/off_ramp_transactions/{$merchantReference}/bank_account", $payload);
    }

    /**
     * Confirm an off-ramp transaction — generates the crypto deposit address.
     * POST /merchants/custodial/off_ramp_transactions/{merchant_reference}/confirm
     *
     * @param string $merchantReference
     */
    public function confirmOffRamp(string $merchantReference): array
    {
        return $this->sendRequest('POST', "merchants/custodial/off_ramp_transactions/{$merchantReference}/confirm");
    }

    public function offRampTransaction(string $merchantReference): array
    {
        return $this->sendRequest('GET', "merchants/custodial/off_ramp_transactions/{$merchantReference}");
    }

    // =========================================================================
    // SHARED / UTILITIES
    // =========================================================================

    /**
     * Fetch available banks for off-ramp.
     * GET /merchants/custodial/banks?country=NG
     *
     * @param string $country  NG | GH
     */
    public function getBanks(string $country = 'NG'): array
    {
        return $this->sendRequest('GET', 'merchants/custodial/banks', ['country' => $country]);
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.quidax_ramp.webhook_secret', $this->privateKey);

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $candidates = [
            hash_hmac('sha256', $payload, $secret),
        ];

        $decodedPayload = json_decode($payload, true);
        if (is_array($decodedPayload) && isset($decodedPayload['data']) && is_array($decodedPayload['data'])) {
            $candidates[] = hash_hmac(
                'sha256',
                json_encode($decodedPayload['data'], JSON_UNESCAPED_SLASHES),
                $secret
            );
        }

        foreach ($candidates as $candidate) {
            if (hash_equals($candidate, $signature)) {
                return true;
            }
        }

        return false;
    }

    protected function extractMessage($value): ?string
    {
        if (is_string($value)) {
            $message = trim($value);

            return $message !== '' ? $message : null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['message', 'error', 'description', 'detail', 'title'] as $key) {
            if (array_key_exists($key, $value)) {
                $message = $this->extractMessage($value[$key]);

                if ($message) {
                    return $message;
                }
            }
        }

        foreach ($value as $item) {
            $message = $this->extractMessage($item);

            if ($message) {
                return $message;
            }
        }

        return null;
    }

    protected function sanitizeForLogs($value, ?string $key = null)
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitizeForLogs($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $sanitized;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (in_array($key, ['email', 'emailAddress'], true)) {
            return $this->maskEmail($value);
        }

        if (in_array($key, ['account_number', 'accountNumber'], true)) {
            return $this->maskTrailing($value, 4);
        }

        if (in_array($key, ['address', 'wallet_address', 'fund_uid'], true) && strlen($value) > 12) {
            return substr($value, 0, 6) . '...' . substr($value, -4);
        }

        return $value;
    }

    protected function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, min(2, strlen($local)));
        $masked = str_repeat('*', max(strlen($local) - strlen($visible), 0));

        return $visible . $masked . '@' . $domain;
    }

    protected function maskTrailing(string $value, int $visibleDigits = 4): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if (!$digits) {
            return $value;
        }

        if (strlen($digits) <= $visibleDigits) {
            return $digits;
        }

        return str_repeat('*', strlen($digits) - $visibleDigits) . substr($digits, -$visibleDigits);
    }
}
