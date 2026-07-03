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
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_SECRET_ID'),        // Your Google Client ID
        'client_secret' => env('GOOGLE_CLIENT_SECRET_KEY'), // Your Google Client Secret
        'redirect' => env('GOOGLE_REDIRECT_URL'),      // Your Google Redirect URL
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

    'bunny' => [
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'storage_key' => env('BUNNY_STORAGE_KEY'),
        // Bunny storage API endpoint; region prefix if the zone isn't in Falkenstein (e.g. ny.storage.bunnycdn.com)
        'storage_host' => env('BUNNY_STORAGE_HOST', 'storage.bunnycdn.com'),
        'cdn_host' => env('BUNNY_CDN_HOST'),
    ],

];
