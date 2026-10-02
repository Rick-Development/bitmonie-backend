<?php

namespace App\Services\Kyc\Contracts;

use App\Models\User;

interface KycProviderInterface
{
    public function name(): string;

    public function supportsCountry(string $countryCode): bool;

    public function initiate(User $user, array $data): array;

    public function verify(User $user, string $reference, array $data = []): array;

    public function status(User $user, string $reference): array;

    public function handleWebhook(array $payload): array;
}