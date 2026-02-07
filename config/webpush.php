<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Authentication
    |--------------------------------------------------------------------------
    |
    | You'll need to create a public and private key for your application.
    | These keys must be safely stored and should not change.
    |
    */

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:your-email@example.com'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'pem_file' => env('VAPID_PEM_FILE', storage_path('app/webpush/private_key.pem')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Deprecated and optional. It's here only for compatibility reasons.
    |
    */

    'gcm' => [
        'key' => env('GCM_KEY'),
        'sender_id' => env('GCM_SENDER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | App name
    |--------------------------------------------------------------------------
    */
    'app_name' => env('APP_NAME', 'Hotel Booking System'),
];
