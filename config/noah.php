<?php

return [
    /** Contraseña de las cuentas @noah.local en entornos demo (seeder, ensure-demo, login UI). */
    'demo_password' => env('NOAH_DEMO_PASSWORD', 'noah_application'),

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
    'evidence' => [
        'disk' => env('NOAH_EVIDENCE_DISK', 'evidence'),
        'path_prefix' => 'executions',
    ],
    'billing' => [
        'labor_rate_per_hour' => (float) env('NOAH_BILLING_LABOR_RATE', 0),
        'tax_rate' => (float) env('NOAH_BILLING_TAX_RATE', 0.16),
    ],
];
