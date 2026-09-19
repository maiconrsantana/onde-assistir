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

    'football_data' => [
        'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),
        'token' => env('FOOTBALL_DATA_API_TOKEN'),
        'competition' => env('FOOTBALL_DATA_COMPETITION', 'BSA'),
        'season' => env('FOOTBALL_DATA_SEASON', date('Y')),
        'timeout' => (int) env('FOOTBALL_DATA_TIMEOUT', 15),
        'retry_times' => (int) env('FOOTBALL_DATA_RETRY_TIMES', 2),
        'retry_sleep' => (int) env('FOOTBALL_DATA_RETRY_SLEEP', 500),
    ],

    'thesportsdb' => [
        'base_url' => env('THESPORTSDB_BASE_URL', 'https://www.thesportsdb.com/api/v1/json'),
        'key' => env('THESPORTSDB_API_KEY'),
        'timeout' => (int) env('THESPORTSDB_TIMEOUT', 15),
        'retry_times' => (int) env('THESPORTSDB_RETRY_TIMES', 2),
        'retry_sleep' => (int) env('THESPORTSDB_RETRY_SLEEP', 500),
        'country' => env('THESPORTSDB_COUNTRY', 'Brazil'),
        'league_id' => env('THESPORTSDB_LEAGUE_ID', '4351'),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'broadcast_search_enabled' => (bool) env('OPENAI_BROADCAST_SEARCH_ENABLED', true),
        'web_search_tool' => env('OPENAI_WEB_SEARCH_TOOL', 'web_search_preview'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'retry_times' => (int) env('OPENAI_RETRY_TIMES', 1),
        'retry_sleep' => (int) env('OPENAI_RETRY_SLEEP', 1000),
    ],

];
