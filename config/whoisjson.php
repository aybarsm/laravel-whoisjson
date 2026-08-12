<?php

declare(strict_types=1);

return [
    'api_base_url' => env('WHOISJSON_API_BASE_URL', 'https://whoisjson.com/api/v1'),
    'api_key' => env('WHOISJSON_API_KEY'),
    'api_token_type' => env('WHOISJSON_API_TOKEN_TYPE', 'TOKEN='),
];
