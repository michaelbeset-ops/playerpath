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

    // Betalingen. Zonder sleutel blijft de app op NotConnectedGateway staan
    // en wordt er nergens een betaling gestart; zie AppServiceProvider.
    'mollie' => [
        'key' => env('MOLLIE_KEY'),
    ],

    // Demo-betalingen: de hele betaalflow zonder dat er een cent beweegt.
    // Alleen voor demo's; op productie weigert playerpath:check het, en het
    // staat nooit aan naast een echte Mollie-sleutel. Zie DemoGateway.
    'payments' => [
        'demo' => (bool) env('PAYMENTS_DEMO', false),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
