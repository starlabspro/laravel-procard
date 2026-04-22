<?php

declare(strict_types=1);

return [

    'base_url' => env('PROCARD_BASE_URL'),

    'merchant_id' => env('PROCARD_MERCHANT_ID'),

    'secret_key' => env('PROCARD_SECRET_KEY'),

    'currency' => env('PROCARD_CURRENCY', 'EUR'),

    'language' => env('PROCARD_LANGUAGE', 'en'),

    'urls' => [
        'approve_url' => env('PROCARD_APPROVE_URL'),
        'decline_url' => env('PROCARD_DECLINE_URL'),
        'cancel_url' => env('PROCARD_CANCEL_URL'),
        'callback_url' => env('PROCARD_CALLBACK_URL'),
    ],

    'defaults' => [
        'auth_type' => env('PROCARD_AUTH_TYPE'),
        'secure_type' => env('PROCARD_SECURE_TYPE'),
    ],

    'http' => [
        'timeout' => (int) env('PROCARD_HTTP_TIMEOUT', 15),
    ],

    'routes' => [
        'enabled' => (bool) env('PROCARD_ROUTES_ENABLED', true),
        'prefix' => env('PROCARD_ROUTES_PREFIX', ''),
        'middleware' => env('PROCARD_ROUTES_MIDDLEWARE', 'web'),
    ],

    'api' => [
        'enabled' => (bool) env('PROCARD_API_MODE', false),
    ],

];
