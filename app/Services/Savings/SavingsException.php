<?php

namespace App\Services\Savings;

use RuntimeException;

/**
 * A business-rule violation that is safe to show to the client.
 * `$retryable` tells background workers whether trying again can help.
 */
class SavingsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'SAVINGS_ERROR',
        public readonly int $httpStatus = 422,
        public readonly bool $retryable = false,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function insufficientFunds(string $where = 'balance'): self
    {
        return new self("Insufficient {$where}.", 'INSUFFICIENT_FUNDS', 422, true);
    }

    public static function notFound(string $what = 'Savings resource'): self
    {
        return new self("{$what} not found.", 'NOT_FOUND', 404);
    }

    public static function invalidState(string $message): self
    {
        return new self($message, 'INVALID_STATE', 409);
    }
}
