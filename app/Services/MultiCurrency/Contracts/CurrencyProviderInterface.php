<?php

namespace App\Services\MultiCurrency\Contracts;

use App\Models\User;

interface CurrencyProviderInterface
{
    /**
     * Create a local-currency virtual account
     * for the authenticated user.
     */
    public function createVirtualAccount(
        User $user,
        string $currency,
        string $accountType = 'individual'
    ): array;

    /**
     * Get collections received into a virtual account.
     */
    public function getCollections(
        string $virtualAccount,
        ?string $business = null
    ): array;

    /**
     * Verify a collection/deposit by merchant reference.
     *
     * Returns null when the transaction does not exist
     * or has not yet been received.
     */
    public function verifyDeposit(
        string $merchantReference
    ): ?array;

    /**
     * Get the provider's current exchange rates.
     */
    public function getRates(
        ?string $from = null,
        ?string $to = null
    ): array;

    /**
     * Get supported banks for a currency/country.
     */
    public function getBanks(
        string $currency,
        string $country
    ): array;

    /**
     * Initiate a customer payin/funding transaction.
     *
     * This is for an active payment flow such as
     * bank transfer, mobile money, card, etc.
     */
    public function initiatePayin(
        User $user,
        string $currency,
        string $amount,
        string $paymentMethod = 'bank_transfer',
        ?string $quoteReference = null
    ): array;

    /**
     * Transfer funds to another wallet within
     * the provider's ecosystem.
     */
    public function transferToWallet(
        User $user,
        string $currency,
        string $beneficiaryWalletNumber,
        string $amount,
        string $description
    ): array;

    
    /**
     * Generate a quote.
     *
     * sourceCurrency and amount are nullable — some quote types
     * (e.g. transactionType: conversion, paymentDestination:
     * fliqpay_wallet) don't require a source currency or amount.
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
    ): array;

        /**
     * Initiate a payout to a beneficiary's bank account.
     *
     * If sourceCurrency and destinationCurrency differ, a
     * quoteReference from the provider's quote step is required.
     */
    public function initiateBankPayout(
        string $sourceCurrency,
        string $destinationCurrency,
        string $amount,
        array $beneficiary,
        ?string $description = null,
        ?string $quoteReference = null
    ): array;

        /**
     * Initiate a currency conversion from a previously
     * generated quote.
     */
    public function initiateConversion(
        string $quoteReference,
        ?string $customerReference = null
    ): array;
    /**
 * Verify the status of a currency conversion.
 *
 * The reference should be the conversion reference returned
 * by the provider when the conversion was initiated.
 */
public function verifyConversion(
    string $conversionReference
): ?array;
/**
 * Verify/resolve a bank account.
 *
 * Used to retrieve the account holder's details before
 * initiating a bank payout.
 */
/**
 * Verify/resolve a beneficiary account.
 *
 * Used to retrieve the account holder's details before
 * initiating a bank payout.
 */
public function verifyAccount(
    string $type,
    ?string $accountNumber = null,
    ?string $bankCode = null,
    ?string $bankSwiftCode = null,
    ?string $mobileMoneyCode = null,
    ?string $iban = null,
    ?string $currency = null
): array;
}