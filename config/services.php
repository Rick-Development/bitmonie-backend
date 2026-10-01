<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'quidax' => [
        'secret' => env('QUIDAX_SECRET_KEY'),
        'private' => env('QUIDAX_PRIVATE_KEY'),
        'url' => env('QUIDAX_API_URL'),
        'ramp_url' => env('QUIDAX_RAMP_URL'),
        'webhook_secret' => env('QUIDAX_WEBHOOK_SECRET'),
        'withdrawal_reservation_ttl_hours' => env('QUIDAX_WITHDRAWAL_RESERVATION_TTL_HOURS', 24),
    ],

    'quidax_ramp' => [
        'base_url'    => env('QUIDAX_RAMP_BASE_URL', 'https://ramp-be.quidax.io/api/v1'),
        'private_key' => env('QUIDAX_RAMP_SECRET'),
        'webhook_secret' => env('QUIDAX_RAMP_WEBHOOK_SECRET', env('QUIDAX_RAMP_SECRET')),
    ],

'fcm'=>[
    'project_id'=>env('FCM_PROJECT_ID'),
    'service_account' => json_decode(env('FIREBASE_CREDENTIALS_JSON'), true),
],
    'busha' => [
        'base_url' => env('BUSHA_BASE_URL', 'https://api.connect.busha.co'),
        'api_key' => env('BUSHA_API_KEY'),
        'webhook_secret' => env('BUSHA_WEBHOOK_SECRET'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],


    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID',""),
        'client_secret' => env('GOOGLE_CLIENT_SECRET',""),
        'redirect' => env('GOOGLE_CALLBACK',""),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID',""),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET',""),
        'redirect' => env('FACEBOOK_CALLBACK',""),
    ],
    'payscribe' => [
        'secret' => env('PAYSCRIBE_SECRET'),
        'key' => env('PAYSCRIBE_PUBLIC_API'),
        'api_url' => env('PAYSCRIBE_URL')
    ],

    'youverify' => [
        'base_url' => 'https://api.youverify.co/v2/',
        'key' => 'KfjLuNhj.CnNSPnbzyo8m4G53qJi5xSMaf0ak6rmY5B0u',
        'public_key' => '690b581fffda175b2b2a21d8',
        'webhook_key' => '6yNnWj0jXq7VwVujOdHKeISVTSJzadVD2ah9',
    ],

    'reloadly' => [
        'client_id' => env('RELOADLY_CLIENT_ID'),
        'client_secret' => env('RELOADLY_CLIENT_SECRET'),
        'base_url' => env('RELOADLY_ENV', 'sandbox') === 'production' 
            ? 'https://giftcards.reloadly.com' 
            : 'https://giftcards-sandbox.reloadly.com',
        'auth_url' => 'https://auth.reloadly.com/oauth/token',
    ],

    'safeHeaven' => [
        'client_id' => env('SAFE_HEAVEN_CLIENTID'),
        'client_assertion' => env('SAFE_HEAVEN_CLIENT_ASSERTION'),
        'api_url' => env('SAFE_HEAVEN_URL'),
        'main_account' => env('SAFE_HEAVEN_MAIN_ACCOUNT'), // Target for debits
        'bank_code' => env('SAFE_HEAVEN_BANK_CODE', '090286'), // SafeHaven MFB Code
    ],
'sudo' => [
    'base_url' => env(
        'SUDO_BASE_URL',
        'https://api.sandbox.sudo.cards'
    ),

    'api_key' => env('SUDO_API_KEY'),

    'timeout' => env('SUDO_TIMEOUT', 30),
    'settlement_bank_code'      => env('SUDO_SETTLEMENT_BANK_CODE'),      // e.g. 058
    'settlement_account_number' => env('SUDO_SETTLEMENT_ACCOUNT_NUMBER'), // e.g. 0123456789
    'settlement_account_name'   => env('SUDO_SETTLEMENT_ACCOUNT_NAME'),
    'settlement_debit_account_id'=>env('SUDO_SETTLEMENT_DEBIT_ACCOUNT_ID')
],
    'yellow_card' => [
        'base_url' => env('YELLOW_CARD_BASE_URL', 'https://sandbox.api.yellowcard.io/business'),
        'api_key' => env('YELLOW_CARD_API_KEY'),
        'secret_key' => env('YELLOW_CARD_SECRET_KEY'),
        'timeout' => env('YELLOW_CARD_TIMEOUT', 20),
        'retries' => env('YELLOW_CARD_RETRIES', 2),
        'retry_sleep_ms' => env('YELLOW_CARD_RETRY_SLEEP_MS', 500),
        'coverage_cache_ttl' => env('YELLOW_CARD_COVERAGE_CACHE_TTL', 3600),
    ],
    'fincra' => [ 
        'base_url' => env( 'FINCRA_BASE_URL', 'https://sandboxapi.fincra.com' ), 
        'secret_key' => env('FINCRA_SECRET_KEY'), 
        'public_key' => env('FINCRA_PUBLIC_KEY'), 
        'webhook_encryption_key' => env( 'FINCRA_WEBHOOK_ENCRYPTION_KEY' ),
         'business_id' => env('FINCRA_BUSINESS_ID'), 
         'timeout' => (int) env( 'FINCRA_TIMEOUT', 30 ), 
         'retry_times' => (int) env( 'FINCRA_RETRY_TIMES', 2 ),
          ],

];
