<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Record origin (primary vs local fallback)
    |--------------------------------------------------------------------------
    |
    | On the temporary fallback server during cloud downtime, set
    | APP_SYNC_RECORD_ORIGIN=local_fallback so new reservations are tracked
    | for upload back to the primary cloud when it returns.
    |
    */
    'record_origin' => env('APP_SYNC_RECORD_ORIGIN', 'primary'),

    /*
    |--------------------------------------------------------------------------
    | Optional HTTP push to primary cloud
    |--------------------------------------------------------------------------
    |
    | POST JSON payloads to this URL (Bearer CLOUD_SYNC_PUSH_TOKEN when set).
    | Idempotency-Key header uses reservation cloud_sync_uuid.
    |
    */
    'push_url' => env('CLOUD_SYNC_PUSH_URL'),

    'push_token' => env('CLOUD_SYNC_PUSH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Reachability probe (optional)
    |--------------------------------------------------------------------------
    |
    | If set, a HEAD request is used for "cloud reachable" status in admin UI.
    |
    */
    'reachability_url' => env('CLOUD_SYNC_REACHABILITY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Automatic sync (no worker is bundled; flag is visibility only)
    |--------------------------------------------------------------------------
    */
    'auto_sync_enabled' => (bool) env('CLOUD_SYNC_AUTO_ENABLED', false),
];
