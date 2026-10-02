<?php

namespace App\Services;

use App\Models\RampTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Services\FonbnkKycService;
class RampService
{
    /**
     * Strict provider routing:
     *
     * ON-RAMP:
     * - NGN/GHS -> Quidax
     * - Other supported fiat currencies -> Fonbnk
     *
     * OFF-RAMP:
     * - NGN/GHS -> Quidax
     * - Other supported fiat currencies -> Fonbnk
     *
     * Explicit force_provider=fonbnk always routes to Fonbnk.
     *
     * IMPORTANT:
     * - There is NO automatic provider switch from Quidax to Fonbnk.
     * - If Quidax has a transient failure, the response only instructs
     *   the client to retry explicitly with force_provider=fonbnk.
     * - Existing transactions always use the provider stored on the
     *   local transaction. There is NO provider guessing.
     */
    protected QuidaxRampService $quidax;
    protected FonbnkService $fonbnk;
    protected FonbnkKycService $fonbnkKyc;
    protected CountryService $countryService;

    public function __construct(
        QuidaxRampService $quidax,
        FonbnkService $fonbnk,
        FonbnkKycService $fonbnkKyc,
        CountryService $countryService
    ) {
        $this->quidax = $quidax;
        $this->fonbnk = $fonbnk;
        $this->fonbnkKyc = $fonbnkKyc;
        $this->countryService = $countryService;
    }

    // =========================================================================
    // PROVIDER RESOLUTION
    // =========================================================================

    protected function resolveOnRampProvider(array $payload): string
    {
        if ($this->isForcedFonbnk($payload)) {
            return 'fonbnk';
        }

        $currency = strtoupper((string) ($payload['from_currency'] ?? ''));

        if (in_array($currency, ['NGN', 'GHS'], true)) {
            return 'quidax';
        }

        return 'fonbnk';
    }

    protected function resolveOffRampProvider(array $payload): string
    {
        if ($this->isForcedFonbnk($payload)) {
            return 'fonbnk';
        }

        $currency = strtoupper((string) ($payload['to_currency'] ?? ''));

        if (in_array($currency, ['NGN', 'GHS'], true)) {
            return 'quidax';
        }

        return 'fonbnk';
    }

    /**
     * Explicit Fonbnk routing.
     *
     * Accepted:
     * force_provider=fonbnk
     * provider=fonbnk
     */
    protected function isForcedFonbnk(array $payload): bool
    {
        $forced = strtolower(
            trim(
                (string) (
                    $payload['force_provider']
                    ?? $payload['provider']
                    ?? ''
                )
            )
        );

        return $forced === 'fonbnk';
    }

    /**
     * Only infrastructure/transient failures should generate the
     * use_fonbnk instruction.
     */
    protected function isTransientProviderFailure(array $result): bool
    {
        if (($result['ok'] ?? false) === true) {
            return false;
        }

        $httpStatus = (int) ($result['http_status'] ?? 0);

        return in_array(
            $httpStatus,
            [408, 425, 429, 500, 502, 503, 504],
            true
        );
    }

    /**
     * Existing transactions MUST have an explicitly stored provider.
     *
     * We intentionally do not default to Quidax because doing so can
     * incorrectly operate on a Fonbnk transaction.
     */
    protected function providerForExistingTransaction(
        string $merchantReference,
        string $type
    ): ?string {
        $transaction = RampTransaction::query()
            ->where('merchant_reference', $merchantReference)
            ->where('type', $type)
            ->first();

        if (!$transaction) {
            return null;
        }

        $provider = strtolower(trim((string) $transaction->provider));

        return $provider !== '' ? $provider : null;
    }

    // =========================================================================
    // ON-RAMP
    // =========================================================================

    public function initiateOnRamp(array $payload): array
    {
        $provider = $this->resolveOnRampProvider($payload);

        Log::info('Ramp on-ramp provider selected', [
            'provider' => $provider,
            'merchant_reference' => $payload['merchant_reference'] ?? null,
            'from_currency' => $payload['from_currency'] ?? null,
            'to_currency' => $payload['to_currency'] ?? null,
            'network' => $payload['network'] ?? null,
        ]);

        if ($provider === 'fonbnk') {
            return $this->tagProvider(
                $this->fonbnkOnRamp($payload),
                'fonbnk'
            );
        }

        // Strict: Quidax only.
        $result = $this->quidax->initiateOnRamp($payload);

        if (($result['ok'] ?? false) === true) {
            return $this->tagProvider($result, 'quidax');
        }

        // Do NOT execute Fonbnk automatically.
        if ($this->isTransientProviderFailure($result)) {
            return $this->useFonbnkResponse(
                type: 'on_ramp',
                fiatCurrency: strtoupper(
                    (string) ($payload['from_currency'] ?? '')
                ),
                providerResult: $result
            );
        }

        return $this->tagProvider($result, 'quidax');
    }

