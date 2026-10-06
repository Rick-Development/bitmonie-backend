<?php

return [
    /*
     * The path where the documentation will be accessible.
     * Accessible only via direct URL (not linked anywhere on the frontend landing page).
     */
    'doc_route' => 'docs/api',

    /*
     * Only routes matching this path will be included in the API documentation.
     */
    'api_path' => 'api',

    'api_domain' => null,

    /*
     * Documentation appearance and metadata.
     */
    'ui' => [
        'title' => 'Bitmonie / Cryptomart API Reference',
        'theme' => 'light',
        'hide_try_it' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'include',
    ],

    /*
     * The list of servers available in the documentation.
     */
    'servers' => [
        'Local' => env('APP_URL', 'http://127.0.0.1:8000'),
    ],

    'middleware' => [
        'web',
        \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
    ],

    'extensions' => [
        \App\Docs\ScrambleApiEnhancerExtension::class,
    ],
];
