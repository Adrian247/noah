<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Valida credenciales de proveedor y lista modelos disponibles.
 */
class AiProviderCatalogService
{
    public const DEFAULT_MODEL = '__default__';

    /**
     * @return array{
     *     ok: bool,
     *     provider: string,
     *     configured: bool,
     *     message: string,
     *     default_model: string,
     *     models: list<array{id: string, label: string}>
     * }
     */
    public function listModels(string $provider): array
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'local' => $this->localCatalog(),
            'google' => $this->googleCatalog(),
            'openai' => $this->openaiCatalog(),
            default => [
                'ok' => false,
                'provider' => $provider,
                'configured' => false,
                'message' => 'Proveedor no soportado.',
                'default_model' => '',
                'models' => [],
            ],
        };
    }

    /**
     * @return array{ok: bool, provider: string, configured: bool, message: string}
     */
    public function validate(string $provider): array
    {
        $catalog = $this->listModels($provider);

        return [
            'ok' => $catalog['ok'],
            'provider' => $catalog['provider'],
            'configured' => $catalog['configured'],
            'message' => $catalog['message'],
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     provider: string,
     *     configured: bool,
     *     message: string,
     *     default_model: string,
     *     models: list<array{id: string, label: string}>
     * }
     */
    private function localCatalog(): array
    {
        return [
            'ok' => true,
            'provider' => 'local',
            'configured' => true,
            'message' => 'Modo local listo: tools verificadas sin LLM externo.',
            'default_model' => 'local',
            'models' => [
                ['id' => 'local', 'label' => 'Local (heurística + tools)'],
            ],
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     provider: string,
     *     configured: bool,
     *     message: string,
     *     default_model: string,
     *     models: list<array{id: string, label: string}>
     * }
     */
    private function googleCatalog(): array
    {
        $key = (string) (config('phoenix.ai.google.api_key') ?: config('ai.providers.gemini.key'));
        $default = (string) config('phoenix.ai.google.model', 'gemini-2.0-flash');

        if ($key === '') {
            return [
                'ok' => false,
                'provider' => 'google',
                'configured' => false,
                'message' => 'Google Gemini no está configurado (GEMINI_API_KEY o GOOGLE_API_KEY).',
                'default_model' => $default,
                'models' => [],
            ];
        }

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $key,
                    'pageSize' => 100,
                ]);

            if (! $response->successful()) {
                $message = data_get($response->json(), 'error.message')
                    ?? ('No se pudo validar Gemini (HTTP '.$response->status().').');

                return [
                    'ok' => false,
                    'provider' => 'google',
                    'configured' => true,
                    'message' => (string) $message,
                    'default_model' => $default,
                    'models' => [],
                ];
            }

            $models = collect($response->json('models') ?? [])
                ->filter(function (array $model): bool {
                    $methods = $model['supportedGenerationMethods'] ?? [];
                    if (! in_array('generateContent', $methods, true)) {
                        return false;
                    }
                    $id = Str::after((string) ($model['name'] ?? ''), 'models/');
                    $haystack = Str::lower($id);

                    return ! Str::contains($haystack, ['tts', '-image', 'imagen', 'robotics', 'computer-use']);
                })
                ->map(function (array $model): array {
                    $id = Str::after((string) ($model['name'] ?? ''), 'models/');
                    if ($id === '' || $id === (string) ($model['name'] ?? '')) {
                        $id = (string) ($model['name'] ?? '');
                    }
                    $display = (string) ($model['displayName'] ?? $id);

                    return [
                        'id' => $id,
                        'label' => $display !== $id ? "{$display} ({$id})" : $id,
                    ];
                })
                ->filter(fn (array $row) => $row['id'] !== '')
                ->unique('id')
                ->sortBy(function (array $row) use ($default): string {
                    if ($row['id'] === $default) {
                        return '0-'.$row['id'];
                    }
                    if (str_starts_with($row['id'], 'gemini-')) {
                        return '1-'.$row['id'];
                    }

                    return '2-'.$row['id'];
                })
                ->values()
                ->all();

            if ($models === []) {
                return [
                    'ok' => false,
                    'provider' => 'google',
                    'configured' => true,
                    'message' => 'La API key es válida pero no devolvió modelos con generateContent.',
                    'default_model' => $default,
                    'models' => [],
                ];
            }

            return [
                'ok' => true,
                'provider' => 'google',
                'configured' => true,
                'message' => 'Google Gemini validado. Selecciona un modelo o deja el predeterminado.',
                'default_model' => $default,
                'models' => $models,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'provider' => 'google',
                'configured' => true,
                'message' => 'Error al consultar modelos de Gemini: '.$e->getMessage(),
                'default_model' => $default,
                'models' => [],
            ];
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     provider: string,
     *     configured: bool,
     *     message: string,
     *     default_model: string,
     *     models: list<array{id: string, label: string}>
     * }
     */
    private function openaiCatalog(): array
    {
        $key = (string) config('phoenix.ai.openai.api_key');
        $default = (string) config('phoenix.ai.openai.model', 'gpt-4o-mini');
        $base = rtrim((string) config('phoenix.ai.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($key === '') {
            return [
                'ok' => false,
                'provider' => 'openai',
                'configured' => false,
                'message' => 'OpenAI no está configurado (OPENAI_API_KEY).',
                'default_model' => $default,
                'models' => [],
            ];
        }

        try {
            $response = Http::timeout(25)
                ->withToken($key)
                ->acceptJson()
                ->get($base.'/models');

            if (! $response->successful()) {
                $message = data_get($response->json(), 'error.message')
                    ?? ('No se pudo validar OpenAI (HTTP '.$response->status().').');

                return [
                    'ok' => false,
                    'provider' => 'openai',
                    'configured' => true,
                    'message' => (string) $message,
                    'default_model' => $default,
                    'models' => [],
                ];
            }

            $models = collect($response->json('data') ?? [])
                ->map(fn (array $model) => (string) ($model['id'] ?? ''))
                ->filter(fn (string $id) => $id !== '' && (
                    str_starts_with($id, 'gpt-')
                    || str_starts_with($id, 'o1')
                    || str_starts_with($id, 'o3')
                    || str_starts_with($id, 'o4')
                ))
                ->unique()
                ->sort()
                ->map(fn (string $id) => ['id' => $id, 'label' => $id])
                ->values()
                ->all();

            if ($models === []) {
                return [
                    'ok' => false,
                    'provider' => 'openai',
                    'configured' => true,
                    'message' => 'La API key es válida pero no hay modelos chat disponibles.',
                    'default_model' => $default,
                    'models' => [],
                ];
            }

            return [
                'ok' => true,
                'provider' => 'openai',
                'configured' => true,
                'message' => 'OpenAI validado. Selecciona un modelo o deja el predeterminado.',
                'default_model' => $default,
                'models' => $models,
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'provider' => 'openai',
                'configured' => true,
                'message' => 'Error al consultar modelos de OpenAI: '.$e->getMessage(),
                'default_model' => $default,
                'models' => [],
            ];
        }
    }
}
