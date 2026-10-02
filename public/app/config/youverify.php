<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YouVerify API Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used when communicating with the YouVerify APIs.
    | Keep the keys in your .env file and never commit them.
    |
    */

    'base_url' => env('YOUVERIFY_BASE_URL', 'https://api.youverify.co'),

    // Secret / server-side key (used from your backend only)
    'secret_key' => env('YOUVERIFY_SECRET_KEY'),

    // Optional public key (if you ever need it on the frontend)
    'public_key' => env('YOUVERIFY_PUBLIC_KEY'),

    // Webhook signing secret (if YouVerify provides one)
    'webhook_secret' => env('YOUVERIFY_WEBHOOK_SECRET'),

    // Default timeout (seconds) for HTTP calls
    'timeout' => env('YOUVERIFY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Paths
    |--------------------------------------------------------------------------
    |
    | These are relative paths from base_url. Adjust them to match the exact
    | YouVerify API documentation for your account / region.
    |
    */

    'endpoints' => [
        // Start an ID verification
        'start_id_verification' => env('YOUVERIFY_START_ID_PATH', '/v2/identity/id-verification'),

        // Get verification status by reference
        'get_verification_status' => env('YOUVERIFY_GET_STATUS_PATH', '/v2/identity/verifications'),
    ],
];



