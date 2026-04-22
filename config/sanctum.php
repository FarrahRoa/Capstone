<?php

use Laravel\Sanctum\Sanctum;

return [
    /*
    |--------------------------------------------------------------------------
    | Sanctum Guard
    |--------------------------------------------------------------------------
    |
    | This project uses Sanctum personal access tokens (Authorization: Bearer).
    | Cookie-based SPA authentication is not required for the current frontend.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Only relevant for cookie-based SPA auth. Kept explicit to avoid “works on
    | my machine” cross-origin confusion if a future deployment uses cookies.
    |
    */

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort() ? ','.Sanctum::currentApplicationUrlWithPort() : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    */

    'expiration' => env('SANCTUM_EXPIRATION'),

    /*
    |--------------------------------------------------------------------------
    | Token Inactivity Timeout (Bearer tokens)
    |--------------------------------------------------------------------------
    |
    | For cloud deployment, enforce a server-side inactivity cutoff even when
    | the frontend stores the Bearer token in persistent storage.
    |
    | - idle_timeout_minutes: normal user inactivity timeout
    | - idle_timeout_admin_minutes: admin portal inactivity timeout (stricter)
    |
    | Set to 0 to disable.
    |
    */

    'idle_timeout_minutes' => (int) env('SANCTUM_IDLE_TIMEOUT_MINUTES', 60 * 24 * 14), // 14 days

    'idle_timeout_admin_minutes' => (int) env('SANCTUM_ADMIN_IDLE_TIMEOUT_MINUTES', 60 * 8), // 8 hours

    /*
    |--------------------------------------------------------------------------
    | Sanctum Token Prefix
    |--------------------------------------------------------------------------
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];

