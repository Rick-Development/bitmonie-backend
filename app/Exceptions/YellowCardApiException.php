<?php

namespace App\Exceptions;

use RuntimeException;

class YellowCardApiException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $statusCode = 0,
        protected array|string|null $response = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function response(): array|string|null
    {
        return $this->response;
    }
}
