<?php
namespace App\Services\Savings\Contracts;

use App\Models\User;

interface SavingsFundingProviderInterface
{
    public function deposit(
        User $user,
        string $amount,
        string $reference
    ): array;

    public function withdraw(
        User $user,
        string $amount,
        string $reference
    ): array;

    public function getTransferStatus( string $sessionId, string $paymentReference ): array;
}
