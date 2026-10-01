<?php

namespace App\Services\MultiCurrency;

use App\Models\User;
use App\Services\MultiCurrency\Contracts\CurrencyProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use App\Models\MultiCurrencyWallet;

class MultiCurrencyWalletService
{
    /**
     * Supported currencies by the application.
     *
     * Provider availability should ultimately be controlled
     * through configuration/resolver rather than hard-coded
     * provider logic in this service.
     */
    protected array $supportedCurrencies = [
        'NGN',
        'GHS',
        'KES',
        'TZS',
    ];

    public function __construct(
        protected MultiCurrencyProviderResolver $providerResolver
    ) {
    }

    /**
     * Create a multicurrency wallet/local currency account.
     *
     * The provider is responsible for creating the actual
     * virtual/local account.
     */
    public function createWallet(
        User $user,
        string $currency,
        string $accountType = 'individual'
    ): mixed {
        $currency = strtoupper($currency);

        $this->validateCurrency($currency);

        $accountType = strtolower($accountType);

        if (!in_array($accountType, [
            'individual',
            'corporate',
        ], true)) {
            throw new InvalidArgumentException(
                'Invalid account type.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $currency,
            $accountType
        ) {

            /*
             * Prevent duplicate local wallets for the same
             * user/currency combination.
             *
             * We check this before making the provider request.
             */
            $existingWallet = $this->findUserWallet(
                $user,
                $currency
            );

            if ($existingWallet) {
                return $existingWallet;
            }

            $provider = $this->providerResolver->resolve(
                $currency
            );

            try {

                $result = $provider->createVirtualAccount(
                    user: $user,
                    currency: $currency,
                    accountType: $accountType
                );

                /*
                 * The provider implementation should normalize
                 * its response into a structure that this service
                 * understands.
                 *
                 * We deliberately don't save provider-specific
                 * fields here until the wallet model/schema is
                 * established.
                 */
                return $this->persistWallet(
                    user: $user,
                    currency: $currency,
                    accountType: $accountType,
                    provider: $provider,
                    providerResponse: $result
                );

            } catch (Throwable $e) {

                Log::error(
                    'Failed to create multicurrency wallet.',
                    [
                        'user_id' => $user->id,
                        'currency' => $currency,
                        'account_type' => $accountType,
                        'exception' => $e->getMessage(),
                    ]
                );

                throw $e;
            }
        });
    }

    /**
     * Get all multicurrency wallets belonging to a user.
     */
    public function getUserWallets(User $user)
    {
        /*
         * Replace `multicurrencyWallets()` with the actual
         * relationship on your User model.
         */
        return $user->multicurrencyWallets()
            ->latest()
            ->get();
    }

    /**
     * Get a user's wallet for a specific currency.
     */
    public function getWallet(
        User $user,
        string $currency
    ): mixed {
        $currency = strtoupper($currency);

        $this->validateCurrency($currency);

        return $this->findUserWallet(
            $user,
            $currency
        );
    }

    /**
     * Get collections/deposits for a user's currency wallet.
     */
    public function getCollections(
        User $user,
        string $currency
    ): array {
        $currency = strtoupper($currency);

        $this->validateCurrency($currency);

        $wallet = $this->findUserWallet(
            $user,
            $currency
        );

        if (!$wallet) {
            throw new InvalidArgumentException(
                "You do not have a {$currency} wallet."
            );
        }

        $provider = $this->providerResolver->resolve(
            $currency
        );

        /*
         * The exact provider account identifier should be
         * normalized by the wallet/provider implementation.
         */
        $virtualAccount = $this->getVirtualAccountIdentifier(
            $wallet
        );

        if (!$virtualAccount) {
            throw new RuntimeException(
                'Virtual account information is missing.'
            );
        }

        return $provider->getCollections(
            virtualAccount: $virtualAccount,
            business: $this->getProviderBusiness($wallet)
        );
    }

    /**
     * Verify a deposit using merchant reference.
     *
     * The merchant reference is also checked against the
     * user's wallet where possible.
     */
    public function verifyDeposit(
        User $user,
        string $merchantReference
    ): ?array {
        $merchantReference = trim($merchantReference);

        if ($merchantReference === '') {
            throw new InvalidArgumentException(
                'Merchant reference is required.'
            );
        }

        /*
         * If you maintain a local wallet transaction/deposit
         * table, this is where you should first locate the
         * user's transaction and therefore determine the
         * provider/currency.
         *
         * For now we resolve the provider from the supported
         * currencies.
         *
         * This should be replaced with transaction-based
         * provider resolution once the ledger is implemented.
         */
        foreach ($this->supportedCurrencies as $currency) {

            try {

                $provider = $this->providerResolver->resolve(
                    $currency
                );

                $deposit = $provider->verifyDeposit(
                    merchantReference: $merchantReference
                );

                if ($deposit) {
                    return $deposit;
                }

            } catch (Throwable $e) {

                Log::warning(
                    'Multicurrency deposit verification attempt failed.',
                    [
                        'user_id' => $user->id,
                        'currency' => $currency,
                        'merchant_reference' => $merchantReference,
                        'exception' => $e->getMessage(),
                    ]
                );
            }
        }

        return null;
    }

