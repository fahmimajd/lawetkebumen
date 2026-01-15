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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wa_gateway' => [
        'webhook_secret' => env('WEBHOOK_SECRET'),
        'send_url' => env('WA_GATEWAY_URL', 'http://node:3001/send'),
        'revoke_url' => env('WA_GATEWAY_REVOKE_URL'),
        'token' => env('WA_GATEWAY_TOKEN'),
        'timeout' => env('WA_GATEWAY_TIMEOUT', 5),
        'base_url' => env('WA_GATEWAY_BASE_URL'),
        'media_base_url' => env('MEDIA_BASE_URL', env('APP_URL')),
        'webhook_max_bytes' => env('WEBHOOK_MAX_BYTES', 0),
        'webhook_rate_limit' => env('WEBHOOK_RATE_LIMIT', 120),
    ],

];
