<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FonbnkService
{
    /**
     * Fonbnk Merchant API v2
     *
     * Sandbox: https://sandbox-api.fonbnk.com
     * Production: https://api.fonbnk.com
     *
     * Auth (from official Postman collection):
     *   x-client-id
     *   x-timestamp  (unix ms)
     *   x-signature  = Base64( HMAC-SHA256( "{timestamp}:{pathAndQuery}", Base64Decode(clientSecret) ) )
     */
    protected string $baseUrl;

    protected ?string $clientId;

    protected ?string $clientSecret;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) config('services.fonbnk.base_url', 'https://sandbox-api.fonbnk.com'),
            '/'
        );

        $this->clientId = config('services.fonbnk.client_id');

        $this->clientSecret = config('services.fonbnk.client_secret')
            ?? config('services.fonbnk.api_key');

        $this->timeout = (int) config('services.fonbnk.timeout', 30);
    }

    // =========================================================================
    // CORE HTTP
    // =========================================================================

    protected function get(string $endpoint, array $query = []): array
    {
        return $this->sendRequest('GET', $endpoint, $query);
    }

    protected function post(string $endpoint, array $payload = []): array
    {
        return $this->sendRequest('POST', $endpoint, $payload);
    }

    /**
     * Build signature exactly as the Fonbnk Postman pre-request script does.
     *
     * stringToSign = "{timestamp}:{pathAndQuery}"
     * signature    = Base64( HMAC-SHA256( stringToSign, Base64Decode(clientSecret) ) )
     */
    protected function buildSignature(string $timestamp, string $pathAndQuery): string
    {
        $stringToSign = $timestamp . ':' . $pathAndQuery;

        $secret = (string) $this->clientSecret;

        $key = base64_decode($secret, true);

        if ($key === false || $key === '') {
            $key = $secret;
        }

        $hmac = hash_hmac('sha256', $stringToSign, $key, true);

        return base64_encode($hmac);
    }

    protected function sendRequest(
        string $method,
        string $endpoint,
        array $payload = []
    ): array {
        $requestId = (string) Str::uuid();
        $method = strtoupper($method);

        $endpoint = '/' . ltrim($endpoint, '/');
        $url = $this->baseUrl . $endpoint;

        $pathAndQuery = $endpoint;
        $queryForGet = [];

        if ($method === 'GET' && !empty($payload)) {
            $queryForGet = array_filter(
                $payload,
                static fn ($v) => $v !== null && $v !== ''
            );
            $qs = http_build_query($queryForGet);
            if ($qs !== '') {
                $pathAndQuery .= '?' . $qs;
            }
        }

        $timestamp = (string) (int) (microtime(true) * 1000);

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'x-client-id'  => (string) $this->clientId,
            'x-timestamp'  => $timestamp,
            'x-signature'  => $this->buildSignature($timestamp, $pathAndQuery),
        ];

        Log::info('Fonbnk API Request', [
            'request_id' => $requestId,
            'method'     => $method,
            'endpoint'   => $endpoint,
            'path_sign'  => $pathAndQuery,
            'payload'    => $this->sanitizeLogPayload($payload),
        ]);

        try {
            $request = Http::timeout($this->timeout)->withHeaders($headers);

            $response = match ($method) {
                'GET'  => $request->get($url, $queryForGet),
                'POST' => $request->post($url, $payload),
                default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
            };

            $parsed = $this->parseResponse($response);

            $logContext = [
                'request_id'  => $requestId,
                'method'      => $method,
                'endpoint'    => $endpoint,
                'http_status' => $response->status(),
                'response'    => $this->sanitizeLogPayload(
                    is_array($parsed) ? $parsed : ['body' => $parsed]
                ),
            ];

            if ($response->successful()) {
                Log::info('Fonbnk API Response', $logContext);
            } else {
                Log::warning('Fonbnk API Error Response', $logContext);
            }

            return $this->normalizeResult($response, $parsed, $requestId);
        } catch (Throwable $e) {
            Log::error('Fonbnk API Exception', [
                'request_id' => $requestId,
                'method'     => $method,
                'endpoint'   => $endpoint,
                'message'    => $e->getMessage(),
            ]);

            return [
                'ok'          => false,
                'request_id'  => $requestId,
                'http_status' => null,
                'status'      => 'transport_error',
                'message'     => $e->getMessage(),
                'data'        => null,
                'raw'         => null,
            ];
        }
    }

    protected function parseResponse(Response $response): mixed
    {
        $json = $response->json();

        if (is_array($json) || is_string($json) || is_numeric($json) || is_bool($json)) {
            return $json;
        }

        return ['message' => $response->body()];
    }

    protected function normalizeResult(
        Response $response,
        mixed $parsed,
        string $requestId
    ): array {
        $ok = $response->successful();
        $httpStatus = $response->status();

        $data = is_array($parsed) ? $parsed : ['value' => $parsed];

        $message = null;
        if (is_array($parsed)) {
            $message = $parsed['message']
                ?? $parsed['error']
                ?? data_get($parsed, 'error.message')
                ?? data_get($parsed, 'errors.0')
                ?? null;
        }

        if (!is_string($message) || $message === '') {
            $message = $ok ? 'Success' : ($response->reason() ?: 'Fonbnk provider error');
        }

        $status = $ok ? 'ok' : 'error';
        if (!$ok && is_array($parsed)) {
            $code = $parsed['code'] ?? $parsed['errorCode'] ?? null;
            if (is_string($code) && $code !== '') {
                $status = strtolower(str_replace([' ', '-'], '_', $code));
            }
        }

        return [
            'ok'          => $ok,
            'request_id'  => $requestId,
            'http_status' => $httpStatus,
            'status'      => $status,
            'message'     => $message,
            'data'        => $data,
            'raw'         => $response->body(),
        ];
    }

    protected function sanitizeLogPayload($payload)
    {
        if (!is_array($payload)) {
            return $payload;
        }

        $sensitive = [
            'api_key', 'client_id', 'client_secret', 'authorization',
            'access_token', 'refresh_token', 'password', 'otp', 'otpcode',
            'signature', 'x-signature', 'account_number', 'bankaccountnumber',
            'bank_account_number', 'phone_number', 'phonenumber',
        ];

        $out = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, $sensitive, true)) {
                $out[$key] = '********';
            } elseif (is_array($value)) {
                $out[$key] = $this->sanitizeLogPayload($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    // =========================================================================
    // CURRENCIES / LIMITS / BALANCE
    // =========================================================================

    public function getCurrencies(): array
    {
        return $this->get('/api/v2/currencies');
    }

    public function getOrderLimits(array $params): array
    {
        return $this->get('/api/v2/order-limits', $params);
    }

    public function getMerchantBalance(): array
    {
        return $this->get('/api/v2/merchant-balance');
    }

    // =========================================================================
    // USER / KYC / TOKENS
    // =========================================================================

    public function getUserKyc(string $userEmail, string $countryIsoCode): array
    {
        return $this->get('/api/v2/user/kyc', [
            'userEmail'      => $userEmail,
            'countryIsoCode' => $countryIsoCode,
        ]);
    }

    public function submitUserKyc(array $payload): array
    {
        return $this->post('/api/v2/user/kyc', $payload);
    }

    public function generateUserTokens(string $email, string $countryIsoCode): array
    {
        return $this->post('/api/v2/user/tokens', [
            'email'          => $email,
            'countryIsoCode' => $countryIsoCode,
        ]);
    }

    // =========================================================================
    // QUOTE
    // =========================================================================

    public function getQuote(array $payload): array
    {
        return $this->post('/api/v2/quote', $payload);
    }

    // =========================================================================
    // ORDERS
    // =========================================================================

    public function createOrder(array $payload): array
    {
        return $this->post('/api/v2/order', $payload);
    }

    public function intermediateAction(array $payload): array
    {
        return $this->post('/api/v2/order/intermediate-action', $payload);
    }

    public function confirmOrder(string $orderId): array
    {
        return $this->post('/api/v2/order/confirm', [
            'orderId' => $orderId,
        ]);
    }

    public function cancelOrder(string $orderId): array
    {
        return $this->post('/api/v2/order/cancel', [
            'orderId' => $orderId,
        ]);
    }

    public function getOrder(?string $orderId = null, ?string $orderParams = null): array
    {
        $query = array_filter([
            'orderId'     => $orderId,
            'orderParams' => $orderParams,
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->get('/api/v2/order', $query);
    }

    public function getOrders(array $params = []): array
    {
        return $this->get('/api/v2/orders', $params);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * On-ramp quote: local fiat → crypto
     */
    public function getOnRampQuote(array $params): array
    {
        $deposit = array_filter([
            'paymentChannel' => $params['paymentChannel'] ?? $params['payment_channel'] ?? null,
            'currencyType'   => 'fiat',
            'currencyCode'   => strtoupper((string) ($params['fiatCurrencyCode'] ?? $params['from_currency'] ?? '')),
            'countryIsoCode' => strtoupper((string) ($params['countryIsoCode'] ?? $params['country'] ?? '')),
            'amount'         => isset($params['amount']) ? (float) $params['amount'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $payout = array_filter([
            'paymentChannel' => 'crypto',
            'currencyType'   => 'crypto',
            'currencyCode'   => strtoupper((string) ($params['cryptoCurrencyCode'] ?? $params['to_currency'] ?? '')),
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->getQuote([
            'deposit' => $deposit,
            'payout'  => $payout,
        ]);
    }

    /**
     * Off-ramp quote: crypto → local fiat
     */
    public function getOffRampQuote(array $params): array
    {
        $deposit = array_filter([
            'paymentChannel' => 'crypto',
            'currencyType'   => 'crypto',
            'currencyCode'   => strtoupper((string) ($params['cryptoCurrencyCode'] ?? $params['from_currency'] ?? '')),
            'amount'         => isset($params['amount']) ? (float) $params['amount'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $payout = array_filter([
            'paymentChannel' => $params['paymentChannel'] ?? $params['payment_channel'] ?? 'bank',
            'currencyType'   => 'fiat',
            'currencyCode'   => strtoupper((string) ($params['fiatCurrencyCode'] ?? $params['to_currency'] ?? '')),
            'countryIsoCode' => strtoupper((string) ($params['countryIsoCode'] ?? $params['country'] ?? '')),
        ], static fn ($v) => $v !== null && $v !== '');

        return $this->getQuote([
            'deposit' => $deposit,
            'payout'  => $payout,
        ]);
    }

    /**
     * Map network + asset → Fonbnk crypto currencyCode (e.g. POLYGON_USDT).
     */
    public function buildCryptoCurrencyCode(?string $network, ?string $asset): string
    {
        $network = strtoupper(trim((string) $network));
        $asset   = strtoupper(trim((string) $asset));

        $networkMap = [
            'TRC20'     => 'TRON',
            'TRC-20'    => 'TRON',
            'BEP20'     => 'BNB',
            'BEP-20'    => 'BNB',
            'ERC20'     => 'ETHEREUM',
            'ERC-20'    => 'ETHEREUM',
            'POLYGON'   => 'POLYGON',
            'MATIC'     => 'POLYGON',
            'SOL'       => 'SOLANA',
            'SOLANA'    => 'SOLANA',
            'CELO'      => 'CELO',
            'BASE'      => 'BASE',
            'ARBITRUM'  => 'ARBITRUM',
            'OPTIMISM'  => 'OPTIMISM',
            'TON'       => 'TON',
            'XRP'       => 'XRP',
            'LISK'      => 'LISK',
            'AVALANCHE' => 'AVALANCHE',
            'BNB'       => 'BNB',
            'TRON'      => 'TRON',
            'ETHEREUM'  => 'ETHEREUM',
        ];

        $network = $networkMap[$network] ?? $network;

        if ($network === '' || $asset === '') {
            return $asset !== '' ? $asset : $network;
        }

        if (in_array($asset, ['NATIVE', 'ETH', 'BNB', 'SOL', 'TRX', 'TON', 'XRP', 'CELO'], true)) {
            if ($asset === 'ETH') {
                return 'ETHEREUM_NATIVE';
            }
            if ($asset === 'NATIVE') {
                return $network . '_NATIVE';
            }
        }

        return $network . '_' . $asset;
    }
}