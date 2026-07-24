<?php

return [
    'ai' => [
        'default_provider' => env('NOAH_AI_PROVIDER', 'local'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],
    'reports' => [
        'disk' => env('NOAH_REPORTS_DISK', 'local'),
        'path_prefix' => 'reports',
        'async' => filter_var(env('NOAH_REPORTS_ASYNC', true), FILTER_VALIDATE_BOOL),
    ],
];
