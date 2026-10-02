<?php

declare(strict_types=1);

namespace App\Services\MultiCurrency\Providers;

use App\Models\MultiCurrencyWallet;
use App\Models\User;
use App\Services\MultiCurrency\Contracts\CurrencyProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FincraService implements CurrencyProviderInterface
{
    /**
     * Supported Fincra currencies.
     */
    protected array $supportedCurrencies = [
        'NGN',
        'GHS',
        'KES',
        'TZS',
    ];

    /**
     * Supported account types.
     */
    protected array $supportedAccountTypes = [
        'individual',
        'corporate',
    ];

    /**
     * Fincra API base URL.
     */
    protected string $baseUrl;

    /**
     * Fincra private key.
     */
    protected string $privateKey;

    /**
     * Fincra public key. Required by endpoints such as Checkout.
     */
    protected string $publicKey;

    /**
     * Fincra business ID.
     */
    protected ?string $businessId;

    /**
     * HTTP timeout.
     */
    protected int $timeout;

    /**
     * HTTP retry count.
     */
    protected int $retryTimes;

    /**
     * Log channel.
     */
    protected string $logChannel = 'fincra';

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) config(
                'services.fincra.base_url',
                'https://sandboxapi.fincra.com'
            ),
            '/'
        );

        $this->privateKey = (string) config(
            'services.fincra.secret_key',
            config('services.fincra.private_key', '')
        );

        $this->publicKey = (string) config(
            'services.fincra.public_key',
            ''
        );

        $this->businessId = config(
            'services.fincra.business_id'
        );

        $this->timeout = (int) config(
            'services.fincra.timeout',
            30
        );

        $this->retryTimes = (int) config(
            'services.fincra.retry_times',
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Virtual Accounts
    |--------------------------------------------------------------------------
    */

    /**
     * Create a local currency virtual account.
     */

public function createVirtualAccount(
    User $user,
    string $currency,
    string $accountType = 'individual'
): array {
    $currency = strtoupper(trim($currency));

    $this->validateCurrency($currency);

    $accountType = strtolower(trim($accountType));

    if (!in_array(
        $accountType,
        $this->supportedAccountTypes,
        true
    )) {
        throw new RuntimeException(
            "Unsupported Fincra account type: {$accountType}"
        );
    }

    $kyc = $user->user_kyc;

    if ($kyc === null) {
        throw new RuntimeException(
            'User KYC verification record was not found.'
        );
    }

    $bvn = data_get($kyc->data, 'bvn');

    if (
        !is_string($bvn)
        || trim($bvn) === ''
    ) {
        throw new RuntimeException(
            'User BVN is required to create a Fincra virtual account.'
        );
    }

    $merchantReference = $this->generateReference(
        'MCW-VA'
    );

    $payload = [
        'currency' => $currency,
        'accountType' => $accountType,

        'KYCInformation' => [
            'firstName' => $user->firstname,
            'lastName' => $user->lastname,
            'email' => $user->email,
            'bvn' => trim($bvn),
        ],

        'merchantReference' => $merchantReference,
    ];

    $response = $this->post(
        '/profile/virtual-accounts/requests',
        $payload
    );

    return $this->normalizeVirtualAccountResponse(
        $response,
        $currency,
        $accountType,
        $merchantReference
    );
}
    /**
     * Normalize virtual account response.
     */
  protected function normalizeVirtualAccountResponse(
    array $response,
    string $currency,
    string $accountType,
    string $merchantReference
): array {
    $data = data_get($response, 'data');

    if (!is_array($data)) {
        throw new RuntimeException(
            'Invalid virtual account response from Fincra.'
        );
    }

    $accountInformation = data_get(
        $data,
        'accountInformation',
        []
    );

    if (!is_array($accountInformation)) {
        $accountInformation = [];
    }

    return [
        'provider' => 'fincra',

        'currency' => strtoupper($currency),

        'account_type' => strtolower($accountType),
        'provider_business_id'=>data_get($data,'business'),

        'provider_reference' =>
            data_get(
                $data,
                'merchantReference'
            )
            ?? $merchantReference,

        'provider_account_id' =>
            data_get($data, '_id'),

        'virtual_account_number' =>
            data_get(
                $accountInformation,
                'accountNumber'
            ),

        'account_name' =>
            data_get(
                $accountInformation,
                'accountName'
            ),

        'bank_name' =>
            data_get(
                $accountInformation,
                'bankName'
            ),

        'bank_code' =>
            data_get(
                $accountInformation,
                'bankCode'
            ),

        'provider_wallet_number' =>
            data_get(
                $data,
                'walletNumber'
            ),

       'status' => $this->normalizeVirtualAccountStatus($data),

        'can_receive' =>
            data_get(
                $data,
                'canReceive',
                true
            ),

        'can_send' =>
            data_get(
                $data,
                'canSend',
                true
            ),

        'provider_metadata' => $data,
    ];
}

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    */

    /**
     * Get collections for a virtual account.
     */
    public function getCollections(
        string $virtualAccount,
        ?string $business = null
    ): array {
        $query = [
            'virtualAccount' => $virtualAccount,
        ];

        $business ??= $this->businessId;

        if ($business) {
            $query['business'] = $business;
        }

        return $this->get(
            '/collections',
            $query
        );
    }

    /**
     * Verify a deposit by merchant reference.
     */
    public function verifyDeposit(
        string $merchantReference
    ): ?array {
        try {
            $response = $this->get(
                '/collections/merchant-reference/'
                . urlencode($merchantReference)
            );

            return $response;
        } catch (Throwable $e) {

            /*
             * Fincra returns 404 when payment has not
             * yet been received.
             */
            if ($this->isNotFoundException($e)) {
                return null;
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Rates
    |--------------------------------------------------------------------------
    */

    /**
     * Get Fincra treasury rates.
     */
    public function getRates(
        ?string $from = null,
        ?string $to = null
    ): array {
        $query = [];

        if ($from && $to) {
            $query['currencyPair'] = strtoupper($from) . '-' . strtoupper($to);
        } elseif ($from) {
            $query['baseCurrency'] = strtoupper($from);
        } elseif ($to) {
            $query['quoteCurrency'] = strtoupper($to);
        }

        return $this->get(
            '/quotes/treasury-orders/rates',
            $query
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Banks
    |--------------------------------------------------------------------------
    */

    /**
     * Get banks supported by Fincra.
     */
    public function getBanks(
        string $currency,
        string $country
    ): array {
        return $this->get(
            '/core/banks',
            [
                'currency' => strtoupper($currency),
                'country' => strtoupper($country),
                'paymentDestination' => 'bank_account',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Wallet Transfers
    |--------------------------------------------------------------------------
    */

    /**
     * Transfer funds to another Fincra wallet.
     */
    public function transferToWallet(
        User $user,
        string $currency,
        string $beneficiaryWalletNumber,
        string $amount,
        string $description
    ): array {
        $currency = strtoupper($currency);

        $this->validateCurrency($currency);

        $wallet = $this->getUserFincraWallet(
            $user,
            $currency
        );

        if (!$wallet) {
            throw new RuntimeException(
                "No Fincra {$currency} wallet exists for this user."
            );
        }

        $business = $wallet->provider_business_id
            ?: $this->businessId;

        if (!$business) {
            throw new RuntimeException(
                'Fincra business ID is not configured.'
            );
        }

        $customerReference = $this->generateReference(
            'MCW-TRF'
        );

        $payload = [
            'amount' => (string) $amount,

            'business' => $business,

            'customerReference' =>
                $customerReference,

            'description' => $description,

            'beneficiaryWalletNumber' =>
                $beneficiaryWalletNumber,
        ];

        $response = $this->post(
            '/disbursements/payouts/wallets',
            $payload
        );

        return [
            'provider' => 'fincra',

            'reference' =>
                data_get($response, 'data.reference')
                ?? data_get($response, 'reference')
                ?? $customerReference,

            'customer_reference' =>
                $customerReference,

            'currency' => $currency,

            'amount' => $amount,

            'beneficiary_wallet_number' =>
                $beneficiaryWalletNumber,

            'status' =>
                data_get($response, 'data.status')
                ?? data_get($response, 'status')
                ?? 'pending',

            'provider_response' => $response,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Quotes
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a Fincra quote.
     *
     * Quotes expire quickly, so the caller should generate
     * and consume them immediately.
     */
    public function generateQuote(
    ?string $sourceCurrency,
    string $destinationCurrency,
    ?string $amount = null,
    string $action = 'send',
    string $transactionType = 'disbursement',
    string $feeBearer = 'customer',
    string $paymentDestination = 'bank_account',
    ?string $paymentScheme = null,
    string $beneficiaryType = 'individual',
    bool $delay = false
): array {
        $sourceCurrency = strtoupper(
            $sourceCurrency
        );

        $destinationCurrency = strtoupper(
            $destinationCurrency
        );

        $payload = [
            'sourceCurrency' =>
                $sourceCurrency,

            'destinationCurrency' =>
                $destinationCurrency,

            'amount' => (string) $amount,

            'action' => $action,

            'transactionType' =>
                $transactionType,

            'business' => $this->businessId,

            'feeBearer' => $feeBearer,

            'paymentDestination' =>
                $paymentDestination,

            'beneficiaryType' =>
                $beneficiaryType,

            'delay' => $delay,
        ];

        if ($paymentScheme) {
            $payload['paymentScheme'] =
                $paymentScheme;
        }

        return $this->post(
            '/quotes/generate',
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payins / Checkout
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate a Fincra payin.
     *
     * This method is intentionally provider-specific.
     *
     * For permanent virtual-account funding, the customer
     * sends money to the virtual account and Fincra sends
     * a collection webhook.
     *
     * This method is for an active checkout/payment flow.
     */
public function initiatePayin(
    User $user,
    string $currency,
    string $amount,
    string $paymentMethod = 'bank_transfer',
    ?string $quoteReference = null
): array {
    $currency = strtoupper(trim($currency));
    $amount = trim($amount);
    $paymentMethod = trim($paymentMethod);

    $this->validateCurrency($currency);

    if ($amount === '' || !is_numeric($amount)) {
        throw new InvalidArgumentException(
            'Payin amount must be a valid numeric value.'
        );
    }

    $numericAmount = (float) $amount;

    if ($numericAmount < 1) {
        throw new InvalidArgumentException(
            'Payin amount cannot be less than 1.'
        );
    }

    if (floor($numericAmount) !== $numericAmount) {
        throw new InvalidArgumentException(
            'Payin amount must be a whole number.'
        );
    }

    $wallet = $this->getUserFincraWallet(
        $user,
        $currency
    );

    if (!$wallet) {
        throw new RuntimeException(
            "No Fincra {$currency} wallet exists for this user."
        );
    }

    $reference = $this->generateReference('MCW-PAY');

    $supportedMethods = [
        'bank_transfer',
        'mobile_money',
        'card',
        'payAttitude',
    ];

    if (!in_array(
        $paymentMethod,
        $supportedMethods,
        true
    )) {
        throw new RuntimeException(
            "Unsupported Fincra payment method: {$paymentMethod}"
        );
    }

    $customerName = trim(
        (string) $user->firstname
        . ' '
        . (string) $user->lastname
    );

    if ($customerName === '') {
        throw new RuntimeException(
            'User name is required to initiate Fincra payin.'
        );
    }

    $customerEmail = trim(
        (string) $user->email
    );

    if ($customerEmail === '') {
        throw new RuntimeException(
            'User email is required to initiate Fincra payin.'
        );
    }

    $customer = [
        'name' => $customerName,
        'email' => $customerEmail,
    ];

    if ($user->full_mobile !== null) {
        $phoneNumber = trim(
            (string) $user->full_mobile
        );

        if ($phoneNumber !== '') {
            $customer['phoneNumber'] = $phoneNumber;
        }
    }

    $payload = [
        'amount' => (int) $numericAmount,
        'currency' => $currency,
        'reference' => $reference,
        'customer' => $customer,
        'paymentMethods' => [
            $paymentMethod,
        ],
    ];

    if ($quoteReference !== null) {
        $quoteReference = trim($quoteReference);

        if ($quoteReference !== '') {
            $payload['quoteReference'] = $quoteReference;
        }
    }

    $settlementDestination = config(
        'services.fincra.settlement_destination'
    );

    if (
        is_string($settlementDestination)
        && trim($settlementDestination) !== ''
    ) {
        $payload['settlementDestination'] =
            trim($settlementDestination);
    }

    Log::debug('Fincra payin checkout payload', [
        'amount' => $payload['amount'],
        'amount_type' => gettype($payload['amount']),
        'currency' => $payload['currency'],
        'reference' => $payload['reference'],
        'paymentMethods' => $payload['paymentMethods'],
    ]);

    $response = $this->checkoutClient()
        ->post('/checkout/payments', $payload);

    $response = $this->handleResponse(
        $response,
        'POST',
        '/checkout/payments'
    );

    return [
        'provider' => 'fincra',

        'reference' =>
            data_get($response, 'data.reference')
            ?? data_get($response, 'reference')
            ?? $reference,

        'currency' => $currency,

        'amount' => (string) $numericAmount,

        'payment_method' => $paymentMethod,

        'status' =>
            data_get($response, 'data.status')
            ?? data_get($response, 'status')
            ?? 'pending',

        'payment_url' =>
            data_get($response, 'data.paymentUrl')
            ?? data_get($response, 'data.payment_url')
            ?? data_get($response, 'paymentUrl'),

        'provider_response' => $response,
    ];
}

    /*
    |--------------------------------------------------------------------------
    | Provider Wallet
    |--------------------------------------------------------------------------
    */

    /**
     * Get the user's Fincra wallet for a currency.
     */
    protected function getUserFincraWallet(
        User $user,
        string $currency
    ): ?MultiCurrencyWallet {
        return $user->multicurrencyWallets()
            ->where('provider', 'fincraservice')
            ->where(
                'currency',
                strtoupper($currency)
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */

    /**
     * Build Fincra HTTP client.
     */
    protected function client(): PendingRequest
    {
        if ($this->privateKey === '') {
            throw new RuntimeException(
                'Fincra secret key is not configured.'
            );
        }

        $headers = [
            'api-key' => $this->privateKey,
        ];

        if ($this->businessId !== null && $this->businessId !== '') {
            $headers['x-business-id'] = $this->businessId;
        }

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                500,
                function (
                    Throwable $exception
                ) {
                    return
                        $exception instanceof
                        \Illuminate\Http\Client\ConnectionException;
                }
            );
    }

    /**
     * Build Fincra Checkout HTTP client.
     *
     * Checkout requires the secret api-key and public x-pub-key.
     */
    protected function checkoutClient(): PendingRequest
    {
        if ($this->privateKey === '') {
            throw new RuntimeException(
                'Fincra secret key is not configured.'
            );
        }

        if ($this->publicKey === '') {
            throw new RuntimeException(
                'Fincra public key is not configured.'
            );
        }

        $headers = [
            'api-key' => $this->privateKey,
            'x-pub-key' => $this->publicKey,
        ];

        if ($this->businessId !== null && $this->businessId !== '') {
            $headers['x-business-id'] = $this->businessId;
        }

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                500,
                function (Throwable $exception): bool {
                    return $exception instanceof
                        \Illuminate\Http\Client\ConnectionException;
                }
            );
    }

    /**
     * GET request.
     */
    protected function get(
        string $endpoint,
        array $query = []
    ): array {
        try {
            $this->logRequest(
                'GET',
                $endpoint,
                $query
            );

            $response = $this->client()
                ->get(
                    $endpoint,
                    $query
                );

            return $this->handleResponse(
                $response,
                'GET',
                $endpoint
            );

        } catch (Throwable $e) {
            $this->logError(
                'GET',
                $endpoint,
                $e
            );

            throw $e;
        }
    }

    /**
     * POST request.
     */
    protected function post(
        string $endpoint,
        array $payload = []
    ): array {
        try {
            $this->logRequest(
                'POST',
                $endpoint,
                $payload
            );

            $response = $this->client()
                ->post(
                    $endpoint,
                    $payload
                );

            return $this->handleResponse(
                $response,
                'POST',
                $endpoint
            );

        } catch (Throwable $e) {
            $this->logError(
                'POST',
                $endpoint,
                $e
            );

            throw $e;
        }
    }

    /**
     * Handle Fincra response.
     */
    protected function handleResponse(
        $response,
        string $method,
        string $endpoint
    ): array {
        $body = $response->json();

        if ($response->successful()) {
            return is_array($body)
                ? $body
                : [];
        }

        $message =
            data_get($body, 'message')
            ?? data_get($body, 'error')
            ?? $response->body();

        Log::channel($this->logChannel)
            ->error(
                'Fincra API request failed.',
                [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' =>
                        $response->status(),
                    'response' => $body,
                ]
            );

        throw new RuntimeException(
            "Fincra API error ({$response->status()}): "
            . $message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Validate supported currency.
     */
    protected function validateCurrency(
        string $currency
    ): void {
        if (!in_array(
            strtoupper($currency),
            $this->supportedCurrencies,
            true
        )) {
            throw new RuntimeException(
                "Currency {$currency} is not supported by Fincra."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | References
    |--------------------------------------------------------------------------
    */

    /**
     * Generate an internal/provider merchant reference.
     */
    protected function generateReference(
        string $prefix
    ): string {
        return $prefix
            . '-'
            . now()->format('YmdHis')
            . '-'
            . strtoupper(
                Str::random(12)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Errors
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether exception represents a 404.
     */
    protected function isNotFoundException(
        Throwable $exception
    ): bool {
        return str_contains(
            $exception->getMessage(),
            '(404)'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    /**
     * Log outgoing request.
     *
     * Do NOT log private API keys.
     */
    protected function logRequest(
        string $method,
        string $endpoint,
        array $payload = []
    ): void {
        Log::channel($this->logChannel)
            ->info(
                'Fincra API request.',
                [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'payload' =>
                        $this->sanitizeLogData(
                            $payload
                        ),
                ]
            );
    }

    /**
     * Log request failure.
     */
    protected function logError(
        string $method,
        string $endpoint,
        Throwable $exception
    ): void {
        Log::channel($this->logChannel)
            ->error(
                'Fincra API exception.',
                [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' =>
                        $exception->getMessage(),
                ]
            );
    }

    /**
     * Remove sensitive information from logs.
     */
    protected function sanitizeLogData(
        array $data
    ): array {
        $sensitive = [
            'privateKey',
            'private_key',
            'secret',
            'password',
            'authorization',
            'token',
            'apiKey',
            'api_key',
        ];

        foreach ($sensitive as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }
        /*
    |--------------------------------------------------------------------------
    | Payouts
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate a payout to a beneficiary's bank account.
     *
     * If sourceCurrency and destinationCurrency differ, this is a
     * cross-currency payout and requires a quoteReference from
     * generateQuote().
     */
    public function initiateBankPayout(
        string $sourceCurrency,
        string $destinationCurrency,
        string $amount,
        array $beneficiary,
        ?string $description = null,
        ?string $quoteReference = null
    ): array {
        $sourceCurrency = strtoupper($sourceCurrency);
        $destinationCurrency = strtoupper($destinationCurrency);

        if (!$this->businessId) {
            throw new RuntimeException(
                'Fincra business ID is not configured.'
            );
        }

        $customerReference = $this->generateReference(
            'MCW-PYT'
        );

        $payload = [
            'business' => $this->businessId,

            'sourceCurrency' => $sourceCurrency,

            'destinationCurrency' => $destinationCurrency,

            /*
             * Fincra expects amount as a number, not a string,
             * for this endpoint specifically.
             */
            'amount' => (float) $amount,

            'customerReference' => $customerReference,

            'paymentDestination' => 'bank_account',

            'beneficiary' => $beneficiary,
        ];

        if ($description) {
            $payload['description'] = $description;
        }

        if ($quoteReference) {
            $payload['quoteReference'] = $quoteReference;
        } elseif ($sourceCurrency !== $destinationCurrency) {
            throw new RuntimeException(
                'A quoteReference is required for cross-currency payouts.'
            );
        }

        $response = $this->post(
            '/disbursements/payouts',
            $payload
        );

        return $this->normalizePayoutResponse(
            $response,
            $sourceCurrency,
            $destinationCurrency,
            $amount,
            $customerReference
        );
    }

    /**
     * Normalize bank-account payout response.
     */
    protected function normalizePayoutResponse(
        array $response,
        string $sourceCurrency,
        string $destinationCurrency,
        string $amount,
        string $customerReference
    ): array {
        $data = $response['data']
            ?? $response;

        return [
            'provider' => 'fincra',

            'provider_payout_id' =>
                data_get($data, 'id'),

            'reference' =>
                data_get($data, 'reference')
                ?? $customerReference,

            'customer_reference' =>
                data_get($data, 'customerReference')
                ?? $customerReference,

            'source_currency' => $sourceCurrency,

            'destination_currency' => $destinationCurrency,

            'amount' => $amount,

            /*
             * success: true only confirms Fincra received the
             * request — it does not mean the payout settled.
             * Callers must keep tracking via webhooks/status endpoint.
             */
            'status' =>
                data_get($data, 'status')
                ?? 'pending',

            'is_document_required' =>
                (bool) data_get($data, 'isDocumentRequired', false),

            'documents_required' =>
                data_get($data, 'documentsRequired', []),

            'provider_response' => $response,
        ];
    }
        /**
     * Verify payout status using your customer reference.
     */
    public function verifyPayoutStatus(string $customerReference): array
    {
        $customerReference = trim($customerReference);

        if ($customerReference === '') {
            throw new RuntimeException(
                'A customer reference is required to verify payout status.'
            );
        }

        return $this->get(
            '/disbursements/payouts/customer-reference/'
                . urlencode($customerReference)
        );
    }

    /**
     * Verify checkout payment status using your merchant reference.
     */
  /**
 * Verify the status of a Fincra conversion by reference.
 *
 * Endpoint:
 * GET /conversions/reference/{reference}
 */
public function verifyConversion(
    string $conversionReference
): ?array {
    $conversionReference = trim($conversionReference);

    if ($conversionReference === '') {
        throw new RuntimeException(
            'A conversion reference is required to verify conversion status.'
        );
    }

    try {
        $response = $this->get(
            '/conversions/reference/' . urlencode($conversionReference)
        );

        return $this->normalizeConversionStatusResponse(
            $response,
            $conversionReference
        );
    } catch (Throwable $e) {
        /*
         * A 404 means the conversion cannot currently be found.
         */
        if ($this->isNotFoundException($e)) {
            return null;
        }

        throw $e;
    }
}

    /*
    |--------------------------------------------------------------------------
    | Conversions
    |--------------------------------------------------------------------------
    */

    /**
     * Initiate a currency conversion from a previously
     * generated quote.
     */
    public function initiateConversion(
        string $quoteReference,
        ?string $customerReference = null
    ): array {
        if (!$this->businessId) {
            throw new RuntimeException(
                'Fincra business ID is not configured.'
            );
        }

        $quoteReference = trim($quoteReference);

        if ($quoteReference === '') {
            throw new RuntimeException(
                'A quote reference is required to initiate a conversion.'
            );
        }

        $customerReference ??= $this->generateReference(
            'MCW-CNV'
        );

        $payload = [
            'business' => $this->businessId,

            'quoteReference' => $quoteReference,

            'customerReference' => $customerReference,
        ];

        $response = $this->post(
            '/conversions/initiate',
            $payload
        );

        return $this->normalizeConversionResponse(
            $response,
            $quoteReference,
            $customerReference
        );
    }

    /**
     * Normalize conversion response.
     */
    protected function normalizeConversionResponse(
        array $response,
        string $quoteReference,
        string $customerReference
    ): array {
        $data = $response['data']
            ?? $response;

        return [
            'provider' => 'fincra',

            'provider_conversion_id' =>
                data_get($data, 'id'),

            'reference' =>
                data_get($data, 'reference')
                ?? $customerReference,

            'customer_reference' =>
                data_get($data, 'customerReference')
                ?? $customerReference,

            'quote_reference' => $quoteReference,

            'status' =>
                data_get($data, 'status')
                ?? 'pending',

            'provider_response' => $response,
        ];
    }

    /**
 * Verify the status of a Fincra conversion by reference.
 *
 * Endpoint:
 * GET /conversions/reference/{reference}
 */
public function verifyConversionStatus(
    string $reference
): array {
    $reference = trim($reference);

    if ($reference === '') {
        throw new RuntimeException(
            'A conversion reference is required to verify conversion status.'
        );
    }

    $response = $this->get(
        '/conversions/reference/' . urlencode($reference)
    );

    return $this->normalizeConversionStatusResponse(
        $response,
        $reference
    );
}

/**
 * Normalize conversion status response.
 */
protected function normalizeConversionStatusResponse(
    array $response,
    string $reference
): array {
    $data = $response['data']
        ?? $response;

    return [
        'provider' => 'fincra',

        'provider_conversion_id' =>
            data_get($data, 'id'),

        'reference' =>
            data_get($data, 'reference')
            ?? $reference,

        'customer_reference' =>
            data_get($data, 'customerReference'),

        'quote_reference' =>
            data_get($data, 'quoteReference'),

        'source_currency' =>
            data_get($data, 'sourceCurrency'),

        'destination_currency' =>
            data_get($data, 'destinationCurrency'),

        'source_amount' =>
            data_get($data, 'sourceAmount'),

        'destination_amount' =>
            data_get($data, 'destinationAmount'),

        'rate' =>
            data_get($data, 'rate'),

        'fee' =>
            data_get($data, 'fee'),

        'status' =>
            data_get($data, 'status')
            ?? 'pending',

        'provider_response' => $response,
    ];
}

/**
 * Verify/resolve a bank account.
 *
 * Fincra endpoint:
 * POST /core/accounts/resolve
 */
public function verifyAccount(
    string $type,
    ?string $accountNumber = null,
    ?string $bankCode = null,
    ?string $bankSwiftCode = null,
    ?string $mobileMoneyCode = null,
    ?string $iban = null,
    ?string $currency = null
): array {
    $type = strtolower(trim($type));

    $accountNumber = $accountNumber !== null
        ? trim($accountNumber)
        : null;

    $bankCode = $bankCode !== null
        ? trim($bankCode)
        : null;

    $bankSwiftCode = $bankSwiftCode !== null
        ? trim($bankSwiftCode)
        : null;

    $mobileMoneyCode = $mobileMoneyCode !== null
        ? trim($mobileMoneyCode)
        : null;

    $iban = $iban !== null
        ? strtoupper(trim($iban))
        : null;

    $currency = $currency !== null
        ? strtoupper(trim($currency))
        : null;

    $supportedTypes = [
        'bank_account',
        'mobile_money',
        'nuban',
        'iban',
    ];

    if (!in_array($type, $supportedTypes, true)) {
        throw new RuntimeException(
            "Unsupported account type: {$type}"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate fields according to verification type
    |--------------------------------------------------------------------------
    */

    switch ($type) {
        case 'nuban':
            if (!$accountNumber) {
                throw new RuntimeException(
                    'Account number is required for NUBAN verification.'
                );
            }

            if (!$bankCode) {
                throw new RuntimeException(
                    'Bank code is required for NUBAN verification.'
                );
            }

            break;

        case 'bank_account':
            if (!$accountNumber) {
                throw new RuntimeException(
                    'Account number is required for bank account verification.'
                );
            }

            if (!$bankCode) {
                throw new RuntimeException(
                    'Bank code is required for bank account verification.'
                );
            }

            if (!$currency) {
                throw new RuntimeException(
                    'Currency is required for bank account verification.'
                );
            }

            break;

        case 'mobile_money':
            if (!$mobileMoneyCode) {
                throw new RuntimeException(
                    'Mobile money code is required for mobile money verification.'
                );
            }

            if (!$currency) {
                throw new RuntimeException(
                    'Currency is required for mobile money verification.'
                );
            }

            break;

        case 'iban':
            if (!$iban) {
                throw new RuntimeException(
                    'IBAN is required for IBAN verification.'
                );
            }

            break;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate supported currency
    |--------------------------------------------------------------------------
    */

    if (
        $currency !== null &&
        !in_array($currency, $this->supportedCurrencies, true)
    ) {
        throw new RuntimeException(
            "Currency {$currency} is not supported by Fincra."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Build Fincra payload
    |--------------------------------------------------------------------------
    */

    $payload = [
        'type' => $type,
    ];

    if ($accountNumber !== null) {
        $payload['accountNumber'] = $accountNumber;
    }

    if ($bankCode !== null) {
        $payload['bankCode'] = $bankCode;
    }

    if ($bankSwiftCode !== null) {
        $payload['bankSwiftCode'] = $bankSwiftCode;
    }

    if ($mobileMoneyCode !== null) {
        $payload['mobileMoneyCode'] = $mobileMoneyCode;
    }

    if ($iban !== null) {
        $payload['iban'] = $iban;
    }

    if ($currency !== null) {
        $payload['currency'] = $currency;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve account
    |--------------------------------------------------------------------------
    */

    $response = $this->post(
        '/core/accounts/resolve',
        $payload
    );

    return $response;
}
/**
 * Normalize Fincra wallet status to the application's wallet status.
 *
 * @param array<string, mixed> $data
 */
protected function normalizeVirtualAccountStatus(array $data): string
{
    $providerStatus = strtolower(
        trim((string) data_get($data, 'status', 'pending'))
    );

    $isSuspended = (bool) data_get(
        $data,
        'isSuspended',
        false
    );

    if ($isSuspended) {
        return 'suspended';
    }

    return match ($providerStatus) {
        'approved', 'active' => 'active',
        'pending', 'processing' => 'pending',
        'suspended' => 'suspended',
        'blocked', 'disabled' => 'blocked',
        default => 'pending',
    };
}
}