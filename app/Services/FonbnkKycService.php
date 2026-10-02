<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class FonbnkKycService
{
    protected FonbnkService $fonbnk;

    public function __construct(FonbnkService $fonbnk)
    {
        $this->fonbnk = $fonbnk;
    }

    /**
     * Get complete Fonbnk KYC state for a user/country.
     */
    public function status(User $user, string $countryIsoCode): array
    {
        $country = strtoupper(trim($countryIsoCode));
        $email = trim((string) $user->email);

        if ($email === '') {
            return $this->error(
                'missing_email',
                'User email is required for Fonbnk KYC.',
                422
            );
        }

        if (!$this->validCountry($country)) {
            return $this->error(
                'invalid_country',
                'Country must be a 2-letter ISO code.',
                422
            );
        }

        try {
            $result = $this->fonbnk->getUserKyc(
                $email,
                $country
            );

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $data = is_array($result['data'] ?? null)
                ? $result['data']
                : [];

            return [
                'ok' => true,
                'status' => 'ok',
                'message' => 'Fonbnk KYC status fetched successfully',
                'http_status' => (int) ($result['http_status'] ?? 200),

                'data' => [
                    'email' => $email,
                    'country_iso_code' => $country,

                    /*
                     * KYC state
                     */
                    'passed_kyc_type' =>
                        $data['passedKycType'] ?? null,

                    'required_kyc_type' =>
                        $data['requiredKycType'] ?? null,

                    'current_kyc_type' =>
                        $data['currentKycType'] ?? null,

                    'current_kyc_status' =>
                        $data['currentKycStatus'] ?? null,

                    'current_kyc_status_description' =>
                        $data['currentKycStatusDescription'] ?? null,

                    'current_kyc_phase' =>
                        $data['currentKycPhase'] ?? null,

                    'reached_kyc_limit' =>
                        (bool) ($data['reachedKycLimit'] ?? false),

                    /*
                     * Documents available for this country.
                     */
                    'kyc_documents' =>
                        $data['kycDocuments'] ?? [],

                    /*
                     * Country KYC rules.
                     */
                    'kyc_settings' =>
                        $data['kycSettings'] ?? [],

                    /*
                     * True only when Fonbnk says the KYC is approved.
                     */
                    'is_approved' =>
                        $this->isApproved($data),

                    /*
                     * Whether the user has actually passed
                     * the required aggregate tier.
                     */
                    'has_required_kyc' =>
                        $this->hasRequiredKyc($data),

                    'raw' => $data,
                ],

                'provider' => 'fonbnk',
                'raw' => $result['raw'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('FonbnkKycService::status exception', [
                'user_id' => $user->id,
                'country' => $country,
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'provider_exception',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Determine the KYC tier required for a specific order.
     *
     * This evaluates BOTH:
     *
     * 1. Aggregate rule from requiredKycType
     * 2. Per-order rules from kycSettings
     *
     * Example:
     *
     * payout + crypto + $150
     *
     * If settings say:
     * payout/crypto/basic    0 - 100
     * payout/crypto/advanced 100 - Infinity
     *
     * then advanced is required.
     */
    public function requiredKycForOrder(
        User $user,
        string $countryIsoCode,
        string $operationType,
        string $currencyType,
        float $amountUsd
    ): array {
        $status = $this->status(
            $user,
            $countryIsoCode
        );

        if (!($status['ok'] ?? false)) {
            return $status;
        }

        $data = $status['data'] ?? [];

        $passedKycType =
            $data['passed_kyc_type'] ?? null;

        $aggregateRequired =
            $data['required_kyc_type'] ?? null;

        $settings =
            $data['kyc_settings'] ?? [];

        /*
         * Start with aggregate requirement.
         */
        $requiredType = $aggregateRequired;

        /*
         * Evaluate per-order rules.
         */
        foreach ($settings as $setting) {

            if (!is_array($setting)) {
                continue;
            }

            if (
                strtolower((string) ($setting['operationType'] ?? ''))
                !== strtolower($operationType)
            ) {
                continue;
            }

            if (
                strtolower((string) ($setting['currencyType'] ?? ''))
                !== strtolower($currencyType)
            ) {
                continue;
            }

            /*
             * Only evaluate settings containing min/max.
             *
             * Aggregate settings have maxOrdersCount
             * and/or maxAmountUsd instead.
             */
            $hasMin = array_key_exists('min', $setting);
            $hasMax = array_key_exists('max', $setting);

            if (!$hasMin && !$hasMax) {
                continue;
            }

            $min = $hasMin
                ? (float) $setting['min']
                : 0;

            $max = $hasMax
                ? $this->numericMax($setting['max'])
                : INF;

            /*
             * Fonbnk uses [min, max)
             *
             * Therefore:
             *
             * amount >= min
             * amount < max
             */
            if (
                $amountUsd >= $min &&
                $amountUsd < $max
            ) {
                $settingType =
                    $setting['type'] ?? null;

                $requiredType =
                    $this->highestKycType(
                        $requiredType,
                        $settingType
                    );
            }
        }

        $requiredType =
            $requiredType ?: null;

        $passed =
            $this->kycRank($passedKycType);

        $required =
            $this->kycRank($requiredType);

        $sufficient =
            $passed >= $required;

        /*
         * Find the actual document Fonbnk expects.
         */
        $document = null;

        if (!$sufficient && $requiredType !== null) {
            $document =
                $this->findDocumentForType(
                    $data['kyc_documents'] ?? [],
                    $requiredType
                );
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'message' => $sufficient
                ? 'User has sufficient KYC for this order.'
                : 'User must complete additional KYC before this order.',

            'data' => [
                'passed_kyc_type' => $passedKycType,
                'aggregate_required_kyc_type' =>
                    $aggregateRequired,

                'order_required_kyc_type' =>
                    $requiredType,

                'has_required_kyc' =>
                    $sufficient,

                'operation_type' =>
                    strtolower($operationType),

                'currency_type' =>
                    strtolower($currencyType),

                'amount_usd' =>
                    $amountUsd,

                'kyc_document' =>
                    $document,

                'required_fields' =>
                    $document['requiredFields'] ?? [],

                'current_kyc_status' =>
                    $data['current_kyc_status'] ?? null,

                'reached_kyc_limit' =>
                    $data['reached_kyc_limit'] ?? false,
            ],

            'provider' => 'fonbnk',
            'raw' => $status['raw'] ?? null,
        ];
    }

    /**
     * Check whether a user is already approved.
     *
     * Note:
     * Approval alone is not necessarily enough.
     * The passed KYC tier must also satisfy the required tier.
     */
    public function isApprovedForCountry(
        User $user,
        string $countryIsoCode
    ): bool {
        $result = $this->status(
            $user,
            $countryIsoCode
        );

        if (!($result['ok'] ?? false)) {
            return false;
        }

        return
            $this->isApproved(
                $result['data']['raw'] ?? []
            )
            &&
            $this->hasRequiredKyc(
                $result['data']['raw'] ?? []
            );
    }

    /**
     * Submit KYC to Fonbnk.
     */
    public function submit(User $user, array $input): array
    {
        $country = strtoupper(
            trim(
                (string) (
                    $input['country']
                    ?? $input['country_iso_code']
                    ?? ''
                )
            )
        );

        $documentId = (string) (
            $input['document_id']
            ?? $input['documentId']
            ?? ''
        );

        $userFields =
            $input['user_fields']
            ?? $input['userFields']
            ?? [];

        if (!is_array($userFields)) {
            $userFields = [];
        }

        $email = trim((string) $user->email);

        if ($email === '') {
            return $this->error(
                'missing_email',
                'User email is required for Fonbnk KYC.',
                422
            );
        }

        if (!$this->validCountry($country)) {
            return $this->error(
                'invalid_country',
                'Country must be a 2-letter ISO code.',
                422
            );
        }

        if ($documentId === '') {
            return $this->error(
                'missing_document_id',
                'document_id is required.',
                422
            );
        }

        /*
         * Make sure email always comes from your authenticated
         * user rather than being trusted from the request.
         */
        $userFields['email'] = $email;

        /*
         * Validate images when supplied.
         */
        if (isset($userFields['images'])) {

            $imageValidation =
                $this->validateImages(
                    $userFields['images']
                );

            if (!($imageValidation['ok'] ?? false)) {
                return $imageValidation;
            }
        }

        /*
         * Verify that this document actually exists in
         * Fonbnk's current country configuration.
         */
        try {

            $kycState =
                $this->fonbnk->getUserKyc(
                    $email,
                    $country
                );

            if (!($kycState['ok'] ?? false)) {
                return $kycState;
            }

            $kycData =
                is_array($kycState['data'] ?? null)
                    ? $kycState['data']
                    : [];

            $document =
                $this->findDocumentById(
                    $kycData['kycDocuments'] ?? [],
                    $documentId
                );

            if (!$document) {
                return $this->error(
                    'invalid_document',
                    'The supplied KYC document is not available for this country.',
                    422
                );
            }

            /*
             * Validate fields according to Fonbnk's
             * requiredFields definition.
             */
            $fieldValidation =
                $this->validateRequiredFields(
                    $document['requiredFields'] ?? [],
                    $userFields
                );

            if (!($fieldValidation['ok'] ?? false)) {
                return $fieldValidation;
            }

            /*
             * Check whether another KYC submission is already
             * pending.
             */
            if (
                ($kycData['reachedKycLimit'] ?? false) === true
            ) {
                return $this->error(
                    'kyc_limit_reached',
                    'The user has too many pending KYC submissions. Please wait for the existing submission to resolve.',
                    422
                );
            }

            $payload = [
                'userEmail' => $email,
                'countryIsoCode' => $country,
                'documentId' => $documentId,
                'userFields' => $userFields,
            ];

            $result =
                $this->fonbnk->submitUserKyc(
                    $payload
                );

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $data =
                is_array($result['data'] ?? null)
                    ? $result['data']
                    : [];

            return [
                'ok' => true,
                'status' => 'ok',

                'message' =>
                    $result['message']
                    ?? 'Fonbnk KYC submitted successfully',

                'http_status' =>
                    (int) ($result['http_status'] ?? 200),

                'data' => [
                    'email' => $email,
                    'country_iso_code' => $country,

                    'current_kyc_status' =>
                        $data['currentKycStatus'] ?? null,

                    'current_kyc_type' =>
                        $data['currentKycType'] ?? null,

                    'current_kyc_phase' =>
                        $data['currentKycPhase'] ?? null,

                    'passed_kyc_type' =>
                        $data['passedKycType'] ?? null,

                    'required_kyc_type' =>
                        $data['requiredKycType'] ?? null,

                    'is_approved' =>
                        $this->isApproved($data),

                    'raw' => $data,
                ],

                'provider' => 'fonbnk',
                'raw' => $result['raw'] ?? null,
            ];

        } catch (Throwable $e) {

            Log::error(
                'FonbnkKycService::submit exception',
                [
                    'user_id' => $user->id,
                    'country' => $country,
                    'document_id' => $documentId,
                    'message' => $e->getMessage(),
                ]
            );

            return $this->error(
                'provider_exception',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Poll Fonbnk until KYC is approved/rejected/invalid.
     *
     * IMPORTANT:
     * Do not keep a PHP request open for a long period in production.
     * Prefer queue/job polling or frontend polling.
     */
    public function waitForApproval(
        User $user,
        string $countryIsoCode,
        int $attempts = 10,
        int $sleepSeconds = 3
    ): array {

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {

            $result =
                $this->status(
                    $user,
                    $countryIsoCode
                );

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $data =
                $result['data'] ?? [];

            $status =
                strtolower(
                    (string) (
                        $data['current_kyc_status']
                        ?? ''
                    )
                );

            if ($status === 'approved') {
                return [
                    'ok' => true,
                    'status' => 'approved',
                    'message' => 'Fonbnk KYC approved.',
                    'data' => $data,
                    'provider' => 'fonbnk',
                ];
            }

            if (
                in_array(
                    $status,
                    ['rejected', 'invalid'],
                    true
                )
            ) {
                return [
                    'ok' => false,
                    'status' => $status,
                    'message' =>
                        $data['current_kyc_status_description']
                        ?? "Fonbnk KYC {$status}.",
                    'data' => $data,
                    'http_status' => 422,
                    'provider' => 'fonbnk',
                ];
            }

            if ($attempt < $attempts) {
                sleep($sleepSeconds);
            }
        }

        return $this->error(
            'kyc_pending',
            'Fonbnk KYC is still pending. Please check the KYC status again later.',
            202
        );
    }

    /**
     * Generate Fonbnk user tokens.
     */
    public function tokens(
        User $user,
        string $countryIsoCode
    ): array {

        $country =
            strtoupper(trim($countryIsoCode));

        $email =
            trim((string) $user->email);

        if ($email === '') {
            return $this->error(
                'missing_email',
                'User email is required.',
                422
            );
        }

        if (!$this->validCountry($country)) {
            return $this->error(
                'invalid_country',
                'Country must be a 2-letter ISO code.',
                422
            );
        }

        try {

            $result =
                $this->fonbnk->generateUserTokens(
                    $email,
                    $country
                );

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $data =
                is_array($result['data'] ?? null)
                    ? $result['data']
                    : [];

            return [
                'ok' => true,
                'status' => 'ok',

                'message' =>
                    'Fonbnk user tokens generated successfully',

                'http_status' =>
                    (int) ($result['http_status'] ?? 200),

                'data' => [
                    'access_token' =>
                        $data['accessToken'] ?? null,

                    'refresh_token' =>
                        $data['refreshToken'] ?? null,
                ],

                'provider' => 'fonbnk',
                'raw' => $result['raw'] ?? null,
            ];

        } catch (Throwable $e) {

            Log::error(
                'FonbnkKycService::tokens exception',
                [
                    'user_id' => $user->id,
                    'country' => $country,
                    'message' => $e->getMessage(),
                ]
            );

            return $this->error(
                'provider_exception',
                $e->getMessage(),
                500
            );
        }
    }

    /**
     * Determine whether Fonbnk has approved the current KYC.
     *
     * Fonbnk documentation specifies "approved".
     */
    protected function isApproved(array $data): bool
    {
        return strtolower(
            (string) (
                $data['currentKycStatus']
                ?? ''
            )
        ) === 'approved';
    }

    /**
     * Check aggregate KYC requirement.
     */
    protected function hasRequiredKyc(array $data): bool
    {
        $passed =
            $this->kycRank(
                $data['passedKycType'] ?? null
            );

        $required =
            $this->kycRank(
                $data['requiredKycType'] ?? null
            );

        return $passed >= $required;
    }

    /**
     * KYC hierarchy.
     *
     * null < basic < advanced
     */
    protected function kycRank(?string $type): int
    {
        return match (strtolower((string) $type)) {
            'advanced' => 2,
            'basic' => 1,
            default => 0,
        };
    }

    /**
     * Return the highest KYC type.
     */
    protected function highestKycType(
        ?string $current,
        ?string $candidate
    ): ?string {

        if ($candidate === null) {
            return $current;
        }

        if ($current === null) {
            return strtolower($candidate);
        }

        return $this->kycRank($candidate)
            > $this->kycRank($current)
            ? strtolower($candidate)
            : strtolower($current);
    }

    /**
     * Convert max values such as "Infinity".
     */
    protected function numericMax(mixed $value): float
    {
        if (
            is_string($value)
            && strtolower(trim($value)) === 'infinity'
        ) {
            return INF;
        }

        return (float) $value;
    }

    /**
     * Find KYC document matching the required type.
     */
    protected function findDocumentForType(
        array $documents,
        string $type
    ): ?array {

        foreach ($documents as $document) {

            if (
                strtolower(
                    (string) ($document['type'] ?? '')
                ) === strtolower($type)
            ) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Find KYC document by Fonbnk document ID.
     */
    protected function findDocumentById(
        array $documents,
        string $documentId
    ): ?array {

        foreach ($documents as $document) {

            if (
                (string) ($document['_id'] ?? '')
                === $documentId
            ) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Validate Fonbnk requiredFields.
     */
    protected function validateRequiredFields(
        array $requiredFields,
        array $userFields
    ): array {

        $missing = [];

        foreach ($requiredFields as $field) {

            if (!is_array($field)) {
                continue;
            }

            if (
                ($field['required'] ?? false) !== true
            ) {
                continue;
            }

            $key =
                $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            if (
                !array_key_exists($key, $userFields)
                ||
                $this->emptyValue($userFields[$key])
            ) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            return $this->error(
                'missing_required_fields',
                'Required KYC fields are missing.',
                422,
                [
                    'missing_fields' => $missing,
                ]
            );
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'data' => null,
        ];
    }

    /**
     * Validate Fonbnk image structure.
     *
     * Expected:
     *
     * [
     *   [
     *      'image_type_id' => 2,
     *      'image' => '<base64>'
     *   ],
     *   ...
     * ]
     */
    protected function validateImages(mixed $images): array
    {
        if (!is_array($images)) {
            return $this->error(
                'invalid_images',
                'images must be an array.',
                422
            );
        }

        $allowedTypes = [2, 3, 7];

        foreach ($images as $index => $image) {

            if (!is_array($image)) {
                return $this->error(
                    'invalid_image',
                    "Image at index {$index} must be an object.",
                    422
                );
            }

            $type =
                (int) ($image['image_type_id'] ?? 0);

            if (!in_array($type, $allowedTypes, true)) {
                return $this->error(
                    'invalid_image_type',
                    "Invalid image_type_id at index {$index}.",
                    422
                );
            }

            $base64 =
                $image['image'] ?? null;

            if (
                !is_string($base64)
                || trim($base64) === ''
            ) {
                return $this->error(
                    'invalid_image_data',
                    "Image at index {$index} must contain a base64-encoded image.",
                    422
                );
            }

            /*
             * Remove optional data URI prefix.
             *
             * Example:
             * data:image/jpeg;base64,XXXX
             */
            if (
                str_contains(
                    $base64,
                    'base64,'
                )
            ) {
                $base64 =
                    substr(
                        $base64,
                        strpos(
                            $base64,
                            'base64,'
                        ) + 7
                    );
            }

            if (
                base64_decode(
                    $base64,
                    true
                ) === false
            ) {
                return $this->error(
                    'invalid_base64_image',
                    "Image at index {$index} is not valid base64.",
                    422
                );
            }
        }

        return [
            'ok' => true,
            'status' => 'ok',
        ];
    }

    protected function emptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    protected function validCountry(string $country): bool
    {
        return strlen($country) === 2
            && ctype_alpha($country);
    }

    protected function error(
        string $status,
        string $message,
        int $httpStatus = 400,
        mixed $data = null
    ): array {

        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'http_status' => $httpStatus,
            'provider' => 'fonbnk',
            'raw' => null,
        ];
    }
}