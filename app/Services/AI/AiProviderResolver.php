<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderContract;
use App\Services\AI\Providers\GoogleProvider;
use App\Services\AI\Providers\LocalProvider;
use App\Services\AI\Providers\OpenAiProvider;

class AiProviderResolver
{
    public function __construct(
        private readonly AiPlatformSettingsService $platformSettings,
    ) {}

    public function resolve(): AiProviderContract
    {
        $settings = $this->platformSettings->get();
        $provider = $settings['provider'];

        if ($provider === 'google' && (filled(config('phoenix.ai.google.api_key')) || filled(config('ai.providers.gemini.key')))) {
            return new GoogleProvider;
        }

        if ($provider === 'openai' && filled(config('phoenix.ai.openai.api_key'))) {
            return new OpenAiProvider;
        }

        return new LocalProvider;
    }

    /**
     * @return array{provider: string, model: ?string}
     */
    public function activeConfig(): array
    {
        $settings = $this->platformSettings->get();
        $provider = $this->resolve();

        $model = match ($settings['provider']) {
            'google' => $settings['google_model'],
            'openai' => $settings['openai_model'],
            default => null,
        };

        return [
            'provider' => $provider->name(),
            'model' => $model,
        ];
    }
}
