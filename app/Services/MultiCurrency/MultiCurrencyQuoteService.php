<?php

namespace App\Services\MultiCurrency;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class MultiCurrencyQuoteService
{
    public function __construct(
        protected MultiCurrencyProviderResolver $resolver
    ) {
    }

       public function generate(
        User $user,
        ?string $sourceCurrency,
        string $destinationCurrency,
        ?string $amount = null,
        string $action = 'send',
        string $transactionType = 'disbursement',
        string $feeBearer = 'customer',
        string $paymentDestination = 'bank_account',
        ?string $paymentScheme = null,
        string $beneficiaryType = 'individual'
    ): array {

        $sourceCurrency = $sourceCurrency
            ? strtoupper($sourceCurrency)
            : null;

        $destinationCurrency = strtoupper(
            $destinationCurrency
        );

        if (
            $sourceCurrency
            && $sourceCurrency === $destinationCurrency
        ) {
            throw new InvalidArgumentException(
                'Source and destination currencies must be different.'
            );
        }

        if ($amount !== null && (float) $amount <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        /*
         * Disbursement quotes debit an existing source-currency
         * wallet, so we resolve the provider from that wallet.
         *
         * Quotes with no source currency (e.g. transactionType:
         * conversion, paymentDestination: fliqpay_wallet) have no
         * wallet to check against, so they resolve against the
         * default provider instead.
         */
        if ($sourceCurrency) {

            $wallet = $user->multicurrencyWallets()
                ->where('currency', $sourceCurrency)
                ->first();

            if (!$wallet) {
                throw new InvalidArgumentException(
                    "You do not have a {$sourceCurrency} wallet."
                );
            }

            if (!$wallet->business_id) {
                throw new InvalidArgumentException(
                    'Wallet provider business is not configured.'
                );
            }

            $provider = $this->resolver->resolve(
                $sourceCurrency
            );

        } else {

            if ($transactionType === 'disbursement') {
                throw new InvalidArgumentException(
                    'Source currency is required for disbursement quotes.'
                );
            }

            $provider = $this->resolver->resolveDefault();
        }

        /*
         * NOTE: provider->generateQuote() does not accept a
         * business parameter — each provider implementation is
         * responsible for its own configured business ID
         * (see FincraService, which uses $this->businessId
         * internally). The wallet's business_id is validated
         * above but intentionally not passed through here.
         */
        $quote = $provider->generateQuote(
            sourceCurrency: $sourceCurrency,
            destinationCurrency: $destinationCurrency,
            amount: $amount,
            action: $action,
            transactionType: $transactionType,
            feeBearer: $feeBearer,
            paymentDestination: $paymentDestination,
            paymentScheme: $paymentScheme,
            beneficiaryType: $beneficiaryType,
            delay: false
        );

        /*
         * Fincra quotes are valid for 30 seconds.
         *
         * We keep a short-lived local copy so that the
         * frontend can reference the quote.
         */
        $reference =
            data_get($quote, 'data.reference')
            ?? data_get($quote, 'reference');

        if ($reference) {

            Cache::put(
                "fincra_quote:{$reference}",
                [
                    'user_id' => $user->id,
                    'source_currency' => $sourceCurrency,
                    'destination_currency' => $destinationCurrency,
                    'amount' => $amount,
                    'response' => $quote,
                ],
                now()->addSeconds(30)
            );
        }

        return $quote;
    }
}