    /**
     * Fonbnk on-ramp:
     * getOnRampQuote -> createOrder
     */
    protected function fonbnkOnRamp(array $payload): array
    {
        try {
            
            $fiatCurrency = strtoupper(
                (string) ($payload['from_currency'] ?? '')
            );

            $country = $payload['country_iso_code'];

            if (!$country) {
                return $this->errorResponse(
                    'unsupported_currency',
                    'Unable to determine Fonbnk country for this fiat currency.',
                    422
                );
            }

            $kycError = $this->assertFonbnkKyc(
                $payload,
                $country,
                'on_ramp'
            );

            if ($kycError !== null) {
                return $kycError;
            }

            $paymentChannel = $payload['payment_channel']
                ?? $payload['paymentChannel']
                ?? null;

            if (empty($paymentChannel)) {
                return $this->errorResponse(
                    'missing_payment_channel',
                    'payment_channel is required for Fonbnk on-ramp (e.g. bank, mobile_money).',
                    422,
                    [
                        'use_fonbnk' => true,
                        'required_fields' => $this->fonbnkRequiredFields(
                            'on_ramp',
                            $fiatCurrency
                        ),
                    ]
                );
            }

            $network = strtoupper(
                trim((string) ($payload['network'] ?? ''))
            );

            $asset = strtoupper(
                trim((string) ($payload['to_currency'] ?? ''))
            );

            $amount = (float) ($payload['from_amount'] ?? 0);

            if ($amount <= 0) {
                return $this->errorResponse(
                    'invalid_amount',
                    'from_amount must be greater than zero.',
                    422
                );
            }

            $cryptoCode = $this->fonbnk->buildCryptoCurrencyCode(
                $network,
                $asset
            );

            $quote = $this->fonbnk->getOnRampQuote([
                'countryIsoCode' => $country,
                'fiatCurrencyCode' => $fiatCurrency,
                'paymentChannel' => $paymentChannel,
                'cryptoCurrencyCode' => $cryptoCode,
                'amount' => $amount,
                'carrierCode' => $payload['carrier_code']
                    ?? $payload['carrierCode']
                    ?? null,
            ]);

            if (!($quote['ok'] ?? false)) {
                return $quote;
            }

            $quoteData = $this->extractProviderData($quote);

            $quoteId = $quoteData['quoteId']
                ?? $quoteData['quote_id']
                ?? null;

            if (empty($quoteId)) {
                return $this->errorResponse(
                    'invalid_quote',
                    'Fonbnk did not return a valid quote ID.',
                    502,
                    $quoteData
                );
            }

            $walletAddress = data_get(
                $payload,
                'wallet_address.address'
            ) ?? $payload['wallet_address'] ?? null;

            if (empty($walletAddress)) {
                return $this->errorResponse(
                    'missing_wallet_address',
                    'A destination wallet address is required for Fonbnk on-ramp.',
                    422
                );
            }

            $email = data_get($payload, 'customer.email')
                ?? $payload['email']
                ?? null;

            if (empty($email)) {
                return $this->errorResponse(
                    'missing_email',
                    'Customer email is required for Fonbnk on-ramp.',
                    422
                );
            }

            $fieldsToCreateOrder = array_filter(
                [
                    'blockchainWalletAddress' => $walletAddress,
                    'phoneNumber' => $payload['phone_number']
                        ?? $payload['phoneNumber']
                        ?? null,
                    'carrierCode' => $payload['carrier_code']
                        ?? $payload['carrierCode']
                        ?? null,
                    'bankCode' => $payload['bank_code']
                        ?? $payload['bankCode']
                        ?? null,
                    'bankAccountNumber' => $payload['account_number']
                        ?? $payload['bankAccountNumber']
                        ?? null,
                ],
                static fn ($value) => $value !== null && $value !== ''
            );

            $orderPayload = [
                'quoteId' => $quoteId,
                'userCountryIsoCode' => $country,
                'userEmail' => $email,
                'userIp' => $payload['user_ip']
                    ?? $payload['userIp']
                    ?? null,

                'deposit' => [
                    'paymentChannel' => $paymentChannel,
                    'currencyType' => 'fiat',
                    'currencyCode' => $fiatCurrency,
                    'countryIsoCode' => $country,
                    'amount' => $amount,
                ],

                'payout' => [
                    'paymentChannel' => 'crypto',
                    'currencyType' => 'crypto',
                    'currencyCode' => $cryptoCode,
                ],

                'fieldsToCreateOrder' => $fieldsToCreateOrder,
                'orderParams' => $payload['merchant_reference'] ?? null,
            ];

            $order = $this->fonbnk->createOrder($orderPayload);

            return $this->normalizeFonbnkOrderResponse(
                $order,
                [
                    'from_currency' => strtolower($fiatCurrency),
                    'to_currency' => strtolower(
                        (string) ($payload['to_currency'] ?? '')
                    ),
                    'from_amount' => $payload['from_amount'] ?? null,
                    'network' => $network,
                    'merchant_reference' => $payload['merchant_reference'] ?? null,
                    'quote_id' => $quoteId,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Fonbnk on-ramp orchestration exception', [
                'merchant_reference' => $payload['merchant_reference'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'provider_exception',
                $e->getMessage(),
                500
            );
        }
    }

    public function refreshOnRamp(
        string $merchantReference,
        array $payload
    ): array {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'on_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            return $this->errorResponse(
                'not_supported',
                'Fonbnk does not support refresh on an existing order. Create a new quote/order instead.',
                501
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->refreshOnRamp(
                $merchantReference,
                $payload
            ),
            'quidax'
        );
    }

    public function confirmOnRamp(string $merchantReference): array
    {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'on_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            $orderId = $this->resolveFonbnkOrderId(
                $merchantReference,
                'on_ramp'
            );

            if (!$orderId) {
                return $this->errorResponse(
                    'missing_order_id',
                    'No Fonbnk order ID stored for this on-ramp transaction.',
                    422
                );
            }

            $result = $this->fonbnk->confirmOrder($orderId);

            return $this->tagProvider(
                $this->normalizeFonbnkOrderResponse($result),
                'fonbnk'
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->confirmOnRamp($merchantReference),
            'quidax'
        );
    }