    /**
     * Get exchange rates.
     */
    public function getRates(
        ?string $from = null,
        ?string $to = null
    ): array {
        $from = $from
            ? strtoupper($from)
            : null;

        $to = $to
            ? strtoupper($to)
            : null;

        if ($from) {
            $this->validateCurrency($from);
        }

        if ($to) {
            $this->validateCurrency($to);
        }

        /*
         * If a specific source currency is supplied, use its
         * configured provider.
         */
        if ($from) {

            $provider = $this->providerResolver->resolve(
                $from
            );

            return $provider->getRates(
                from: $from,
                to: $to
            );
        }

        /*
         * No source currency supplied.
         *
         * The resolver should determine the default provider.
         */
        $provider = $this->providerResolver->resolveDefault();

        return $provider->getRates(
            from: null,
            to: $to
        );
    }

    /**
     * Get banks supported for a currency/country.
     */
    public function getBanks(
        string $currency,
        string $country
    ): array {
        $currency = strtoupper($currency);
        $country = strtoupper($country);

        $this->validateCurrency($currency);

        if (strlen($country) !== 2) {
            throw new InvalidArgumentException(
                'Country must be a two-letter country code.'
            );
        }

        $provider = $this->providerResolver->resolve(
            $currency
        );

        return $provider->getBanks(
            currency: $currency,
            country: $country
        );
    }

