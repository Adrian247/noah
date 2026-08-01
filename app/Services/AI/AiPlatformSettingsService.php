<?php

namespace App\Services\AI;

use App\Models\PlatformSetting;

class AiPlatformSettingsService
{
    public const KEY = 'ai_agent';

    /**
     * @return array{
     *     provider: string,
     *     openai_model: string,
     *     google_model: string,
     *     openai_use_default: bool,
     *     google_use_default: bool,
     *     default_openai_model: string,
     *     default_google_model: string,
     *     providers_available: array<string, array{configured: bool}>
     * }
     */
    public function get(): array
    {
        $stored = PlatformSetting::query()->where('key', self::KEY)->first();
        $value = is_array($stored?->value) ? $stored->value : [];

        $provider = (string) ($value['provider'] ?? config('phoenix.ai.default_provider', 'local'));
        if (! in_array($provider, ['google', 'openai', 'local'], true)) {
            $provider = 'local';
        }

        $defaultGoogle = (string) config('phoenix.ai.google.model', 'gemini-2.0-flash');
        $defaultOpenai = (string) config('phoenix.ai.openai.model', 'gpt-4o-mini');

        $googleStored = (string) ($value['google_model'] ?? AiProviderCatalogService::DEFAULT_MODEL);
        $openaiStored = (string) ($value['openai_model'] ?? AiProviderCatalogService::DEFAULT_MODEL);

        $googleUseDefault = $this->isDefaultModel($googleStored);
        $openaiUseDefault = $this->isDefaultModel($openaiStored);

        return [
            'provider' => $provider,
            'openai_model' => $openaiUseDefault ? $defaultOpenai : $openaiStored,
            'google_model' => $googleUseDefault ? $defaultGoogle : $googleStored,
            'openai_use_default' => $openaiUseDefault,
            'google_use_default' => $googleUseDefault,
            'default_openai_model' => $defaultOpenai,
            'default_google_model' => $defaultGoogle,
            'providers_available' => [
                'google' => [
                    'configured' => filled(config('phoenix.ai.google.api_key'))
                        || filled(config('ai.providers.gemini.key')),
                ],
                'openai' => ['configured' => filled(config('phoenix.ai.openai.api_key'))],
                'local' => ['configured' => true],
            ],
        ];
    }

    /**
     * Modelo efectivo para runtime (gateway / laravel-ai).
     */
    public function resolvedModel(string $provider): ?string
    {
        $settings = $this->get();

        return match ($provider) {
            'google' => $settings['google_model'],
            'openai' => $settings['openai_model'],
            default => null,
        };
    }

    /**
     * @param  array{
     *     provider: string,
     *     openai_model?: string|null,
     *     google_model?: string|null,
     *     openai_use_default?: bool,
     *     google_use_default?: bool
     * }  $payload
     */
    public function update(array $payload): array
    {
        $provider = $payload['provider'];
        if (! in_array($provider, ['google', 'openai', 'local'], true)) {
            throw new \InvalidArgumentException('Proveedor IA no soportado.');
        }

        if ($provider === 'google' && ! (filled(config('phoenix.ai.google.api_key')) || filled(config('ai.providers.gemini.key')))) {
            throw new \RuntimeException('Google Gemini no está configurado (GEMINI_API_KEY o GOOGLE_API_KEY).');
        }
        if ($provider === 'openai' && ! filled(config('phoenix.ai.openai.api_key'))) {
            throw new \RuntimeException('OpenAI no está configurado (OPENAI_API_KEY).');
        }

        $googleUseDefault = (bool) ($payload['google_use_default'] ?? false);
        $openaiUseDefault = (bool) ($payload['openai_use_default'] ?? false);

        $googleModel = $googleUseDefault
            ? AiProviderCatalogService::DEFAULT_MODEL
            : trim((string) ($payload['google_model'] ?? ''));
        $openaiModel = $openaiUseDefault
            ? AiProviderCatalogService::DEFAULT_MODEL
            : trim((string) ($payload['openai_model'] ?? ''));

        if ($provider === 'google' && ! $googleUseDefault && $googleModel === '') {
            throw new \InvalidArgumentException('Selecciona un modelo de Google o usa el predeterminado.');
        }
        if ($provider === 'openai' && ! $openaiUseDefault && $openaiModel === '') {
            throw new \InvalidArgumentException('Selecciona un modelo de OpenAI o usa el predeterminado.');
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => [
                    'provider' => $provider,
                    'openai_model' => $openaiModel !== '' ? $openaiModel : AiProviderCatalogService::DEFAULT_MODEL,
                    'google_model' => $googleModel !== '' ? $googleModel : AiProviderCatalogService::DEFAULT_MODEL,
                ],
            ],
        );

        return $this->get();
    }

    private function isDefaultModel(string $stored): bool
    {
        return $stored === '' || $stored === AiProviderCatalogService::DEFAULT_MODEL;
    }
}
