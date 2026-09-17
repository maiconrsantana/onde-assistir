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

    'api_football' => [
        'base_url' => env('API_FOOTBALL_BASE_URL', 'https://v3.football.api-sports.io'),
        'key' => env('API_FOOTBALL_KEY'),
        'brasileirao_league_id' => env('API_FOOTBALL_BRASILEIRAO_LEAGUE_ID'),
        'season' => env('API_FOOTBALL_SEASON', date('Y')),
        'timeout' => (int) env('API_FOOTBALL_TIMEOUT', 15),
        'retry_times' => (int) env('API_FOOTBALL_RETRY_TIMES', 2),
        'retry_sleep' => (int) env('API_FOOTBALL_RETRY_SLEEP', 500),
    ],

    'thesportsdb' => [
        'base_url' => env('THESPORTSDB_BASE_URL', 'https://www.thesportsdb.com/api/v1/json'),
        'key' => env('THESPORTSDB_API_KEY'),
        'timeout' => (int) env('THESPORTSDB_TIMEOUT', 15),
        'retry_times' => (int) env('THESPORTSDB_RETRY_TIMES', 2),
        'retry_sleep' => (int) env('THESPORTSDB_RETRY_SLEEP', 500),
        'country' => env('THESPORTSDB_COUNTRY', 'Brazil'),
    ],

];
