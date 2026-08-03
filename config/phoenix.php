<?php

return [
    /** Contraseña de cuentas demo de tenant (seeder, ensure-demo). */
    'demo_password' => env('PHOENIX_DEMO_PASSWORD', 'pyro.2026$'),

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
        'max_tool_rounds' => (int) env('PHOENIX_AI_MAX_TOOL_ROUNDS', 4),
        'google' => [
            'api_key' => env('GOOGLE_API_KEY', env('GEMINI_API_KEY')),
            'model' => env('GOOGLE_MODEL', env('GEMINI_MODEL', 'gemini-2.0-flash')),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],
    'predictive' => [
        /** Ventanas de predicción permitidas, en días. */
        'horizons' => [7, 14, 30],
        'default_horizon_days' => (int) env('PHOENIX_PREDICTIVE_HORIZON_DAYS', 14),
        /** Subproyecto ML (ml/phoenix-predict); si está apagado se usa el motor determinístico. */
        'ml' => [
            'enabled' => filter_var(env('PHOENIX_PREDICTIVE_ML_ENABLED', false), FILTER_VALIDATE_BOOL),
            'url' => env('PHOENIX_PREDICTIVE_ML_URL', 'http://ml:8000'),
            'timeout' => (int) env('PHOENIX_PREDICTIVE_ML_TIMEOUT', 8),
        ],
    ],
    'reports' => [
        'disk' => env('PHOENIX_REPORTS_DISK', 'local'),
        'path_prefix' => 'reports',
        'async' => filter_var(env('PHOENIX_REPORTS_ASYNC', true), FILTER_VALIDATE_BOOL),
        /** auto | browsershot | dompdf — auto usa Chromium si está instalado (ADR-005). */
        'pdf_driver' => env('PHOENIX_REPORTS_PDF_DRIVER', 'auto'),
        'pdf_fallback_dompdf' => filter_var(env('PHOENIX_REPORTS_PDF_FALLBACK_DOMPDF', true), FILTER_VALIDATE_BOOL),
        'chrome_path' => env('PHOENIX_REPORTS_CHROME_PATH', ''),
        'chrome_no_sandbox' => filter_var(env('PHOENIX_REPORTS_CHROME_NO_SANDBOX', true), FILTER_VALIDATE_BOOL),
        'node_binary' => env('PHOENIX_REPORTS_NODE_BINARY', ''),
        'npm_binary' => env('PHOENIX_REPORTS_NPM_BINARY', ''),
        'browsershot_timeout' => (int) env('PHOENIX_REPORTS_BROWSERSHOT_TIMEOUT', 60),
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
    'push' => [
        'enabled' => filter_var(env('PHOENIX_PUSH_ENABLED', true), FILTER_VALIDATE_BOOL),
        /** log | fcm */
        'driver' => env('PHOENIX_PUSH_DRIVER', 'log'),
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            /** Ruta absoluta al JSON de service account con rol Firebase Cloud Messaging Admin. */
            'credentials' => env('FCM_CREDENTIALS'),
        ],
    ],
];
