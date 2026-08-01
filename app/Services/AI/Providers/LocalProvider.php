<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiCompletionResult;
use App\Services\AI\Contracts\AiProviderContract;

/**
 * Fallback sin LLM externo: no chat ni visión reales.
 */
class LocalProvider implements AiProviderContract
{
    public function name(): string
    {
        return 'local';
    }

    public function supportsChat(): bool
    {
        return false;
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function chat(
        array $messages,
        array $tools = [],
        ?string $model = null,
        float $temperature = 0.2,
    ): AiCompletionResult {
        return new AiCompletionResult(
            content: '',
            finishReason: 'local_unsupported',
            provider: $this->name(),
            model: $model,
        );
    }

    public function visionExtractText(string $imageContents, string $prompt, ?string $model = null): AiCompletionResult
    {
        return new AiCompletionResult(
            content: 'OCR no disponible en modo local (configure OpenAI para visión).',
            finishReason: 'local_unsupported',
            provider: $this->name(),
            model: $model,
        );
    }
}
