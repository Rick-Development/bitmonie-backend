<?php

namespace App\Services;

class CurlService
{
    protected $baseUrl;
    protected $rampUrl;
    protected $headers = [];

    public function __construct(
        $baseUrl,
        $privateKey = null,
        $token = null,
        $rampUrl = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->rampUrl = rtrim($rampUrl ?: $baseUrl, '/');

        $this->headers = [
            'Accept: application/json',
        ];

        if (!empty($privateKey)) {
            $this->headers[] = "x-private-key: {$privateKey}";
        }

        if (!empty($token)) {
            $this->headers[] = "Authorization: Bearer {$token}";
        }
    }


    protected function request($method, $endpoint, $data = [])
    {
        $curl = curl_init();

        $baseUrl = str_contains($endpoint, 'ramp')
            ? $this->rampUrl
            : $this->baseUrl;

        $url = $baseUrl . '/' . ltrim($endpoint, '/');
    

        $headers = $this->headers;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];


        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {

            $options[CURLOPT_POSTFIELDS] = json_encode($data);

            $options[CURLOPT_HTTPHEADER][] = "Content-Type: application/json";
        }


        curl_setopt_array($curl, $options);


        $response = curl_exec($curl);

        $error = curl_error($curl);

        curl_close($curl);


        if ($error) {
            return [
                'error' => $error
            ];
        }


        return json_decode($response, true);
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