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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'webpush' => [
        'vapid' => [
            'public_key' => env('VAPID_PUBLIC_KEY'),
            'private_key' => env('VAPID_PRIVATE_KEY'),
        ],
    ],

    'google' => [
        // Legacy single-key support (kept for backward compatibility).
        'pagespeed_api_key' => env('GOOGLE_PAGESPEED_API_KEY'),

        // Multi-key rotation: comma-separated list of API keys.
        // Example: GOOGLE_PAGESPEED_API_KEYS="key1,key2,key3"
        // When set, keys are rotated round-robin; each key has a daily quota
        // counter capped at 400 calls (conservative margin below Google's 500/day limit).
        // If empty, falls back to GOOGLE_PAGESPEED_API_KEY (single key).
        // If both are empty, the API is called without a key (very low quota).
        'pagespeed_api_keys' => env('GOOGLE_PAGESPEED_API_KEYS'),
    ],

];
