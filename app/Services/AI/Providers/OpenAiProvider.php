<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiCompletionResult;
use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderContract
{
    public function name(): string
    {
        return 'openai';
    }

    public function supportsChat(): bool
    {
        return filled(config('phoenix.ai.openai.api_key'));
    }

    public function supportsVision(): bool
    {
        return $this->supportsChat();
    }

    public function chat(
        array $messages,
        array $tools = [],
        ?string $model = null,
        float $temperature = 0.2,
    ): AiCompletionResult {
        $payload = [
            'model' => $model ?? config('phoenix.ai.openai.model', 'gpt-4o-mini'),
            'temperature' => $temperature,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken(config('phoenix.ai.openai.api_key'))
            ->timeout(45)
            ->post(rtrim((string) config('phoenix.ai.openai.base_url'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI chat failed: '.$response->body());
        }

        $message = $response->json('choices.0.message') ?? [];
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $argsRaw = $call['function']['arguments'] ?? '{}';
            $decoded = json_decode(is_string($argsRaw) ? $argsRaw : '{}', true);
            $toolCalls[] = [
                'id' => (string) ($call['id'] ?? ''),
                'name' => (string) ($call['function']['name'] ?? ''),
                'arguments' => is_array($decoded) ? $decoded : [],
            ];
        }

        return new AiCompletionResult(
            content: trim((string) ($message['content'] ?? '')),
            finishReason: $response->json('choices.0.finish_reason'),
            toolCalls: $toolCalls,
            inputTokens: $response->json('usage.prompt_tokens'),
            outputTokens: $response->json('usage.completion_tokens'),
            model: $response->json('model'),
            provider: $this->name(),
        );
    }

    public function visionExtractText(string $imageContents, string $prompt, ?string $model = null): AiCompletionResult
    {
        $base64 = base64_encode($imageContents);
        $model ??= config('phoenix.ai.openai.vision_model', 'gpt-4o-mini');

        $response = Http::withToken(config('phoenix.ai.openai.api_key'))
            ->timeout(45)
            ->post(rtrim((string) config('phoenix.ai.openai.base_url'), '/').'/chat/completions', [
                'model' => $model,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$base64]],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI vision failed: '.$response->body());
        }

        return new AiCompletionResult(
            content: trim((string) $response->json('choices.0.message.content', '')),
            finishReason: $response->json('choices.0.finish_reason'),
            toolCalls: [],
            inputTokens: $response->json('usage.prompt_tokens'),
            outputTokens: $response->json('usage.completion_tokens'),
            model: $response->json('model'),
            provider: $this->name(),
        );
    }
}
