<?php

namespace App\Services\AI\Contracts;

interface AiProviderContract
{
    public function name(): string;

    public function supportsChat(): bool;

    public function supportsVision(): bool;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools  OpenAI-style tool schemas
     */
    public function chat(
        array $messages,
        array $tools = [],
        ?string $model = null,
        float $temperature = 0.2,
    ): AiCompletionResult;

    public function visionExtractText(string $imageContents, string $prompt, ?string $model = null): AiCompletionResult;
}
