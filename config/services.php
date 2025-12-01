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

    'whatsapp' => [
        'base_url' => env('WHATSAPP_API_URL', 'https://api.green-api.com'),
        'instance_id' => env('WHATSAPP_API_INSTANCE'),
        'token' => env('WHATSAPP_API_TOKEN'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'firebase' => [
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-credentials.json')),
    ],

];
