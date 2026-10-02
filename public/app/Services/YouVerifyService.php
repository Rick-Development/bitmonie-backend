<?php

namespace App\Services;

use App\Models\User;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Exception;

class YouVerifyService
{
    protected string $baseUrl;
    protected ?string $secretKey;
    protected ?string $publicKey;
    protected ?string $webhookKey;
    protected array $endpoints;
    protected int $timeout;

    public function __construct()
    {
        // Prioritize services.php config which was already set up correctly
        $configUrl = config('services.youverify.base_url', config('youverify.base_url', 'https://api.youverify.co/v2/'));
        $configKey = config('services.youverify.key', config('youverify.secret_key'));
        $configPublicKey = config('services.youverify.public_key', config('youverify.public_key'));
        $configWebhookKey = config('services.youverify.webhook_key', config('youverify.webhook_secret'));

        // Check for Admin Override in database
        $basicSettings = \App\Models\Admin\BasicSettings::first();
        
        $this->baseUrl   = rtrim($configUrl, '/');
        $this->secretKey = ($basicSettings && $basicSettings->youverify_key) ? $basicSettings->youverify_key : $configKey;
        $this->publicKey = ($basicSettings && $basicSettings->youverify_public_key) ? $basicSettings->youverify_public_key : $configPublicKey;
        $this->webhookKey = ($basicSettings && $basicSettings->youverify_webhook_key) ? $basicSettings->youverify_webhook_key : $configWebhookKey;
        
        $this->endpoints = config('youverify.endpoints', []);
        $this->timeout   = (int) config('youverify.timeout', 30);
    }

    /**
    * Build an HTTP client instance with auth headers.
    */
    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'token' => $this->secretKey,
                'X-Youverify-Token' => $this->secretKey,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson();
    }

    /**
     * Verify BVN with Face Match (Tier 1)
     */
    public function verifyBvn(string $bvn, ?string $selfieImage = null)
    {
        $payload = [
            'id' => $bvn,
            'isSubjectConsent' => true,
        ];

        if ($selfieImage) {
            $payload['validations'] = [
                'selfie' => [
                    'image' => $selfieImage // Should be base64
                ]
            ];
        }

        try {
            $response = $this->client()->post('api/identity/ng/bvn', $payload);
            
            if ($response->failed()) {
                // \Illuminate\Support\Facades//\Log::error("YouVerify BVN Failed", [
                //     'status' => $response->status(),
                //     'body' => $response->body(),
                //     'payload' => $payload
                // ]);
            }

            $res = $response->json();
            // \Illuminate\Support\Facades//\Log::info("YouVerify BVN Response", [
            //     'status' => $response->status(),
            //     'body' => $response->body(),
            //     'json' => $res
            // ]);

            if ($response->status() === 404) {
                return ['status' => 'error', 'message' => 'BVN Verification endpoint not found (404). Please check API version/base URL.'];
            }

            if ($response->failed()) {
                return ['status' => 'error', 'message' => $res['message'] ?? $res['error'] ?? 'Verification service error (' . $response->status() . ')'];
            }
            
            return $res ?? ['status' => 'error', 'message' => 'Empty response from verification service'];
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify NIN (Tier 2)
     */
    public function verifyNin(string $nin)
    {
        $payload = [
            'id' => $nin,
            'isSubjectConsent' => true,
        ];

        try {
            $response = $this->client()->post('api/identity/ng/nin', $payload);
            return $response->json();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Submit OTP for ID verification
     */
    public function submitOtp(string $transactionId, string $otp)
    {
        $payload = [
            'transactionId' => $transactionId,
            'otp' => $otp,
        ];

        try {
            $response = $this->client()->post('api/identity/otp/verify', $payload);
            return $response->json();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify Address (Tier 3)
     */
    public function verifyAddress(array $data)
    {
        try {
            $response = $this->client()->post('api/identity/address-verification', $data);
            return $response->json();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Initiate a standard vForms identity verification workflow.
     */
    public function initiateWorkflow($payload)
    {
        try {
            $response = $this->client()->post('api/identity/vforms/initiate', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception("YouVerify Error: " . $response->status() . " - " . $response->body());
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify Webhook Signature (HMAC SHA256)
     */
    public function verifyWebhookSignature($signature, $payload)
    {
        $secret = $this->webhookKey;
        $expected = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($expected, $signature);
    }

    public function getVerificationStatus(string $reference): array
    {
        $path = rtrim($this->endpoints['get_verification_status'] ?? 'identity/verifications', '/')
            . '/' . $reference;

        $response = $this->client()
            ->get($path)
            ->throw();

        $body   = $response->json();
        $status = Arr::get($body, 'data.status', Arr::get($body, 'status', 'unknown'));

        return [
            'status' => $status,
            'raw'    => $body,
        ];
    }
}
