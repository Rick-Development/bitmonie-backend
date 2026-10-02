<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class ReloadlyService
{
    protected string $baseUrl;
    protected string $authUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.reloadly.base_url');
        $this->authUrl = config('services.reloadly.auth_url');
        $this->clientId = config('services.reloadly.client_id');
        $this->clientSecret = config('services.reloadly.client_secret');
    }

    /**
     * Get or refresh OAuth2 access token
     */
    protected function getAccessToken(): string
    {
        // Use a unique cache key per environment/client
        $cacheKey = 'reloadly_giftcard_token_' . md5($this->clientId . $this->baseUrl);

        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::post($this->authUrl, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
                'audience' => str_contains($this->baseUrl, 'sandbox') 
                    ? 'https://giftcards-sandbox.reloadly.com' 
                    : 'https://giftcards.reloadly.com',
            ]);

            if ($response->failed()) {
                throw new Exception("Reloadly Auth Failed: " . $response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Build an HTTP client instance with auth headers.
     */
    protected function client(array $headers = [])
    {
        $defaultHeaders = array_merge([
            'Accept' => 'application/com.reloadly.giftcards-v1+json',
        ], $headers);

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->getAccessToken())
            ->withHeaders($defaultHeaders);
    }

    protected function parseResponse($response)
    {
        if ($response->failed()) {
            throw new Exception("Reloadly API Error: " . $response->body());
        }
        return json_decode($response->body(), true);
    }

    // --- Discovery Methods ---

    public function getCategories()
    {
        return $this->parseResponse($this->client()->get('product-categories'));
    }

    public function getCountries()
    {
        return $this->parseResponse($this->client()->get('countries'));
    }

    public function getCountry(string $isoCode)
    {
        return $this->parseResponse($this->client()->get("countries/{$isoCode}"));
    }

    public function getProducts(array $filters = [])
    {
        return $this->parseResponse($this->client()->get('products', $filters));
    }

    public function getProductById(int $id)
    {
        return $this->parseResponse($this->client()->get("products/{$id}"));
    }

    public function getProductsByCountry(string $isoCode)
    {
        return $this->parseResponse($this->client()->get("countries/{$isoCode}/products"));
    }

    // --- Redeem Instructions ---

    public function getRedeemInstructions()
    {
        return $this->parseResponse($this->client()->get('redeem-instructions'));
    }

    public function getRedeemInstructionsByProduct(int $productId)
    {
        return $this->parseResponse($this->client()->get("products/{$productId}/redeem-instructions"));
    }

    // --- Financial & Discounts ---

    public function getFxRate(string $currencyCode, float $amount)
    {
        return $this->parseResponse($this->client()->get('fx-rate', [
            'currencyCode' => $currencyCode,
            'amount' => $amount
        ]));
    }

    public function getDiscounts(array $filters = [])
    {
        return $this->parseResponse($this->client()->get('discounts', $filters));
    }

    public function getDiscountByProduct(int $productId)
    {
        return $this->parseResponse($this->client()->get("products/{$productId}/discounts"));
    }

    // --- Order Execution ---

    public function placeOrder(array $payload)
    {
        return $this->parseResponse($this->client()->post('orders', $payload));
    }

    public function getRedeemCode(string $transactionId, string $version = 'v2')
    {
        $acceptHeader = $version === 'v2' 
            ? 'application/com.reloadly.giftcards-v2+json' 
            : 'application/com.reloadly.giftcards-v1+json';

        return $this->parseResponse($this->client([
            'Accept' => $acceptHeader
        ])->get("orders/transactions/{$transactionId}/cards"));
    }

    // --- Reporting ---

    public function getTransactions(array $filters = [])
    {
        return $this->parseResponse($this->client()->get('reports/transactions', $filters));
    }

    public function getTransactionById(string $transactionId)
    {
        return $this->parseResponse($this->client()->get("reports/transactions/{$transactionId}"));
    }

    /**
     * Get account balance from Reloadly
     */
    public function getBalance()
    {
        return $this->parseResponse($this->client()->get('accounts/balance'));
    }
}
