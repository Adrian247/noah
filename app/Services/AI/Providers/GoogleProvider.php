<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiCompletionResult;
use App\Services\AI\Contracts\AiProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleProvider implements AiProviderContract
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function name(): string
    {
        return 'google';
    }

    public function supportsChat(): bool
    {
        return filled(config('phoenix.ai.google.api_key'));
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
        $model ??= config('phoenix.ai.google.model', 'gemini-2.0-flash');
        [$systemInstruction, $contents] = $this->convertMessages($messages);
        $payload = [
            'contents' => $contents,
            'generationConfig' => ['temperature' => $temperature],
        ];
        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = $systemInstruction;
        }
        $toolDeclarations = $this->convertTools($tools);
        if ($toolDeclarations !== null) {
            $payload['tools'] = $toolDeclarations;
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => (string) config('phoenix.ai.google.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->post(self::API_BASE.'/models/'.$model.':generateContent', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Google Gemini failed: '.$response->body());
        }

        $candidate = $response->json('candidates.0') ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $text = '';
        $toolCalls = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fn = $part['functionCall'];
                $toolCalls[] = [
                    'id' => 'call_'.Str::random(8),
                    'name' => (string) ($fn['name'] ?? ''),
                    'arguments' => is_array($fn['args'] ?? null) ? $fn['args'] : [],
                ];
            }
        }

        $usage = $response->json('usageMetadata') ?? [];

        return new AiCompletionResult(
            content: trim($text),
            finishReason: $candidate['finishReason'] ?? null,
            toolCalls: $toolCalls,
            inputTokens: $usage['promptTokenCount'] ?? null,
            outputTokens: $usage['candidatesTokenCount'] ?? null,
            model: $model,
            provider: $this->name(),
        );
    }

    public function visionExtractText(string $imageContents, string $prompt, ?string $model = null): AiCompletionResult
    {
        throw new \RuntimeException('Visión no implementada para Google en esta versión.');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array{0: ?array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function convertMessages(array $messages): array
    {
        $systemParts = [];
        $contents = [];
        $callNames = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            if ($role === 'system') {
                if (! empty($message['content'])) {
                    $systemParts[] = ['text' => (string) $message['content']];
                }

                continue;
            }
            if ($role === 'assistant') {
                $parts = [];
                if (! empty($message['content'])) {
                    $parts[] = ['text' => (string) $message['content']];
                }
                foreach ($message['tool_calls'] ?? [] as $call) {
                    $fn = $call['function'] ?? [];
                    $name = (string) ($fn['name'] ?? '');
                    $callId = (string) ($call['id'] ?? $name);
                    $callNames[$callId] = $name;
                    $args = json_decode((string) ($fn['arguments'] ?? '{}'), true);
                    $parts[] = ['functionCall' => ['name' => $name, 'args' => is_array($args) ? $args : []]];
                }
                if ($parts !== []) {
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                }

                continue;
            }
            if ($role === 'tool') {
                $callId = (string) ($message['tool_call_id'] ?? '');
                $name = $callNames[$callId] ?? $callId;
                $decoded = json_decode((string) ($message['content'] ?? '{}'), true);
                $response = is_array($decoded) ? $decoded : ['result' => $decoded];
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['functionResponse' => ['name' => $name, 'response' => $response]]],
                ];

                continue;
            }
            if (! empty($message['content'])) {
                $contents[] = ['role' => 'user', 'parts' => [['text' => (string) $message['content']]]];
            }
        }

        $systemInstruction = $systemParts !== [] ? ['parts' => $systemParts] : null;

        return [$systemInstruction, $contents];
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>|null
     */
    private function convertTools(array $tools): ?array
    {
        if ($tools === []) {
            return null;
        }

        $declarations = [];
        foreach ($tools as $tool) {
            $fn = $tool['function'] ?? [];
            if (empty($fn['name'])) {
                continue;
            }
            $declarations[] = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? '',
                'parameters' => $fn['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }

        return $declarations === [] ? null : [['functionDeclarations' => $declarations]];
    }
}
