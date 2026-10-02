<?php

return [
    'currency' => env('REFERRAL_WALLET_CURRENCY', 'NGN'),
    'earning_currency' => env('REFERRAL_EARNING_CURRENCY', 'NGN'),
    'naira_exchange_rate' => env('REFERRAL_TO_NAIRA_RATE', 1),
    'min_withdrawal_amount' => env('REFERRAL_MIN_WITHDRAWAL_AMOUNT', 1000),
    'max_withdrawal_amount' => env('REFERRAL_MAX_WITHDRAWAL_AMOUNT'),
];
