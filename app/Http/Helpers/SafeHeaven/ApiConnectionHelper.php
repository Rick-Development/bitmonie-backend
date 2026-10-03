<?php

namespace App\Http\Helpers\SafeHeaven;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiConnectionHelper
{
    private $apiClientId;
    private $apiClientAssertion;
    private $apiAuthUrl;

    private $cacheKey = 'safeheaven_auth_token';

    public function __construct()
    {
        $basicSettings = null;
        try {
            $basicSettings = \App\Models\Admin\BasicSettings::first();
        } catch (\Throwable $e) {
            // Database is unreachable (e.g. during build / package discovery)
        }

        $this->apiClientId = $basicSettings?->safehaven_client_id ?? trim((string) config('services.safeHeaven.client_id'));
        $this->apiClientAssertion = $basicSettings?->safehaven_client_assertion ?? trim((string) config('services.safeHeaven.client_assertion'));
        $this->apiAuthUrl = rtrim($basicSettings?->safehaven_api_url ?? (string) config('services.safeHeaven.api_url'), '/');
    }

    public function authentication()
    {
        $tokenData = Cache::get($this->cacheKey);

        if ($tokenData) {
            $expiresAt = $tokenData['created_at'] + $tokenData['expires_in'];

            if (now()->timestamp < $expiresAt - 60) {
                return $tokenData;
            }
        }

        $jsonData = json_encode([
            "grant_type" => "client_credentials",
            "client_id" => $this->apiClientId,
            "client_assertion_type" => "urn:ietf:params:oauth:client-assertion-type:jwt-bearer",
            "client_assertion" => $this->apiClientAssertion,
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiAuthUrl . "/oauth2/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL Error: $err");
        }

        $decodedResponse = json_decode($response, true);

        if (!isset($decodedResponse['access_token'])) {
            throw new \Exception("Failed to obtain access token: " . $response);
        }

        $newTokenData = [
            'access_token' => $decodedResponse['access_token'],
            'expires_in' => $decodedResponse['expires_in'],
            'ibs_client_id' => $decodedResponse['ibs_client_id'],
            'created_at' => now()->timestamp,
        ];

        Cache::put($this->cacheKey, $newTokenData, $decodedResponse['expires_in'] - 60);

        return $newTokenData;
    }

    public function get($url)
    {
        $auth = $this->authentication();

        Log::info("SafeHaven GET Request", [
            'url' => $this->apiAuthUrl . $url,
            'ibs_client_id' => $auth['ibs_client_id'] ?? 'MISSING',
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiAuthUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$auth['access_token']}",
                "ClientID: {$auth['ibs_client_id']}",
                "Accept: application/json",
                "Content-Type: application/json",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            Log::error("SafeHaven GET Error: " . $err, ['url' => $this->apiAuthUrl . $url]);
        }

        Log::info("SafeHaven GET Response", ['url' => $this->apiAuthUrl . $url, 'response' => $response]);

        curl_close($curl);

        return $response;
    }

    public function post($url, array $data)
    {
        $auth = $this->authentication();
        $jsonData = json_encode($data);
        $curl = curl_init();

        Log::info("SafeHaven POST Request", ['url' => $this->apiAuthUrl . $url, 'payload' => $data]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiAuthUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$auth['access_token']}",
                "ClientID: {$auth['ibs_client_id']}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($jsonData),
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($err) {
            Log::error("SafeHaven POST Error: " . $err, ['url' => $this->apiAuthUrl . $url]);
        }

        Log::info("SafeHaven POST Response", [
            'url' => $this->apiAuthUrl . $url,
            'http_code' => $httpCode,
            'response' => $response,
        ]);

        if ($httpCode >= 400) {
            Log::error("SafeHaven POST HTTP Error $httpCode", ['url' => $this->apiAuthUrl . $url, 'response' => $response]);
        }

        curl_close($curl);

        return $response;
    }

    public function patch($url, $data)
    {
        $auth = $this->authentication();
        $jsonData = json_encode($data);
        $curl = curl_init();

        Log::info("SafeHaven PATCH Request", ['url' => $this->apiAuthUrl . $url, 'payload' => $data]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiAuthUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$auth['access_token']}",
                "ClientID: {$auth['ibs_client_id']}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($jsonData),
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($err) {
            Log::error("SafeHaven PATCH Error: " . $err, ['url' => $this->apiAuthUrl . $url]);
        }

        Log::info("SafeHaven PATCH Response", [
            'url' => $this->apiAuthUrl . $url,
            'http_code' => $httpCode,
            'response' => $response,
        ]);

        curl_close($curl);

        return $response;
    }

    public function put($url, $data)
    {
        $auth = $this->authentication();
        $jsonData = json_encode($data);
        $curl = curl_init();

        Log::info("SafeHaven PUT Request", ['url' => $this->apiAuthUrl . $url, 'payload' => $data]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiAuthUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$auth['access_token']}",
                "ClientID: {$auth['ibs_client_id']}",
                "Content-Type: application/json",
                "Content-Length: " . strlen($jsonData),
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($err) {
            Log::error("SafeHaven PUT Error: " . $err, ['url' => $this->apiAuthUrl . $url]);
        }

        Log::info("SafeHaven PUT Response", [
            'url' => $this->apiAuthUrl . $url,
            'http_code' => $httpCode,
            'response' => $response,
        ]);

        curl_close($curl);

        return $response;
    }
}