    /**
     * Transfer funds to another wallet on the provider.
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

        $beneficiaryWalletNumber = trim(
            $beneficiaryWalletNumber
        );

        $description = trim($description);

        if ($beneficiaryWalletNumber === '') {
            throw new InvalidArgumentException(
                'Beneficiary wallet number is required.'
            );
        }

        if ($description === '') {
            throw new InvalidArgumentException(
                'Transfer description is required.'
            );
        }

        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException(
                'Transfer amount must be greater than zero.'
            );
        }

        $wallet = $this->findUserWallet(
            $user,
            $currency
        );

        if (!$wallet) {
            throw new InvalidArgumentException(
                "You do not have a {$currency} wallet."
            );
        }

        $provider = $this->providerResolver->resolve(
            $currency
        );

        try {

            /*
             * IMPORTANT:
             *
             * Before this becomes production-ready, this operation
             * should create a local transaction/idempotency record
             * BEFORE calling the provider.
             *
             * That protects against duplicate requests and gives
             * us a reconciliation mechanism.
             */
            return $provider->transferToWallet(
                user: $user,
                currency: $currency,
                beneficiaryWalletNumber:
                    $beneficiaryWalletNumber,
                amount: (string) $amount,
                description: $description
            );

        } catch (Throwable $e) {

            Log::error(
                'Multicurrency wallet transfer failed.',
                [
                    'user_id' => $user->id,
                    'currency' => $currency,
                    'amount' => $amount,
                    'beneficiary_wallet_number' =>
                        $beneficiaryWalletNumber,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Find a user's wallet by currency.
     *
     * Centralized here so controllers/services don't need
     * to repeat the wallet lookup logic.
     */
protected function findUserWallet(
    User $user,
    string $currency
): ?MultiCurrencyWallet {
    $currency = strtoupper(trim($currency));

    $wallet = $user->multicurrencyWallets()
        ->where('currency', $currency)
        ->first();


    return $wallet;
}

    /**
     * Extract provider virtual account identifier.
     *
     * Adjust this once the wallet model is finalized.
     */
    protected function getVirtualAccountIdentifier(
        mixed $wallet
    ): ?string {
        return $wallet->virtual_account
            ?? $wallet->virtual_account_number
            ?? $wallet->account_number
            ?? null;
    }

    /**
     * Get provider business identifier from wallet.
     */
    protected function getProviderBusiness(
        mixed $wallet
    ): ?string {
        return $wallet->business_id
            ?? $wallet->provider_business_id
            ?? null;
    }

    /**
     * Persist a newly created wallet.
     *
     * This method assumes you will have a MulticurrencyWallet
     * model. Adjust the fields to your actual migration.
     */
  protected function persistWallet(
    User $user,
    string $currency,
    string $accountType,
    CurrencyProviderInterface $provider,
    array $providerResponse
): MultiCurrencyWallet {
    $virtualAccountNumber = data_get(
        $providerResponse,
        'virtual_account_number'
    );

    $providerReference = data_get(
        $providerResponse,
        'provider_reference'
    );

    if (
        !is_string($virtualAccountNumber)
        || trim($virtualAccountNumber) === ''
    ) {
        throw new RuntimeException(
            'Provider did not return a virtual account number.'
        );
    }

    if (
        !is_string($providerReference)
        || trim($providerReference) === ''
    ) {
        throw new RuntimeException(
            'Provider did not return a provider reference.'
        );
    }

    return MultiCurrencyWallet::create([
        'user_id' => $user->id,

        'currency' => strtoupper($currency),

        'account_type' => strtolower($accountType),

        'provider' => strtolower(
            $this->providerName($provider)
        ),

        'provider_business_id' =>
            data_get(
                $providerResponse,
                'provider_business_id'
            ),

        'virtual_account_number' =>
            trim($virtualAccountNumber),

        'account_name' =>
            data_get(
                $providerResponse,
                'account_name'
            ),

        'bank_name' =>
            data_get(
                $providerResponse,
                'bank_name'
            ),

        'bank_code' =>
            data_get(
                $providerResponse,
                'bank_code'
            ),

        'provider_wallet_number' =>
            data_get(
                $providerResponse,
                'provider_wallet_number'
            ),

        'provider_reference' =>
            trim($providerReference),

        'provider_account_id' =>
            data_get(
                $providerResponse,
                'provider_account_id'
            ),

        'status' =>
            data_get(
                $providerResponse,
                'status',
                'pending'
            ),

        'can_receive' =>
            (bool) data_get(
                $providerResponse,
                'can_receive',
                false
            ),

        'can_send' =>
            (bool) data_get(
                $providerResponse,
                'can_send',
                false
            ),

        'provider_metadata' =>
            data_get(
                $providerResponse,
                'provider_metadata',
                []
            ),
    ]);
}

    /**
     * Get provider name from implementation.
     */
    protected function providerName(
        CurrencyProviderInterface $provider
    ): string {
        return class_basename($provider);
    }

    /**
     * Validate supported application currency.
     */
    protected function validateCurrency(
        string $currency
    ): void {
        if (!in_array($currency, $this->supportedCurrencies, true)) {
            throw new InvalidArgumentException(
                "Unsupported currency: {$currency}."
            );
        }
    }
    /**
 * Initiate a payin/funding transaction.
 *
 * This creates a Fincra payment flow for funding
 * the user's multicurrency wallet.
 */
public function initiatePayin(
    User $user,
    string $currency,
    string $amount,
    string $paymentMethod = 'bank_transfer',
    ?string $quoteReference = null
): array {
    $currency = strtoupper($currency);

    $this->validateCurrency($currency);

    if (!is_numeric($amount) || (float) $amount <= 0) {
        throw new InvalidArgumentException(
            'Amount must be greater than zero.'
        );
    }

    $allowedMethods = [
        'bank_transfer',
        'mobile_money',
        'card',
        'eft',
        'payattitude',
    ];

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        throw new InvalidArgumentException(
            'Unsupported payment method.'
        );
    }

    /*
     * Ensure the user has a wallet for this currency.
     */
    $wallet = $this->findUserWallet(
        $user,
        $currency
    );

    if (!$wallet) {
        throw new InvalidArgumentException(
            "You do not have a {$currency} wallet."
        );
    }

    $provider = $this->providerResolver->resolve(
        $currency
    );

    return $provider->initiatePayin(
        user: $user,
        currency: $currency,
        amount: (string) $amount,
        paymentMethod: $paymentMethod,
        quoteReference: $quoteReference
    );
}

    /**
     * Initiate a payout to a beneficiary's bank account.
     *
     * Debits the user's currency wallet and sends funds out
     * to an external bank account via the resolved provider.
     */
    public function initiateBankPayout(
        User $user,
        string $sourceCurrency,
        string $destinationCurrency,
        string $amount,
        array $beneficiary,
        ?string $description = null,
        ?string $quoteReference = null
    ): array {
        $sourceCurrency = strtoupper($sourceCurrency);
        $destinationCurrency = strtoupper($destinationCurrency);

        $this->validateCurrency($sourceCurrency);
        $this->validateCurrency($destinationCurrency);

        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException(
                'Payout amount must be greater than zero.'
            );
        }

        if (empty($beneficiary)) {
            throw new InvalidArgumentException(
                'Beneficiary details are required.'
            );
        }

        if (
            $sourceCurrency !== $destinationCurrency
            && !$quoteReference
        ) {
            throw new InvalidArgumentException(
                'A quote reference is required for cross-currency payouts.'
            );
        }

        $wallet = $this->findUserWallet(
            $user,
            $sourceCurrency
        );

        if (!$wallet) {
            throw new InvalidArgumentException(
                "You do not have a {$sourceCurrency} wallet."
            );
        }

        $provider = $this->providerResolver->resolve(
            $sourceCurrency
        );

        try {

            /*
             * IMPORTANT:
             *
             * As with transferToWallet(), before this becomes
             * production-ready, this operation should create a
             * local transaction/idempotency record BEFORE calling
             * the provider, to protect against duplicate requests
             * and to support reconciliation.
             */
            return $provider->initiateBankPayout(
                sourceCurrency: $sourceCurrency,
                destinationCurrency: $destinationCurrency,
                amount: (string) $amount,
                beneficiary: $beneficiary,
                description: $description,
                quoteReference: $quoteReference
            );

        } catch (Throwable $e) {

            Log::error(
                'Multicurrency bank payout failed.',
                [
                    'user_id' => $user->id,
                    'source_currency' => $sourceCurrency,
                    'destination_currency' => $destinationCurrency,
                    'amount' => $amount,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
        /**
     * Initiate a conversion from a previously generated quote.
     *
     * The quote must have been generated by this user and must
     * still be cached (Fincra quotes expire after 30 seconds).
     */
    public function convertCurrency(
        User $user,
        string $quoteReference
    ): array {
        $quoteReference = trim($quoteReference);

        if ($quoteReference === '') {
            throw new InvalidArgumentException(
                'Quote reference is required.'
            );
        }

        $cachedQuote = Cache::get(
            "fincra_quote:{$quoteReference}"
        );

        if (!$cachedQuote) {
            throw new InvalidArgumentException(
                'This quote has expired or does not exist. Please generate a new quote.'
            );
        }

        if (
            (int) data_get($cachedQuote, 'user_id')
            !== (int) $user->id
        ) {
            throw new InvalidArgumentException(
                'This quote does not belong to you.'
            );
        }

        $sourceCurrency = data_get(
            $cachedQuote,
            'source_currency'
        );

        /*
         * Quotes with a source currency (disbursement-style)
         * resolve to that currency's provider. Quotes with no
         * source currency (e.g. fliqpay_wallet conversions)
         * resolve to the default provider, matching how
         * MultiCurrencyQuoteService::generate() resolved the
         * provider when the quote was first created.
         */
        $provider = $sourceCurrency
            ? $this->providerResolver->resolve($sourceCurrency)
            : $this->providerResolver->resolveDefault();

        try {

            return $provider->initiateConversion(
                quoteReference: $quoteReference
            );

        } catch (Throwable $e) {

            Log::error(
                'Multicurrency conversion failed.',
                [
                    'user_id' => $user->id,
                    'quote_reference' => $quoteReference,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
    /**
 * Verify the status of a currency conversion.
 *
 * The reference should be the provider conversion reference
 * returned when the conversion was initiated.
 *
 * @throws InvalidArgumentException
 * @throws Throwable
 */
public function verifyConversionStatus(
    User $user,
    string $reference
): array {
    $reference = trim($reference);

    if ($reference === '') {
        throw new InvalidArgumentException(
            'Conversion reference is required.'
        );
    }

    /*
     * At this stage, the conversion reference itself does not
     * contain a currency from which we can reliably resolve
     * the provider.
     *
     * If your local conversion transaction table exists, the
     * preferred implementation is to find the conversion there
     * and resolve its provider directly.
     *
     * Until then, use the default provider, which matches the
     * current conversion flow for fliqpay_wallet conversions.
     */
    $provider = $this->providerResolver->resolveDefault();

    try {

        return $provider->verifyConversionStatus(
            reference: $reference
        );

    } catch (Throwable $e) {

        Log::error(
            'Multicurrency conversion status verification failed.',
            [
                'user_id' => $user->id,
                'reference' => $reference,
                'exception' => $e->getMessage(),
            ]
        );

        throw $e;
    }
}
public function verifyAccount(
    string $accountNumber,
    string $bankCode,
    string $currency,
    string $type = 'nuban',
    ?string $bankSwiftCode = null,
    ?string $mobileMoneyCode = null,
    ?string $iban = null
): array {
    return $this->provider->verifyAccount(
        accountNumber: $accountNumber,
        bankCode: $bankCode,
        currency: strtoupper($currency),
        type: $type,
        bankSwiftCode: $bankSwiftCode,
        mobileMoneyCode: $mobileMoneyCode,
        iban: $iban
    );
}
}