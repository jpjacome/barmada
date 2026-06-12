<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Push notification driver
    |--------------------------------------------------------------------------
    |
    | How staff-app push notifications leave this server:
    |
    |   "none"  — (default) no pushes are sent; the mobile app falls back
    |             to foreground polling. Zero configuration, zero egress.
    |   "fcm"   — direct Firebase Cloud Messaging HTTP v1 using YOUR OWN
    |             Firebase service account (self-hosters who build their
    |             own app or run their own Firebase project).
    |   "relay" — the hosted Barmada Push Relay (payload-light: device
    |             tokens and an event name, never order data). For
    |             self-hosted servers using the official store app.
    |
    */

    'driver' => env('PUSH_DRIVER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Dispatch mode
    |--------------------------------------------------------------------------
    |
    | "sync" sends pushes inline in the request (simple, no worker needed —
    | fine for a venue with a handful of staff devices). "queue" dispatches
    | to the configured queue connection; run `php artisan queue:work`.
    |
    */

    'dispatch' => env('PUSH_DISPATCH', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Whether pushes carry human-readable content
    |--------------------------------------------------------------------------
    |
    | When true, notifications include a title ("New order — Table 4") so
    | the OS can display them with no app code running. When false, pushes
    | are pure "wake and sync" data messages. The relay driver ignores
    | this and is ALWAYS payload-light by design.
    |
    */

    'include_content' => env('PUSH_INCLUDE_CONTENT', true),

    'fcm' => [
        // Your Firebase project id (Project settings → General).
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute path to a service-account JSON with the
        // "Firebase Cloud Messaging API" enabled.
        'credentials' => env('FCM_CREDENTIALS_PATH'),
    ],

    'relay' => [
        'url' => env('PUSH_RELAY_URL'),
        'key' => env('PUSH_RELAY_KEY'),
    ],

];
