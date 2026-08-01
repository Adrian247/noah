<?php

namespace App\Services\AI\Contracts;

class AiCompletionResult
{
    /**
     * @param  list<array{id?: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly ?string $finishReason = null,
        public readonly array $toolCalls = [],
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?string $model = null,
        public readonly string $provider = 'unknown',
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
