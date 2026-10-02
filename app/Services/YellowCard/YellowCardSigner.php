<?php

namespace App\Services\YellowCard;

use Carbon\CarbonInterface;

class YellowCardSigner
{
    public function bodyHash(string $body): string
    {
        return base64_encode(hash('sha256', $body, true));
    }

    public function message(string $timestamp, string $path, string $method, ?string $body = null): string
    {
        $message = $timestamp . $path . strtoupper($method);

        if (in_array(strtoupper($method), ['POST', 'PUT'], true)) {
            $message .= $this->bodyHash($body ?? '');
        }

        return $message;
    }

    public function sign(string $timestamp, string $path, string $method, string $secretKey, ?string $body = null): string
    {
        return base64_encode(hash_hmac('sha256', $this->message($timestamp, $path, $method, $body), $secretKey, true));
    }

    public function authorizationHeader(string $apiKey, string $signature): string
    {
        return "YcHmacV1 {$apiKey}:{$signature}";
    }

    public function timestamp(?CarbonInterface $time = null): string
    {
        return ($time ?: now('UTC'))->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
