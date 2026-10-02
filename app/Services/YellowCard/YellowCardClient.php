<?php

namespace App\Services\YellowCard;

use App\Exceptions\YellowCardApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YellowCardClient
{
    public function __construct(protected YellowCardSigner $signer)
    {
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('POST', $path, $payload, $query);
    }

    public function put(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('PUT', $path, $payload, $query);
    }

    public function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');
        $body = in_array($method, ['POST', 'PUT'], true)
            ? json_encode($payload, JSON_UNESCAPED_SLASHES)
            : null;

        $attempts = max(1, (int) config('services.yellow_card.retries', 2) + 1);
        $sleepMs = max(0, (int) config('services.yellow_card.retry_sleep_ms', 500));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->send($method, $path, $body, $query);

                if ($this->isTransient($response) && $attempt < $attempts) {
                    usleep($sleepMs * 1000);
                    continue;
                }

                return $this->decodeResponse($response);
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt < $attempts) {
                    usleep($sleepMs * 1000);
                    continue;
                }

                throw new YellowCardApiException('Yellow Card connection failed: ' . $exception->getMessage());
            }
        }

        throw new YellowCardApiException('Yellow Card request failed: ' . ($lastException?->getMessage() ?? 'unknown error'));
    }

    protected function send(string $method, string $path, ?string $body, array $query): HttpResponse
    {
        $baseUrl = rtrim((string) config('services.yellow_card.base_url'), '/');
        $timestamp = $this->signer->timestamp();
        $signature = $this->signer->sign($timestamp, $this->signaturePath($baseUrl, $path), $method, $this->secretKey(), $body);

        $request = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-YC-Timestamp' => $timestamp,
                'Authorization' => $this->signer->authorizationHeader($this->apiKey(), $signature),
            ])
            ->timeout((int) config('services.yellow_card.timeout', 20));

        if ($body !== null) {
            $request = $request->withBody($body, 'application/json');
        }

        return $request->send($method, $baseUrl . $path, ['query' => array_filter($query, fn ($value) => $value !== null && $value !== '')]);
    }

    protected function decodeResponse(HttpResponse $response): array
    {
        $json = $response->json();
        $payload = is_array($json) ? $json : $response->body();

        if ($response->successful()) {
            return is_array($payload) ? $payload : ['raw' => $payload];
        }

        throw new YellowCardApiException(
            $this->errorMessage($response, $payload),
            $response->status(),
            $payload
        );
    }

    protected function errorMessage(HttpResponse $response, array|string|null $payload): string
    {
        $message = is_array($payload)
            ? data_get($payload, 'message', data_get($payload, 'error', 'Yellow Card request failed.'))
            : ($payload ?: 'Yellow Card request failed.');

        return match ($response->status()) {
            401, 403 => 'Yellow Card authentication failed. Check API key, secret key, timestamp, signature, and IP whitelist.',
            429 => 'Yellow Card rate limit exceeded.',
            default => Str::limit((string) $message, 500),
        };
    }

    protected function isTransient(HttpResponse $response): bool
    {
        return in_array($response->status(), [408, 425, 429, 500, 502, 503, 504], true);
    }

    protected function signaturePath(string $baseUrl, string $path): string
    {
        $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '';

        return '/' . trim($basePath . '/' . ltrim($path, '/'), '/');
    }

    protected function apiKey(): string
    {
        $apiKey = (string) config('services.yellow_card.api_key');

        if ($apiKey === '') {
            throw new YellowCardApiException('Yellow Card API key is not configured.');
        }

        return $apiKey;
    }

    protected function secretKey(): string
    {
        $secretKey = (string) config('services.yellow_card.secret_key');

        if ($secretKey === '') {
            throw new YellowCardApiException('Yellow Card secret key is not configured.');
        }

        return $secretKey;
    }
}
