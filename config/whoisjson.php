<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Every whoisjson.com call authenticates with a single header, built as
    | `Authorization: TOKEN=<YOUR_API_KEY>`. The token type is configurable so
    | the header can be reshaped without touching the package.
    |
    | @see https://whoisjson.com/documentation#quick-start
    |
    */

    'api_base_url' => env('WHOISJSON_API_BASE_URL', 'https://whoisjson.com/api/v1'),

    'api_key' => env('WHOISJSON_API_KEY'),

    'api_token_type' => env('WHOISJSON_API_TOKEN_TYPE', 'TOKEN='),

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | Timeouts are in seconds. Retries cover connection failures plus the 429
    | and 5xx statuses; `times` is the total number of attempts, so 1 disables
    | retrying altogether. Sleep is in milliseconds.
    |
    */

    'timeout' => (int) env('WHOISJSON_TIMEOUT', 30),

    'connect_timeout' => (int) env('WHOISJSON_CONNECT_TIMEOUT', 10),

    'retry' => [
        'times' => (int) env('WHOISJSON_RETRY_TIMES', 2),
        'sleep' => (int) env('WHOISJSON_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Bypass
    |--------------------------------------------------------------------------
    |
    | Responses are cached upstream for three hours. Enabling this sends
    | `_forceRefresh=1` with every request, which returns fresh data at the
    | cost of 2x credits. Prefer the per-call `fresh()` helper over flipping
    | this on globally.
    |
    */

    'force_refresh' => (bool) env('WHOISJSON_FORCE_REFRESH', false),

];
