<?php

namespace App\Services\Kyc\Providers;

use App\Models\User;
use App\Services\CountryService;
use App\Services\FonbnkService;
use App\Services\Kyc\Contracts\KycProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FonbnkProvider implements KycProviderInterface
{
    public function __construct(
        protected FonbnkService $fonbnk,
        protected CountryService $countryService
    ) {
    }

    public function name(): string
    {
        return 'fonbnk';
    }

    /**
     * Countries Fonbnk actually returns on GET /api/v2/currencies (fiat only).
     * Prefer currencyDetails.countryIsoCode from the API over local JSON.
     */
    public function supportsCountry(string $countryCode): bool
    {
        $code = strtoupper(trim($countryCode));

        if ($code === '') {
            return false;
        }

        return in_array($code, $this->supportedCountryCodes(), true);
    }

    /**
     * @return list<string> e.g. ['NG', 'KE', 'GH', ...]
     */
    public function supportedCountryCodes(): array
    {
        return Cache::remember('fonbnk_supported_countries', 3600, function () {
            $result = $this->fonbnk->getCurrencies();

            if (!($result['ok'] ?? false)) {
                Log::warning('Fonbnk getCurrencies failed in supportsCountry', [
                    'message' => $result['message'] ?? null,
                ]);

                return [];
            }

            $items = $result['data'] ?? [];

            // data may be a bare list or wrapped
            if (isset($items['currencies']) && is_array($items['currencies'])) {
                $items = $items['currencies'];
            }

            if (!is_array($items)) {
                return [];
            }

            return collect($items)
                ->filter(fn ($row) => is_array($row))
                ->filter(fn ($row) => strtolower((string) ($row['currencyType'] ?? '')) === 'fiat')
                ->map(function (array $row) {
                    // Primary: API country on the currency
                    $fromApi = strtoupper((string) data_get($row, 'currencyDetails.countryIsoCode', ''));

                    if ($fromApi !== '') {
                        return $fromApi;
                    }

                    // Fallback: local countries.json via currency code
                    $currencyCode = strtoupper((string) ($row['currencyCode'] ?? ''));

                    return $currencyCode !== ''
                        ? $this->countryService->countryFromFiat($currencyCode)
                        : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Submit KYC.
     *
     * Expected $data:
     * - country_code | countryIsoCode | country
     * - document_id | documentId
     * - user_fields | userFields  (first_name, last_name, dob, id_number, images, ...)
     */
    public function initiate(User $user, array $data): array
    {
        $country = strtoupper((string) (
            $data['country_code']
            ?? $data['countryIsoCode']
            ?? $data['country']
            ?? ''
        ));

        $documentId = (string) ($data['document_id'] ?? $data['documentId'] ?? '');
        $userFields = $data['user_fields'] ?? $data['userFields'] ?? [];

        if (!is_array($userFields)) {
            $userFields = [];
        }

        if (!$this->supportsCountry($country)) {
            return $this->fail('unsupported_country', "Fonbnk KYC does not support country {$country}.");
        }

        if ($documentId === '') {
            return $this->fail('missing_document_id', 'document_id is required.');
        }

        if (empty($user->email)) {
            return $this->fail('missing_email', 'User email is required for Fonbnk KYC.');
        }

        if (empty($userFields['email'])) {
            $userFields['email'] = $user->email;
        }

        if (!empty($userFields['images']) && is_array($userFields['images'])) {
            $userFields['images'] = $this->normalizeImages($userFields['images']);
        }

        $payload = [
            'userEmail'      => $user->email,
            'countryIsoCode' => $country,
            'documentId'     => $documentId,
            'userFields'     => $userFields,
        ];

        $result = $this->fonbnk->submitUserKyc($payload);

        if (!($result['ok'] ?? false)) {
            return $this->mapError($result);
        }

        $body = is_array($result['data'] ?? null) ? $result['data'] : [];
        $reference = $body['reference']
            ?? $body['id']
            ?? $body['_id']
            ?? ('fonbnk_kyc_' . $user->id . '_' . strtolower($country) . '_' . Str::random(6));

        return [
            'ok'          => true,
            'provider'    => $this->name(),
            'reference'   => (string) $reference,
            'status'      => $this->normalizeStatus($body),
            'is_approved' => $this->isApproved($body),
            'message'     => $result['message'] ?? 'Fonbnk KYC submitted',
            'data'        => $body,
            'raw'         => $result,
        ];
    }

    /**
     * No separate OTP verify step in Fonbnk merchant KYC docs — re-fetch status.
     * Pass country via $data['country_code'] or encode it in $reference.
     */
    public function verify(User $user, string $reference, array $data = []): array
    {
        return $this->status($user, $data['country_code'] ?? $data['country'] ?? $reference);
    }

    /**
     * $reference = country ISO (KE) or a local ref containing the country.
     */
    public function status(User $user, string $reference): array
    {
        $country = $this->resolveCountry($reference, $user);

        if ($country === null || !$this->supportsCountry($country)) {
            return $this->fail('invalid_reference', 'Unable to resolve a supported country for Fonbnk KYC status.');
        }

        if (empty($user->email)) {
            return $this->fail('missing_email', 'User email is required for Fonbnk KYC.');
        }

        $result = $this->fonbnk->getUserKyc($user->email, $country);

        if (!($result['ok'] ?? false)) {
            return $this->mapError($result);
        }

        $body = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'ok'          => true,
            'provider'    => $this->name(),
            'reference'   => $reference,
            'status'      => $this->normalizeStatus($body),
            'is_approved' => $this->isApproved($body),
            'message'     => $result['message'] ?? 'Fonbnk KYC status fetched',
            'data'        => [
                'country_iso_code'               => $country,
                'passed_kyc_type'                => $body['passedKycType'] ?? null,
                'current_kyc_type'               => $body['currentKycType'] ?? null,
                'current_kyc_status'             => $body['currentKycStatus'] ?? null,
                'current_kyc_status_description' => $body['currentKycStatusDescription'] ?? null,
                'reached_kyc_limit'              => $body['reachedKycLimit'] ?? false,
                'kyc_documents'                  => $body['kycDocuments'] ?? [],
                'raw'                            => $body,
            ],
            'raw'         => $result,
        ];
    }

    public function handleWebhook(array $payload): array
    {
        Log::info('Fonbnk KYC webhook received', [
            'keys' => array_keys($payload),
        ]);

        return [
            'ok'       => true,
            'provider' => $this->name(),
            'status'   => 'received',
            'data'     => $payload,
        ];
    }

    // -------------------------------------------------------------------------

    protected function resolveCountry(string $reference, User $user): ?string
    {
        $ref = strtoupper(trim($reference));

        if (strlen($ref) === 2) {
            return $ref;
        }

        // fonbnk_kyc_{userId}_{ke}_{random}
        if (preg_match('/(?:^|_)([A-Z]{2})(?:_|$)/', $ref, $m)) {
            return $m[1];
        }

        $fromUser = strtoupper((string) (
            $user->country_code
            ?? $user->country_iso_code
            ?? data_get($user, 'profile.country_code')
            ?? ''
        ));

        return strlen($fromUser) === 2 ? $fromUser : null;
    }

    protected function normalizeImages(array $images): array
    {
        $out = [];

        foreach ($images as $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeId = $row['image_type_id'] ?? $row['imageTypeId'] ?? null;
            $image  = $row['image'] ?? null;

            if ($typeId === null || $image === null || $image === '') {
                continue;
            }

            // Local file path → base64 data URI
            if (is_string($image) && is_file($image)) {
                $mime  = mime_content_type($image) ?: 'image/jpeg';
                $image = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($image));
            }

            $out[] = [
                'image_type_id' => (int) $typeId,
                'image'         => $image, // public URL or base64
            ];
        }

        return $out;
    }

    protected function isApproved(array $body): bool
    {
        $status = strtolower((string) ($body['currentKycStatus'] ?? ''));
        $desc   = strtolower((string) ($body['currentKycStatusDescription'] ?? ''));

        return in_array($status, ['approved', 'verified', 'success'], true)
            || $desc === 'exact match';
    }

    protected function normalizeStatus(array $body): string
    {
        if ($this->isApproved($body)) {
            return 'approved';
        }

        $status = strtolower((string) ($body['currentKycStatus'] ?? ''));

        return match ($status) {
            'initiated', 'pending', 'processing', 'in_review' => 'pending',
            'rejected', 'failed', 'declined' => 'rejected',
            'approved', 'verified', 'success' => 'approved',
            default => $status !== '' ? $status : 'unknown',
        };
    }

    protected function mapError(array $result): array
    {
        return [
            'ok'          => false,
            'provider'    => $this->name(),
            'status'      => $result['status'] ?? 'error',
            'message'     => $result['message'] ?? 'Fonbnk KYC provider error',
            'http_status' => $result['http_status'] ?? 400,
            'data'        => $result['data'] ?? null,
            'raw'         => $result,
        ];
    }

    protected function fail(string $status, string $message, int $httpStatus = 422): array
    {
        return [
            'ok'          => false,
            'provider'    => $this->name(),
            'status'      => $status,
            'message'     => $message,
            'http_status' => $httpStatus,
            'data'        => null,
            'raw'         => null,
        ];
    }
}