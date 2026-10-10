<?php

namespace App\Services;

class CurlService
{
    protected $baseUrl;
    protected $rampUrl;
    protected $p2pUrl;
    protected $headers;
    private ?string $PrivateKey = null;

    public function __construct($baseUrl, $privateKey = null, $token = null, $rampUrl = null, $p2pUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->rampUrl = rtrim($rampUrl ?: $baseUrl, '/');
        $this->p2pUrl = $p2pUrl ? rtrim($p2pUrl, '/') : null;
        $this->PrivateKey = config('services.quidax.private');
        

        $this->headers = [
            "accept: application/json",
            "x-private-key: {$this->PrivateKey}",
        ];

        if ($token) {
            $this->headers[] = "Authorization: Bearer {$token}";
        }
        
        
    }

    // protected function buildUrl($endpoint)
    // {
    //     // Define which endpoints use ramp
    //     $rampEndpoints = ['ramp/', 'ramp-auth/', 'ramp-orders/'];

    //     foreach ($rampEndpoints as $prefix) {
    //         if (str_contains($endpoint, $prefix)) {
    //             return $this->rampUrl . "/{$endpoint}";
    //         }
    //     }

    //     return $this->baseUrl . "/{$endpoint}";
    // }

    protected function request($method, $endpoint, $data = [])
    {
        $curl = curl_init();
        //\Log::info("{$this->baseUrl}/{$endpoint}");
        $options = [
            CURLOPT_URL => (str_contains($endpoint, 'ramp')
                ? $this->rampUrl
                : $this->baseUrl) . "/{$endpoint}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $this->headers,
        ];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            $options[CURLOPT_HTTPHEADER][] = "Content-Type: application/json";
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return [
                'status' => 'error',
                'message' => 'Provider request failed: ' . $err,
                'data' => null,
                'error' => $err,
                'retryable' => true,
            ];
        }

        $responseBody = trim((string) $response);
        if ($responseBody === '') {
            return [
                'status' => 'error',
                'message' => 'Provider returned an empty response.',
                'data' => null,
                'retryable' => true,
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 'error',
                'message' => 'Provider returned an invalid response.',
                'data' => null,
                'error' => json_last_error_msg(),
                'retryable' => true,
            ];
        }

        return $decoded;
    }

    public function get($endpoint, $params = [])
    {
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->request('GET', $endpoint);
    }

    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function put($endpoint, $data = [])
    {
        return $this->request('PUT', $endpoint, $data);
    }
}
