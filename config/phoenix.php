<?php

return [
    /** Contraseña de cuentas demo de tenant (seeder, ensure-demo). */
    'demo_password' => env('PHOENIX_DEMO_PASSWORD', 'phoenix.2026$'),

    /** Contraseña del administrador de plataforma (root). */
    'demo_root_password' => env('PHOENIX_DEMO_ROOT_PASSWORD', 'pyro.2026$'),

    /**
     * Correos con acceso de plataforma (plantilla global de roles, tenants).
     *
     * @var list<string>
     */
    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) env('PHOENIX_PLATFORM_ADMIN_EMAILS', 'admin@pyro-systems.com')),
    ))),

    'ai' => [
        'default_provider' => env('PHOENIX_AI_PROVIDER', 'local'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],
    'reports' => [
        'disk' => env('PHOENIX_REPORTS_DISK', 'local'),
        'path_prefix' => 'reports',
        'async' => filter_var(env('PHOENIX_REPORTS_ASYNC', true), FILTER_VALIDATE_BOOL),
    ],
    'evidence' => [
        'disk' => env('PHOENIX_EVIDENCE_DISK', 'evidence'),
        'path_prefix' => 'executions',
    ],
    'billing' => [
        'labor_rate_per_hour' => (float) env('PHOENIX_BILLING_LABOR_RATE', 0),
        'tax_rate' => (float) env('PHOENIX_BILLING_TAX_RATE', 0.16),
        'evidence_max_kb' => (int) env('PHOENIX_BILLING_EVIDENCE_MAX_KB', 10240),
        'evidence_disk' => env('PHOENIX_BILLING_EVIDENCE_DISK', 'local'),
        'fiscal' => [
            'default_provider' => env('PHOENIX_FISCAL_PROVIDER', 'sandbox'),
            'mexico_pac' => [
                'base_url' => env('PHOENIX_PAC_BASE_URL'),
                'api_key' => env('PHOENIX_PAC_API_KEY'),
            ],
        ],
    ],
];
