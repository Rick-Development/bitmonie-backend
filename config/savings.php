<?php

/*
|--------------------------------------------------------------------------
| Savings configuration
|--------------------------------------------------------------------------
|
| All monetary values are decimal strings (NGN, 2dp). Anything marked
| "PRODUCT DECISION" is a business policy that must be confirmed by the
| product owner; the defaults mirror what the legacy implementation did.
|
*/

return [
    'currency' => 'NGN',

    'limits' => [
        'min_transaction' => env('SAVINGS_MIN_TRANSACTION', '100.00'),
        'max_transaction' => env('SAVINGS_MAX_TRANSACTION', '5000000.00'),
        'max_target_amount' => env('SAVINGS_MAX_TARGET_AMOUNT', '1000000000.00'),
        'max_active_targets' => (int) env('SAVINGS_MAX_ACTIVE_TARGETS', 50),
        'max_auto_plans' => (int) env('SAVINGS_MAX_AUTO_PLANS', 20),
    ],

    'scheduler' => [
        // Default execution time (HH:MM, in the plan's timezone).
        'default_run_time' => '08:00',
        'default_timezone' => env('SAVINGS_DEFAULT_TIMEZONE', 'Africa/Lagos'),
        // A due instalment older than this is recorded as SKIPPED (missed),
        // never collected late and never collected in bulk.
        'grace_minutes' => (int) env('SAVINGS_SCHEDULE_GRACE_MINUTES', 360),
        // Bounded retries for retryable failures (e.g. insufficient funds).
        'max_attempts' => (int) env('SAVINGS_MAX_ATTEMPTS', 3),
        'backoff_minutes' => [60, 180],
        // A PROCESSING execution older than this is considered abandoned.
        'stale_lock_minutes' => 15,
        'batch_size' => 200,
    ],

    'target' => [
        // PRODUCT DECISION: fee (%) charged when a target with a deadline is
        // closed early. Mirrors the legacy 2% rule. Set to 0 to disable.
        // The user must explicitly acknowledge the fee before it is charged.
        'early_closure_fee_percent' => env('SAVINGS_TARGET_EARLY_CLOSURE_FEE_PERCENT', '2.00'),
    ],

    'flexi' => [
        // Existing legacy product rate (5% monthly ~ 60% APR, simple daily
        // accrual). PRODUCT DECISION: confirm before relying on it.
        'interest_apr_percent' => env('SAVINGS_FLEXI_INTEREST_APR', '60'),
        'interest_enabled' => (bool) env('SAVINGS_FLEXI_INTEREST_ENABLED', true),
    ],
];