    public function onRampTransaction(
        string $merchantReference
    ): array {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'on_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            $orderId = $this->resolveFonbnkOrderId(
                $merchantReference,
                'on_ramp'
            );

            $result = $orderId
                ? $this->fonbnk->getOrder($orderId)
                : $this->fonbnk->getOrder(null, $merchantReference);

            return $this->tagProvider(
                $this->normalizeFonbnkOrderResponse($result),
                'fonbnk'
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->onRampTransaction($merchantReference),
            'quidax'
        );
    }

    // =========================================================================
    // OFF-RAMP
    // =========================================================================

    public function initiateOffRamp(array $payload): array
    {
        $provider = $this->resolveOffRampProvider($payload);

        Log::info('Ramp off-ramp provider selected', [
            'provider' => $provider,
            'merchant_reference' => $payload['merchant_reference'] ?? null,
            'from_currency' => $payload['from_currency'] ?? null,
            'to_currency' => $payload['to_currency'] ?? null,
            'network' => $payload['network'] ?? null,
        ]);

        if ($provider === 'fonbnk') {
            return $this->tagProvider(
                $this->fonbnkOffRamp($payload),
                'fonbnk'
            );
        }

        // Strict: Quidax only.
        $result = $this->quidax->initiateOffRamp($payload);

        if (($result['ok'] ?? false) === true) {
            return $this->tagProvider($result, 'quidax');
        }

        // Do NOT execute Fonbnk automatically.
        if ($this->isTransientProviderFailure($result)) {
            return $this->useFonbnkResponse(
                type: 'off_ramp',
                fiatCurrency: strtoupper(
                    (string) ($payload['to_currency'] ?? '')
                ),
                providerResult: $result
            );
        }

        return $this->tagProvider($result, 'quidax');
    }

    /**
     * Fonbnk off-ramp:
     * getOffRampQuote -> createOrder
     */
    protected function fonbnkOffRamp(array $payload): array
    {
        try {
            $fiatCurrency = strtoupper(
                (string) ($payload['to_currency'] ?? '')
            );

            /*
             * IMPORTANT:
             * fiat_iso_code was previously passed into
             * fiatFromCountryCode(), which is backwards.
             *
             * Fonbnk needs the country ISO code associated with
             * the destination fiat currency.
             */
            $country = $payload['country_iso_code'];

            if (!$country) {
                return $this->errorResponse(
                    'unsupported_currency',
                    'Unable to determine Fonbnk country for this fiat currency.',
                    422
                );
            }

            $kycError = $this->assertFonbnkKyc(
                $payload,
                $country,
                'off_ramp'
            );

            if ($kycError !== null) {
                return $kycError;
            }

            $paymentChannel = $payload['payment_channel']
                ?? $payload['paymentChannel']
                ?? null;

            if (empty($paymentChannel)) {
                return $this->errorResponse(
                    'missing_payment_channel',
                    'payment_channel is required for Fonbnk off-ramp (e.g. bank, mobile_money).',
                    422,
                    [
                        'use_fonbnk' => true,
                        'required_fields' => $this->fonbnkRequiredFields(
                            'off_ramp',
                            $fiatCurrency
                        ),
                    ]
                );
            }

            $network = strtoupper(
                trim((string) ($payload['network'] ?? ''))
            );

            $asset = strtoupper(
                trim((string) ($payload['from_currency'] ?? ''))
            );

            $amount = (float) ($payload['from_amount'] ?? 0);

            if ($amount <= 0) {
                return $this->errorResponse(
                    'invalid_amount',
                    'from_amount must be greater than zero.',
                    422
                );
            }

            $cryptoCode = $this->fonbnk->buildCryptoCurrencyCode(
                $network,
                $asset
            );

            $quote = $this->fonbnk->getOffRampQuote([
                'countryIsoCode' => $country,
                'fiatCurrencyCode' => $fiatCurrency,
                'paymentChannel' => $paymentChannel,
                'cryptoCurrencyCode' => $cryptoCode,
                'amount' => $amount,
            ]);

            if (!($quote['ok'] ?? false)) {
                return $quote;
            }

            $quoteData = $this->extractProviderData($quote);

            $quoteId = $quoteData['quoteId']
                ?? $quoteData['quote_id']
                ?? null;

            if (empty($quoteId)) {
                return $this->errorResponse(
                    'invalid_quote',
                    'Fonbnk did not return a valid quote ID.',
                    502,
                    $quoteData
                );
            }

            $email = data_get($payload, 'customer.email')
                ?? $payload['email']
                ?? null;

            if (empty($email)) {
                return $this->errorResponse(
                    'missing_email',
                    'Customer email is required for Fonbnk off-ramp.',
                    422
                );
            }

            $fieldsToCreateOrder = array_filter(
                [
                    'phoneNumber' => $payload['phone_number']
                        ?? $payload['phoneNumber']
                        ?? null,

                    'carrierCode' => $payload['carrier_code']
                        ?? $payload['carrierCode']
                        ?? null,

                    'bankCode' => $payload['bank_code']
                        ?? $payload['bankCode']
                        ?? null,

                    'bankAccountNumber' => $payload['account_number']
                        ?? $payload['bankAccountNumber']
                        ?? null,
                ],
                static fn ($value) => $value !== null && $value !== ''
            );

            $orderPayload = [
                'quoteId' => $quoteId,
                'userCountryIsoCode' => $country,
                'userEmail' => $email,
                'userIp' => $payload['user_ip']
                    ?? $payload['userIp']
                    ?? null,

                'deposit' => [
                    'paymentChannel' => 'crypto',
                    'currencyType' => 'crypto',
                    'currencyCode' => $cryptoCode,
                    'amount' => $amount,
                ],

                'payout' => [
                    'paymentChannel' => $paymentChannel,
                    'currencyType' => 'fiat',
                    'currencyCode' => $fiatCurrency,
                    'countryIsoCode' => $country,
                ],

                'fieldsToCreateOrder' => $fieldsToCreateOrder,
                'orderParams' => $payload['merchant_reference'] ?? null,
            ];

            $order = $this->fonbnk->createOrder($orderPayload);

            return $this->normalizeFonbnkOrderResponse(
                $order,
                [
                    'from_currency' => strtolower(
                        (string) ($payload['from_currency'] ?? '')
                    ),
                    'to_currency' => strtolower($fiatCurrency),
                    'from_amount' => $payload['from_amount'] ?? null,
                    'network' => $network,
                    'merchant_reference' => $payload['merchant_reference'] ?? null,
                    'quote_id' => $quoteId,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Fonbnk off-ramp orchestration exception', [
                'merchant_reference' => $payload['merchant_reference'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'provider_exception',
                $e->getMessage(),
                500
            );
        }
    }

    public function refreshOffRamp(
        string $merchantReference,
        array $payload
    ): array {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'off_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            return $this->errorResponse(
                'not_supported',
                'Fonbnk does not support refresh on an existing order. Create a new quote/order instead.',
                501
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->refreshOffRamp(
                $merchantReference,
                $payload
            ),
            'quidax'
        );
    }

    public function confirmOffRamp(string $merchantReference): array
    {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'off_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            $orderId = $this->resolveFonbnkOrderId(
                $merchantReference,
                'off_ramp'
            );

            if (!$orderId) {
                return $this->errorResponse(
                    'missing_order_id',
                    'No Fonbnk order ID stored for this off-ramp transaction.',
                    422
                );
            }

            $result = $this->fonbnk->confirmOrder($orderId);

            return $this->tagProvider(
                $this->normalizeFonbnkOrderResponse($result),
                'fonbnk'
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->confirmOffRamp($merchantReference),
            'quidax'
        );
    }

    public function offRampTransaction(
        string $merchantReference
    ): array {
        $provider = $this->providerForExistingTransaction(
            $merchantReference,
            'off_ramp'
        );

        if ($provider === null) {
            return $this->transactionProviderError();
        }

        if ($provider === 'fonbnk') {
            $orderId = $this->resolveFonbnkOrderId(
                $merchantReference,
                'off_ramp'
            );

            $result = $orderId
                ? $this->fonbnk->getOrder($orderId)
                : $this->fonbnk->getOrder(null, $merchantReference);

            return $this->tagProvider(
                $this->normalizeFonbnkOrderResponse($result),
                'fonbnk'
            );
        }

        if ($provider !== 'quidax') {
            return $this->unsupportedStoredProvider($provider);
        }

        return $this->tagProvider(
            $this->quidax->offRampTransaction($merchantReference),
            'quidax'
        );
    }

    // =========================================================================
    // BANKS - QUIDAX
    // =========================================================================

    public function getBanks(string $country = 'NG'): array
    {
        return $this->tagProvider(
            $this->quidax->getBanks($country),
            'quidax'
        );
    }

    public function getOffRampBanks(string $country = 'NG'): array
    {
        return $this->tagProvider(
            $this->quidax->getOffRampBanks($country),
            'quidax'
        );
    }

    // =========================================================================
    // QUIDAX FAILURE -> CLIENT MUST EXPLICITLY RETRY WITH FONBNK
    // =========================================================================

    protected function useFonbnkResponse(
        string $type,
        string $fiatCurrency,
        array $providerResult
    ): array {
        $required = $this->fonbnkRequiredFields(
            $type,
            $fiatCurrency
        );

        Log::warning(
            'Quidax unavailable - instructing client to use Fonbnk',
            [
                'type' => $type,
                'fiat_currency' => $fiatCurrency,
                'http_status' => $providerResult['http_status'] ?? null,
                'message' => $providerResult['message'] ?? null,
            ]
        );

        return [
            'ok' => false,
            'status' => 'use_fonbnk',
            'message' => 'Primary ramp is temporarily unavailable. Retry explicitly with Fonbnk parameters.',
            'http_status' => 503,
            'provider' => 'quidax',

            'data' => [
                'use_fonbnk' => true,
                'provider_attempted' => 'quidax',
                'force_provider' => 'fonbnk',
                'type' => $type,
                'fiat_currency' => $fiatCurrency,
                'required_fields' => $required,
                'suggested_defaults' => $this->fonbnkSuggestedDefaults(
                    $fiatCurrency
                ),
                'provider_message' => $providerResult['message'] ?? null,
                'provider_http_status' => $providerResult['http_status'] ?? null,
            ],

            'raw' => $providerResult['raw'] ?? null,
        ];
    }

    protected function fonbnkRequiredFields(
        string $type,
        string $fiatCurrency
    ): array {
        $base = [
            'payment_channel',
            'force_provider',
        ];

        $fiat = strtoupper($fiatCurrency);

        if (in_array(
            $fiat,
            ['KES', 'GHS', 'UGX', 'TZS', 'RWF', 'ZMW'],
            true
        )) {
            $base[] = 'phone_number';
            $base[] = 'carrier_code';
        } else {
            $base[] = 'bank_code';
            $base[] = 'account_number';
        }

        if ($type === 'on_ramp') {
            $base[] = 'wallet_address';
        }

        return array_values(array_unique($base));
    }

    protected function fonbnkSuggestedDefaults(
        string $fiatCurrency
    ): array {
        $fiat = strtoupper($fiatCurrency);

        return match ($fiat) {
            'NGN' => [
                'payment_channel' => 'bank',
                'force_provider' => 'fonbnk',
            ],

            'GHS',
            'KES',
            'UGX',
            'TZS',
            'RWF',
            'ZMW' => [
                'payment_channel' => 'mobile_money',
                'force_provider' => 'fonbnk',
            ],

            default => [
                'payment_channel' => 'bank',
                'force_provider' => 'fonbnk',
            ],
        };
    }

    // =========================================================================
    // KYC
    // =========================================================================

    /**
     * Fonbnk KYC gate.
     *
     * If user_id is supplied, the user's Fonbnk KYC status must be approved.
     * If user_id is not supplied, this method does not perform a KYC check.
     */
    protected function assertFonbnkKyc(
        array $payload,
        string $country,
        string $type
    ): ?array {
        $userId = $payload['user_id'] ?? null;

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->errorResponse(
                'user_not_found',
                'Unable to find the customer for Fonbnk KYC verification.',
                404
            );
        }

        $kycResult = $this->fonbnkKyc->status(
            $user,
            $country
        );

        if (!($kycResult['ok'] ?? false)) {
            return $this->errorResponse(
                'kyc_check_failed',
                $kycResult['message']
                    ?? 'Unable to verify Fonbnk KYC status.',
                (int) ($kycResult['http_status'] ?? 422),
                [
                    'kyc_required' => true,
                    'kyc_status' => data_get(
                        $kycResult,
                        'data.current_kyc_status'
                    ),
                    'kyc_type' => data_get(
                        $kycResult,
                        'data.current_kyc_type'
                    ),
                ]
            );
        }

        $kycData = $kycResult['data'] ?? [];

        $approved = (bool) (
            $kycData['is_approved']
            ?? $kycResult['is_approved']
            ?? false
        );

        if (!$approved) {
            return $this->errorResponse(
                'kyc_required',
                'Customer KYC verification is required before this Fonbnk '
                    . str_replace('_', '-', $type)
                    . ' can be initiated.',
                422,
                [
                    'kyc_required' => true,
                    'country_iso_code' => $country,
                    'current_kyc_type' => $kycData['current_kyc_type'] ?? null,
                    'current_kyc_status' => $kycData['current_kyc_status'] ?? null,
                    'current_kyc_status_description'
                        => $kycData['current_kyc_status_description'] ?? null,
                    'passed_kyc_type'
                        => $kycData['passed_kyc_type'] ?? null,
                    'reached_kyc_limit'
                        => $kycData['reached_kyc_limit'] ?? false,
                    'kyc_documents'
                        => $kycData['kyc_documents'] ?? [],
                ]
            );
        }

        return null;
    }

    // =========================================================================
    // FONBNK ORDER / RESPONSE HELPERS
    // =========================================================================

    protected function resolveFonbnkOrderId(
        string $merchantReference,
        string $type
    ): ?string {
        $transaction = RampTransaction::query()
            ->where('merchant_reference', $merchantReference)
            ->where('type', $type)
            ->first();

        if (!$transaction) {
            return null;
        }

        return $transaction->provider_transaction_id
            ?? data_get(
                $transaction->metadata,
                'fonbnk_order_id'
            )
            ?? data_get(
                $transaction->metadata,
                'confirm_data._id'
            )
            ?? data_get(
                $transaction->metadata,
                'confirm_data.id'
            )
            ?? data_get(
                $transaction->metadata,
                'initiate_data._id'
            )
            ?? data_get(
                $transaction->metadata,
                'initiate_data.id'
            )
            ?? data_get(
                $transaction->metadata,
                'initiate_data.order._id'
            )
            ?? data_get(
                $transaction->metadata,
                'initiate_data.order.id'
            )
            ?? null;
    }

    protected function normalizeFonbnkOrderResponse(
        array $result,
        array $extra = []
    ): array {
        $ok = ($result['ok'] ?? false) === true
            || in_array(
                strtolower((string) ($result['status'] ?? '')),
                ['ok', 'success'],
                true
            );

        $data = $this->extractProviderData($result);

        $order = $data['order'] ?? $data;

        if (is_array($order)) {
            $orderId = $order['_id']
                ?? $order['id']
                ?? $order['orderId']
                ?? null;

            $transferDetails = data_get(
                $order,
                'deposit.transferInstructions.transferDetails'
            );

            $address = null;

            if (is_array($transferDetails)) {
                $address = $this->extractTransferDetail(
                    $transferDetails,
                    'recipientWalletAddress'
                );
            }

            if (!$address) {
                $address = data_get(
                    $order,
                    'payout.providedFieldsToCreateOrder.blockchainWalletAddress'
                );
            }

            $data = array_merge(
                $data,
                $extra,
                [
                    'public_id' => $orderId,
                    'reference' => $orderId,
                    'id' => $orderId,

                    'status' => $order['status']
                        ?? ($data['status'] ?? null),

                    'from_amount' => $extra['from_amount']
                        ?? data_get(
                            $order,
                            'deposit.cashout.amountBeforeFees'
                        )
                        ?? data_get(
                            $order,
                            'deposit.cashout.amountAfterFees'
                        )
                        ?? null,

                    'to_amount' => data_get(
                        $order,
                        'payout.cashout.amountAfterFees'
                    )
                        ?? data_get(
                            $order,
                            'payout.cashout.amountBeforeFees'
                        )
                        ?? null,

                    'address' => $address,
                ]
            );
        } else {
            $data = array_merge(
                is_array($data) ? $data : [],
                $extra
            );
        }

        return [
            'ok' => $ok,
            'status' => $result['status']
                ?? ($ok ? 'ok' : 'error'),

            'message' => $result['message']
                ?? ($ok ? 'Success' : 'Fonbnk provider error'),

            'data' => $data,

            'http_status' => (int) (
                $result['http_status']
                ?? ($ok ? 200 : 400)
            ),

            'raw' => $result['raw'] ?? $result,
            'provider' => 'fonbnk',
            'request_id' => $result['request_id'] ?? null,
        ];
    }

    protected function extractTransferDetail(
        array $details,
        string $id
    ): ?string {
        foreach ($details as $row) {
            if (($row['id'] ?? null) === $id) {
                return isset($row['value'])
                    ? (string) $row['value']
                    : null;
            }
        }

        return null;
    }

    protected function extractProviderData(array $result): array
    {
        $data = $result['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    // =========================================================================
    // COUNTRY / FIAT HELPERS
    // =========================================================================

    /**
     * Resolve the country ISO code from a fiat currency.
     *
     * Example:
     * NGN -> NG
     * GHS -> GH
     * KES -> KE
     *

     * Resolve the fiat currency from a country ISO code.
     *
     * Example:
     * NG -> NGN
     * GH -> GHS
     * KE -> KES
     */
    protected function fiatFromCountryCode(
        ?string $countryCode
    ): ?string {
        if (!$countryCode) {
            return null;
        }

        return $this->countryService->fiatFromCountryCode(
            strtoupper(trim($countryCode))
        );
    }

    // =========================================================================
    // RESPONSE HELPERS
    // =========================================================================

    protected function tagProvider(
        array $result,
        string $provider
    ): array {
        $result['provider'] = $provider;
        $result['ok'] = $result['ok'] ?? false;

        $result['status'] = $result['status']
            ?? ($result['ok'] ? 'ok' : 'error');

        $result['message'] = $result['message'] ?? null;
        $result['data'] = $result['data'] ?? null;

        $result['http_status'] = $result['http_status']
            ?? ($result['ok'] ? 200 : 400);

        return $result;
    }

    protected function transactionProviderError(): array
    {
        return $this->errorResponse(
            'transaction_provider_missing',
            'The ramp transaction does not have a stored provider. The provider cannot be determined safely.',
            422
        );
    }

    protected function unsupportedStoredProvider(
        string $provider
    ): array {
        return $this->errorResponse(
            'unsupported_provider',
            'The stored ramp provider is not supported: ' . $provider,
            422,
            [
                'provider' => $provider,
            ]
        );
    }

    protected function errorResponse(
        string $status,
        string $message,
        int $httpStatus = 400,
        $data = null
    ): array {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'http_status' => $httpStatus,
            'raw' => null,
            'provider' => null,
        ];
    }
}