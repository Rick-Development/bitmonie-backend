<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SudoCardService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.sudo.base_url', 'https://api.sudo.africa'),
            '/'
        );

        $this->apiKey = (string) config('services.sudo.api_key');
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CLIENT
    |--------------------------------------------------------------------------
    */

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout($this->timeout);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function id(string $id): string
    {
        return rawurlencode($id);
    }

    protected function normalizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim($phone);

        $phone = preg_replace(
            '/[\s\-\(\)]/',
            '',
            $phone
        );

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '234')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+234' . substr($phone, 1);
        }

        return $phone;
    }

    protected function removeEmptyValues(array $payload): array
    {
        return array_filter(
            $payload,
            function ($value) {
                if ($value === null || $value === '') {
                    return false;
                }

                if (is_array($value) && empty($value)) {
                    return false;
                }

                return true;
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PAYLOAD
    |--------------------------------------------------------------------------
    */

    protected function normalizeCustomerPayload(array $payload): array
    {
        if (!empty($payload['phoneNumber'])) {
            $payload['phoneNumber'] =
                $this->normalizePhoneNumber(
                    $payload['phoneNumber']
                );
        }

        if (
            isset($payload['billingAddress']) &&
            is_array($payload['billingAddress'])
        ) {
            $payload['billingAddress'] =
                $this->removeEmptyValues(
                    $payload['billingAddress']
                );
        }

        if (
            ($payload['type'] ?? null) === 'individual' &&
            isset($payload['individual']) &&
            is_array($payload['individual'])
        ) {
            $individual = $payload['individual'];

            if (
                isset($individual['identity']) &&
                is_array($individual['identity'])
            ) {
                $identity = $this->removeEmptyValues(
                    $individual['identity']
                );

                if (
                    !empty($identity['type']) &&
                    !empty($identity['number'])
                ) {
                    $individual['identity'] = $identity;
                } else {
                    unset($individual['identity']);
                }
            }

            $payload['individual'] =
                $this->removeEmptyValues($individual);
        }

        return $this->removeEmptyValues($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP REQUEST
    |--------------------------------------------------------------------------
    */

    protected function request(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = []
    ): array {
        $method = strtoupper($method);

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        Log::debug('Sudo API request', [
            'method'   => $method,
            'endpoint' => $endpoint,
            'payload'  => $this->sanitizeLogPayload($payload),
            'query'    => $query,
        ]);

        try {
            $request = $this->http();

            $response = match ($method) {
                'GET' => $request->get($url, $query),

                'POST' => $request->post(
                    $url,
                    $payload
                ),

                'PUT' => $request->put(
                    $url,
                    $payload
                ),

                'PATCH' => $request->patch(
                    $url,
                    $payload
                ),

                'DELETE' => $request->delete(
                    $url,
                    $payload
                ),

                default => throw new \InvalidArgumentException(
                    "Unsupported HTTP method: {$method}"
                ),
            };

            /*
             * Handle empty responses safely.
             */
            if ($response->status() === 204) {
                return [
                    'success'    => true,
                    'statusCode' => 204,
                    'message'    => 'Request successful.',
                    'data'       => null,
                    'response'   => null,
                ];
            }

            $data = $response->json();

            if (!is_array($data)) {
                $data = [
                    'raw' => $response->body(),
                ];
            }

            Log::debug('Sudo API response', [
                'method'     => $method,
                'endpoint'   => $endpoint,
                'status'     => $response->status(),
                'successful' => $response->successful(),
                'response'   => $this->sanitizeLogPayload($data),
            ]);

            if (!$response->successful()) {
                Log::warning('Sudo API request failed', [
                    'method'   => $method,
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                    'response' => $this->sanitizeLogPayload($data),
                ]);

                return [
                    'success'    => false,
                    'statusCode' => $response->status(),
                    'message'    => $this->extractErrorMessage($data),
                    'errors'     => $this->extractErrors($data),
                    'data'       => $data,
                    'response'   => $data,
                ];
            }

            return [
                'success'    => true,
                'statusCode' => $response->status(),
                'message'    => $data['message']
                    ?? 'Sudo request successful.',
                'data'       => $data['data']
                    ?? $data,
                'response'   => $data,
            ];

        } catch (Throwable $e) {
            Log::error('Sudo API exception', [
                'method'   => $method,
                'endpoint' => $endpoint,
                'url'      => $url,
                'error'    => $e->getMessage(),
                'class'    => get_class($e),
            ]);

            return [
                'success'    => false,
                'statusCode' => null,
                'message'    => 'Unable to communicate with Sudo.',
                'errors'     => null,
                'data'       => null,
                'response'   => null,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR HANDLING
    |--------------------------------------------------------------------------
    */

    protected function extractErrorMessage(array $data): string
    {
        if (!empty($data['message'])) {

            if (is_string($data['message'])) {
                return $data['message'];
            }

            if (is_array($data['message'])) {
                $messages = [];

                foreach ($data['message'] as $message) {

                    if (is_string($message)) {
                        $messages[] = $message;
                        continue;
                    }

                    if (!is_array($message)) {
                        continue;
                    }

                    if (!empty($message['message'])) {
                        $messages[] = $message['message'];
                    }

                    if (!empty($message['property'])) {
                        $messages[] = $message['property'];
                    }

                    if (!empty($message['constraints'])) {
                        $constraints = $message['constraints'];

                        if (is_array($constraints)) {
                            foreach ($constraints as $constraint) {
                                if (is_string($constraint)) {
                                    $messages[] = $constraint;
                                }
                            }
                        }
                    }
                }

                if (!empty($messages)) {
                    return implode(
                        '; ',
                        array_unique($messages)
                    );
                }

                return json_encode(
                    $data['message'],
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE
                );
            }
        }

        if (!empty($data['error'])) {
            return is_string($data['error'])
                ? $data['error']
                : 'Sudo API request failed.';
        }

        return 'Sudo API request failed.';
    }

    protected function extractErrors(array $data): ?array
    {
        if (
            isset($data['errors']) &&
            is_array($data['errors'])
        ) {
            return $data['errors'];
        }

        if (
            isset($data['message']) &&
            is_array($data['message'])
        ) {
            return $data['message'];
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | LOG SANITIZATION
    |--------------------------------------------------------------------------
    */

    protected function sanitizeLogPayload(array $payload): array
    {
        $sensitiveKeys = [
            'number',
            'accountNumber',
            'beneficiaryAccountNumber',
            'bvn',
            'identity',
            'authorization',
            'authorizationHeader',
            'apiKey',
            'token',
            'cardNumber',
            'pan',
            'cvv',
            'pin',
            'password',
            'secret',
            'accessToken',
            'refreshToken',
        ];

        $sensitiveKeys = array_map(
            'strtolower',
            $sensitiveKeys
        );

        $sanitize = function (
            $value,
            $key = null
        ) use (
            &$sanitize,
            $sensitiveKeys
        ) {
            if (
                $key !== null &&
                in_array(
                    strtolower((string) $key),
                    $sensitiveKeys,
                    true
                )
            ) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                $result = [];

                foreach ($value as $childKey => $childValue) {
                    $result[$childKey] =
                        $sanitize(
                            $childValue,
                            $childKey
                        );
                }

                return $result;
            }

            return $value;
        };

        return $sanitize($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMERS / HOLDERS
    |--------------------------------------------------------------------------
    */

    public function createCustomer(array $payload): array
    {
        return $this->request(
            'POST',
            '/customers',
            $this->normalizeCustomerPayload($payload)
        );
    }

    public function getCustomers(array $query = []): array
    {
        return $this->request(
            'GET',
            '/customers',
            [],
            $query
        );
    }

    public function getCustomer(string $customerId): array
    {
        return $this->request(
            'GET',
            '/customers/' . $this->id($customerId)
        );
    }

    public function updateCustomer(
        string $customerId,
        array $payload
    ): array {
        return $this->request(
            'PUT',
            '/customers/' . $this->id($customerId),
            $this->normalizeCustomerPayload($payload)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FUNDING SOURCES
    |--------------------------------------------------------------------------
    */

    public function createFundingSource(
        array $payload = []
    ): array {
        $payload = array_merge([
            'type'   => 'default',
            'status' => 'active',
        ], $payload);

        return $this->request(
            'POST',
            '/fundingsources',
            $this->removeEmptyValues($payload)
        );
    }

    public function getFundingSources(
        array $query = []
    ): array {
        return $this->request(
            'GET',
            '/fundingsources',
            [],
            $query
        );
    }

    public function getFundingSource(
        string $fundingSourceId
    ): array {
        return $this->request(
            'GET',
            '/fundingsources/' .
            $this->id($fundingSourceId)
        );
    }

    public function updateFundingSource(
        string $fundingSourceId,
        array $payload
    ): array {
        return $this->request(
            'PUT',
            '/fundingsources/' .
            $this->id($fundingSourceId),
            $payload
        );
    }

    public function deleteFundingSource(
        string $fundingSourceId
    ): array {
        return $this->request(
            'DELETE',
            '/fundingsources/' .
            $this->id($fundingSourceId)
        );
    }

    public function createDefaultFundingSource(): array
    {
        return $this->createFundingSource([
            'type'   => 'default',
            'status' => 'active',
        ]);
    }

    public function createAccountFundingSource(): array
    {
        return $this->createFundingSource([
            'type'   => 'account',
            'status' => 'active',
        ]);
    }

    public function createGatewayFundingSource(
        string $url,
        string $authorizationHeader,
        bool $authorizeByDefault = false
    ): array {
        return $this->createFundingSource([
            'type'   => 'gateway',
            'status' => 'active',

            'jitGateway' => [
                'url' =>
                    $url,

                'authorizationHeader' =>
                    $authorizationHeader,

                'authorizeByDefault' =>
                    $authorizeByDefault,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTS
    |--------------------------------------------------------------------------
    */

    public function createAccount(
        array $payload = []
    ): array {
        $payload = array_merge([
            'type'        => 'account',
            'currency'    => 'NGN',
            'accountType' => 'Savings',
        ], $payload);

        return $this->request(
            'POST',
            '/accounts',
            $this->removeEmptyValues($payload)
        );
    }

    public function createWallet(
        string $customerId,
        string $currency = 'NGN',
        string $accountType = 'Savings'
    ): array {
        return $this->createAccount([
            'type'        => 'wallet',
            'currency'    => $currency,
            'accountType' => $accountType,
            'customerId'  => $customerId,
        ]);
    }

    public function getAccounts(
        array $query = []
    ): array {
        return $this->request(
            'GET',
            '/accounts',
            [],
            $query
        );
    }

    public function getAccount(
        string $accountId
    ): array {
        return $this->request(
            'GET',
            '/accounts/' . $this->id($accountId)
        );
    }

    public function updateAccount(
        string $accountId,
        array $payload
    ): array {
        return $this->request(
            'PUT',
            '/accounts/' . $this->id($accountId),
            $payload
        );
    }

    public function getAccountBalance(
        string $accountId
    ): array {
        return $this->request(
            'GET',
            '/accounts/' .
            $this->id($accountId) .
            '/balance'
        );
    }

    public function getAccountTransactions(
        string $accountId,
        array $query = []
    ): array {
        return $this->request(
            'GET',
            '/accounts/' .
            $this->id($accountId) .
            '/transactions',
            [],
            $query
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BANKS
    |--------------------------------------------------------------------------
    */

    public function getBanks(
        string $country = 'NG'
    ): array {
        return $this->request(
            'GET',
            '/accounts/banks',
            [],
            [
                'country' => $country,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NAME ENQUIRY
    |--------------------------------------------------------------------------
    */

    public function nameEnquiry(
        string $bankCode,
        string $accountNumber
    ): array {
        return $this->request(
            'POST',
            '/accounts/transfer/name-enquiry',
            [
                'bankCode'      => $bankCode,
                'accountNumber' => $accountNumber,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSFERS
    |--------------------------------------------------------------------------
    */


public function fundTransfer(array $payload): array
{
    /*
    |--------------------------------------------------------------------------
    | Validate required fields
    |--------------------------------------------------------------------------
    */

    if (empty($payload['debitAccountId'])) {
        return [
            'success' => false,
            'message' => 'debitAccountId is required.',
            'statusCode' => 422,
        ];
    }

    if (
        !isset($payload['amount']) ||
        !is_numeric($payload['amount']) ||
        (float) $payload['amount'] <= 0
    ) {
        return [
            'success' => false,
            'message' => 'amount must be greater than zero.',
            'statusCode' => 422,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Destination
    |--------------------------------------------------------------------------
    */

    $hasCreditAccount = !empty($payload['creditAccountId']);

    $hasBeneficiary =
        !empty($payload['beneficiaryBankCode']) &&
        !empty($payload['beneficiaryAccountNumber']);

    if (!$hasCreditAccount && !$hasBeneficiary) {
        return [
            'success' => false,
            'message' =>
                'Provide either creditAccountId or beneficiaryBankCode and beneficiaryAccountNumber.',
            'statusCode' => 422,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build ONLY the fields expected by Sudo
    |--------------------------------------------------------------------------
    */

    $transferPayload = [
        'debitAccountId' => $payload['debitAccountId'],
        'amount'         => (float) $payload['amount'],
        'narration'      => $payload['narration'] ?? 'Crypto card transfer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Internal Sudo account transfer
    |--------------------------------------------------------------------------
    */

    if ($hasCreditAccount) {
        $transferPayload['creditAccountId'] =
            $payload['creditAccountId'];
    }

    /*
    |--------------------------------------------------------------------------
    | External bank transfer
    |--------------------------------------------------------------------------
    */

    if (!$hasCreditAccount && $hasBeneficiary) {
        $transferPayload['beneficiaryBankCode'] =
            $payload['beneficiaryBankCode'];

        $transferPayload['beneficiaryAccountNumber'] =
            $payload['beneficiaryAccountNumber'];
    }

    /*
    |--------------------------------------------------------------------------
    | Payment reference
    |--------------------------------------------------------------------------
    */

    if (!empty($payload['paymentReference'])) {
        $transferPayload['paymentReference'] =
            $payload['paymentReference'];
    }

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */

    

    return $this->request(
        'POST',
        '/accounts/transfer',
        $transferPayload
    );
}

    public function transferToAccount(
        string $debitAccountId,
        string $creditAccountId,
        float $amount,
        ?string $narration = null,
        ?string $paymentReference = null
    ): array {
        return $this->fundTransfer([
            'debitAccountId'   => $debitAccountId,
            'amount'           => $amount,
            'creditAccountId'  => $creditAccountId,
            'narration'        => $narration,
            'paymentReference' => $paymentReference,
        ]);
    }

    public function transferToBank(
        string $debitAccountId,
        string $bankCode,
        string $accountNumber,
        float $amount,
        ?string $narration = null,
        ?string $paymentReference = null
    ): array {
        return $this->fundTransfer([
            'debitAccountId' =>
                $debitAccountId,

            'amount' =>
                $amount,

            'beneficiaryBankCode' =>
                $bankCode,

            'beneficiaryAccountNumber' =>
                $accountNumber,

            'narration' =>
                $narration,

            'paymentReference' =>
                $paymentReference,
        ]);
    }

    public function getTransferStatus(
        string $transferId
    ): array {
        return $this->request(
            'GET',
            '/accounts/transfers/' .
            $this->id($transferId)
        );
    }

    public function getTransferRate(
        string $currencyPair
    ): array {
        return $this->request(
            'GET',
            '/accounts/transfers/rate/' .
            $this->id($currencyPair)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CARDS
    |--------------------------------------------------------------------------
    */





public function createCard(array $payload): array
{
    try {
        /*
         * Remove only null values first.
         *
         * Do NOT use removeEmptyValues() here because Sudo
         * requires some fields to remain as empty arrays.
         */
        $payload = $this->removeNullValues($payload);

        /*
         * Normalize card brand.
         *
         * Accepted:
         *   visa
         *   VISA
         *   Visa
         *   mastercard
         *   MASTERCARD
         *   MasterCard
         *   verve
         *   VERVE
         *   Verve
         *   afrigo
         *   AFRIGO
         *   AfriGo
         *
         * Sent to Sudo:
         *   Visa
         *   Mastercard
         *   Verve
         *   AfriGo
         */
        if (!isset($payload['brand']) || trim((string) $payload['brand']) === '') {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Card brand is required.',
                'errors' => [
                    'brand' => [
                        'The brand field is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        $normalizedBrand = $this->normalizeCardBrand(
            $payload['brand']
        );

        if ($normalizedBrand === null) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Invalid card brand.',
                'errors' => [
                    'brand' => [
                        'Supported card brands are Visa, Mastercard, Verve and AfriGo.'
                    ],
                ],
                'data' => null,
            ];
        }

        $payload['brand'] = $normalizedBrand;

        /*
         * Defaults.
         */
        $payload['issuerCountry'] ??= 'NGA';
        $payload['enable2FA'] ??= true;

        /*
         * Always use the configured Sudo settlement debit account.
         */
        $payload['debitAccountId'] =
            config('services.sudo.settlement_debit_account_id');

        /*
         * Virtual cards require debitAccountId.
         */
        if (
            ($payload['type'] ?? null) === 'virtual' &&
            empty($payload['debitAccountId'])
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' =>
                    'debitAccountId is required for virtual cards.',
                'errors' => [
                    'debitAccountId' => [
                        'A valid Sudo account ID is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Physical cards require a card number.
         */
        if (
            ($payload['type'] ?? null) === 'physical' &&
            empty($payload['number'])
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' =>
                    'number is required for physical cards.',
                'errors' => [
                    'number' => [
                        'A valid physical card number is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Spending controls.
         *
         * Sudo requires:
         *   channels
         *   allowedCategories
         *   blockedCategories
         *   spendingLimits
         *
         * Keep empty arrays because they are valid values
         * and Sudo expects these properties to exist.
         */
        if (
            isset($payload['spendingControls']) &&
            is_array($payload['spendingControls'])
        ) {
            $spendingControls = $payload['spendingControls'];

            $spendingControls['channels'] ??= [
                'atm' => true,
                'pos' => true,
                'web' => true,
                'mobile' => true,
            ];

            $spendingControls['allowedCategories'] ??= [];

            $spendingControls['blockedCategories'] ??= [];

            $spendingControls['spendingLimits'] ??= [];

            /*
             * Remove null values inside spendingControls,
             * but preserve empty arrays.
             */
            $payload['spendingControls'] =
                $this->removeNullValues($spendingControls);
        }

        /*
         * Do not send an invalid amount.
         */
        if (
            isset($payload['amount']) &&
            (
                $payload['amount'] === null ||
                (
                    is_numeric($payload['amount']) &&
                    (float) $payload['amount'] < 1
                )
            )
        ) {
            unset($payload['amount']);
        }

        /*
         * Final null cleanup.
         *
         * This intentionally uses removeNullValues()
         * rather than removeEmptyValues() so that:
         *
         * allowedCategories: []
         * blockedCategories: []
         *
         * are preserved.
         */
        $payload = $this->removeNullValues($payload);

        Log::info('Sudo create card request', [
            'base_url' => $this->baseUrl,
            'payload' => $this->sanitizeLogPayload($payload),
        ]);

        return $this->request(
            'POST',
            '/cards',
            $payload
        );

    } catch (Throwable $e) {
        Log::error('Sudo create card failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'statusCode' => null,
            'message' => 'Unable to create card.',
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Normalize Sudo card brand.
 *
 * Input is case-insensitive.
 *
 * visa       -> Visa
 * mastercard -> Mastercard
 * verve      -> Verve
 * afrigo     -> AfriGo
 */
protected function normalizeCardBrand(?string $brand): ?string
{
    if ($brand === null) {
        return null;
    }

    return match (strtolower(trim($brand))) {
        'visa'       => 'Visa',
        'mastercard' => 'MasterCard',
        'verve'      => 'Verve',
        'afrigo'     => 'AfriGo',
        default      => null,
    };
}

/**
 * Remove null values recursively while preserving
 * empty arrays.
 */
protected function removeNullValues(array $data): array
{
    foreach ($data as $key => $value) {

        if (is_array($value)) {

            $value = $this->removeNullValues($value);

            $data[$key] = $value;

            continue;
        }

        if ($value === null) {
            unset($data[$key]);
        }
    }

    return $data;
}








    public function getCards(
        array $query = []
    ): array {
        return $this->request(
            'GET',
            '/cards',
            [],
            $query
        );
    }

    public function getCustomerCards(
        string $customerId,
        array $query = []
    ): array {
        return $this->request(
            'GET',
            '/cards/customer/' .
            $this->id($customerId),
            [],
            $query
        );
    }

    public function getCard(
        string $cardId
    ): array {
        return $this->request(
            'GET',
            '/card/' . $this->id($cardId)
        );
    }

    public function updateCard(
        string $cardId,
        array $payload
    ): array {
        return $this->request(
            'PUT',
            '/card/' . $this->id($cardId),
            $payload
        );
    }

    public function sendCardPin(
        string $cardId
    ): array {
        return $this->request(
            'PUT',
            '/card/' .
            $this->id($cardId) .
            '/send-pin'
        );
    }

    public function changeCardPin(
        string $cardId,
        array $payload
    ): array {
        return $this->request(
            'PUT',
            '/card/' .
            $this->id($cardId) .
            '/pin',
            $payload
        );
    }

    public function enrollCard2FA(
        string $cardId
    ): array {
        return $this->request(
            'PUT',
            '/card/' .
            $this->id($cardId) .
            '/enroll2fa'
        );
    }

    public function generateCardToken(
        string $cardId
    ): array {
        return $this->request(
            'GET',
            '/card/' .
            $this->id($cardId) .
            '/token'
        );
    }

    public function digitalizeCard(
        string $cardId
    ): array {
        return $this->request(
            'GET',
            '/card/digitalize/' .
            $this->id($cardId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CARD TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function getCardTransactions(
        ?string $cardId = null,
        array $query = []
    ): array {
    
        if ($cardId) {
            return $this->request(
                'GET',
                '/cards/' .
                $this->id($cardId) .
                '/transactions',
                [],
                $query
            );
        }

        return $this->request(
            'GET',
            '/cards/transactions',
            [],
            $query
        );
    }

    public function getTransaction(
        string $transactionId
    ): array {
        return $this->request(
            'GET',
            '/cards/transactions/' .
            $this->id($transactionId)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SIMULATOR
    |--------------------------------------------------------------------------
    */

    public function simulatorFundAccount(
        float $amount,
        ?string $accountId = null,
        ?string $bankCode = null,
        ?string $accountNumber = null
    ): array {
        $payload = [
            'amount' => $amount,
        ];

        if ($accountId !== null) {
            $payload['accountId'] =
                $accountId;
        }

        if ($bankCode !== null) {
            $payload['bankCode'] =
                $bankCode;
        }

        if ($accountNumber !== null) {
            $payload['accountNumber'] =
                $accountNumber;
        }

        return $this->request(
            'POST',
            '/accounts/simulator/fund',
            $payload
        );
    }

    public function generateTestCard(): array
    {
        return $this->request(
            'GET',
            '/cards/simulator/generate'
        );
    }

    public function simulatorBalanceEnquiry(
        string $cardId,
        string $channel = 'web'
    ): array {
        return $this->request(
            'POST',
            '/cards/simulator/balance_enqiry',
            [
                'cardId'  => $cardId,
                'channel' => $channel,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GENERIC PROVIDER METHODS
    |--------------------------------------------------------------------------
    */

    public function providerCall(
        string $method,
        array $arguments = []
    ): array {
        if (!method_exists($this, $method)) {
            return [
                'success' => false,
                'statusCode' => 400,
                'message' =>
                    "Unsupported Sudo service method: {$method}",
                'errors' => null,
                'data' => null,
                'response' => null,
            ];
        }

        try {
            return $this->{$method}(...$arguments);
        } catch (Throwable $e) {
            Log::error(
                'Sudo provider call failed',
                [
                    'method' => $method,
                    'error'  => $e->getMessage(),
                ]
            );

            return [
                'success' => false,
                'statusCode' => null,
                'message' =>
                    'Unable to execute Sudo provider method.',
                'errors' => null,
                'data' => null,
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }


public function orderCard(array $payload): array
{
    try {
        /*
         * Sudo uses the configured settlement debit account
         * for card issuance. Do not allow the client to override it.
         */
        $debitAccountId = config(
            'services.sudo.settlement_debit_account_id'
        );

        if (empty($debitAccountId)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Sudo settlement debit account is not configured.',
                'errors' => [
                    'debitAccountId' => [
                        'The Sudo settlement debit account is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Customer is required by Sudo when ordering a single
         * customer's physical card.
         */
        if (empty($payload['customerId'])) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'customerId is required.',
                'errors' => [
                    'customerId' => [
                        'The Sudo customer ID is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Card brand.
         *
         * Supported input:
         *   verve
         *   afrigo
         *   master card
         *   mastercard
         *   visa
         *
         * Sent to Sudo:
         *   Verve
         *   AfriGo
         *   MasterCard
         *   Visa
         */
        if (
            !isset($payload['brand']) ||
            trim((string) $payload['brand']) === ''
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Card brand is required.',
                'errors' => [
                    'brand' => [
                        'The brand field is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        $brand = $this->normalizeCardOrderBrand(
            $payload['brand']
        );

        if ($brand === null) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Invalid card brand.',
                'errors' => [
                    'brand' => [
                        'Supported card brands are Verve, AfriGo, MasterCard and Visa.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Currency.
         */
        $currency = strtoupper(
            trim((string) ($payload['currency'] ?? 'NGN'))
        );

        if (!in_array($currency, ['NGN', 'USD'], true)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Invalid card currency.',
                'errors' => [
                    'currency' => [
                        'Supported currencies are NGN and USD.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Number of physical cards.
         */
        $allocation = $payload['allocation'] ?? 1;

        if (
            !is_numeric($allocation) ||
            (int) $allocation < 1
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'allocation must be at least 1.',
                'errors' => [
                    'allocation' => [
                        'The allocation must be at least 1.'
                    ],
                ],
                'data' => null,
            ];
        }

        $allocation = (int) $allocation;

        /*
         * Expedite.
         */
        $expedite = $payload['expedite'] ?? false;

        if (!is_bool($expedite)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'expedite must be a boolean.',
                'errors' => [
                    'expedite' => [
                        'The expedite field must be true or false.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Shipping method.
         */
        $shippingMethod = strtoupper(
            trim((string) ($payload['shippingMethod'] ?? 'NIPOST'))
        );

        if (!in_array($shippingMethod, ['NIPOST', 'DHL'], true)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'Invalid shipping method.',
                'errors' => [
                    'shippingMethod' => [
                        'Supported shipping methods are NIPOST and DHL.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Shipping address.
         */
        if (
            !isset($payload['shippingAddress']) ||
            !is_array($payload['shippingAddress'])
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'shippingAddress is required.',
                'errors' => [
                    'shippingAddress' => [
                        'A valid shipping address is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        $shippingAddress = $payload['shippingAddress'];

        foreach (
            ['line1', 'city', 'state', 'postalCode', 'country']
            as $field
        ) {
            if (
                !isset($shippingAddress[$field]) ||
                trim((string) $shippingAddress[$field]) === ''
            ) {
                return [
                    'success' => false,
                    'statusCode' => 422,
                    'message' => "{$field} is required in shippingAddress.",
                    'errors' => [
                        "shippingAddress.{$field}" => [
                            "The {$field} field is required."
                        ],
                    ],
                    'data' => null,
                ];
            }
        }

        $shippingAddress = $this->removeNullValues(
            $shippingAddress
        );

        /*
         * Card design.
         */
        $design = trim(
            (string) ($payload['design'] ?? 'SudoBlack')
        );

        if ($design === '') {
            $design = 'SudoBlack';
        }

        /*
         * Names printed on cards.
         */
        if (
            !isset($payload['nameOnCards']) ||
            !is_array($payload['nameOnCards']) ||
            count($payload['nameOnCards']) < 1
        ) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'nameOnCards is required.',
                'errors' => [
                    'nameOnCards' => [
                        'At least one card name is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        $nameOnCards = [];

        foreach ($payload['nameOnCards'] as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $nameOnCards[] = $name;
            }
        }

        if (empty($nameOnCards)) {
            return [
                'success' => false,
                'statusCode' => 422,
                'message' => 'At least one valid card name is required.',
                'errors' => [
                    'nameOnCards' => [
                        'At least one valid card name is required.'
                    ],
                ],
                'data' => null,
            ];
        }

        /*
         * Build exactly the payload expected by:
         *
         * POST /cards/order
         */
        $orderPayload = [
            'debitAccountId' => $debitAccountId,
            'currency' => $currency,
            'allocation' => $allocation,
            'expedite' => $expedite,
            'shippingMethod' => $shippingMethod,
            'shippingAddress' => $shippingAddress,
            'customerId' => $payload['customerId'],
            'design' => $design,
            'nameOnCards' => $nameOnCards,
            'brand' => $brand,
        ];

        Log::info('Sudo card order request', [
            'base_url' => $this->baseUrl,
            'payload' => $this->sanitizeLogPayload($orderPayload),
        ]);

        return $this->request(
            'POST',
            '/cards/order',
            $orderPayload
        );
    } catch (Throwable $e) {
        Log::error('Sudo card order failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'statusCode' => null,
            'message' => 'Unable to order card.',
            'errors' => null,
            'data' => null,
            'response' => null,
            'error' => $e->getMessage(),
        ];
    }
}

protected function normalizeCardOrderBrand(?string $brand): ?string
{
    if ($brand === null) {
        return null;
    }

    return match (
        strtolower(
            preg_replace(
                '/[\s_-]+/',
                '',
                trim($brand)
            )
        )
    ) {
        'visa' => 'Visa',
        'mastercard' => 'MasterCard',
        'verve' => 'Verve',
        'afrigo' => 'AfriGo',
        default => null,
    };
}



}