<?php

namespace App\Services\AI;

use App\Models\AiInvocation;
use App\Models\PromptTemplate;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiGateway
{
    public function __construct(
        private GrammarCorrectionService $localGrammar,
    ) {}

    public function correctGrammar(string $text, ?int $userId = null): string
    {
        $companyId = app(CurrentCompany::class)->id();
        $template = PromptTemplate::activeFor('grammar_correction_v1', $companyId);
        $provider = $template?->provider ?? config('phoenix.ai.default_provider', 'local');

        if ($provider === 'openai' && config('phoenix.ai.openai.api_key')) {
            return $this->invokeOpenAi($text, $template, $companyId, $userId);
        }

        $output = $this->localGrammar->correct($text);
        $this->logInvocation($companyId, $userId, 'grammar_correction', 'local', null, $text, $output, 'success');

        return $output;
    }

    private function invokeOpenAi(string $text, ?PromptTemplate $template, ?int $companyId, ?int $userId): string
    {
        $system = $template?->system_prompt ?? 'Corrige gramática sin agregar datos.';
        $user = str_replace('{{technician_text}}', $text, $template?->user_template ?? '{{technician_text}}');
        $model = $template?->model ?? config('phoenix.ai.openai.model');

        try {
            $response = Http::withToken(config('phoenix.ai.openai.api_key'))
                ->timeout(30)
                ->post(rtrim(config('phoenix.ai.openai.base_url'), '/').'/chat/completions', [
                    'model' => $model,
                    'temperature' => (float) ($template?->temperature ?? 0.2),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException($response->body());
            }

            $output = trim($response->json('choices.0.message.content', ''));
            if ($output === '') {
                throw new \RuntimeException('Empty AI response');
            }

            $this->logInvocation(
                $companyId,
                $userId,
                'grammar_correction',
                'openai',
                $model,
                $text,
                $output,
                'success',
                $response->json('usage.prompt_tokens'),
                $response->json('usage.completion_tokens'),
            );

            return $output;
        } catch (\Throwable $e) {
            $this->logInvocation($companyId, $userId, 'grammar_correction', 'openai', $model, $text, null, 'failed');
            report($e);

            return $this->localGrammar->correct($text);
        }
    }

    private function logInvocation(
        ?int $companyId,
        ?int $userId,
        string $useCase,
        string $provider,
        ?string $model,
        string $input,
        ?string $output,
        string $status,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): void {
        if ($companyId === null) {
            return;
        }

        AiInvocation::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'use_case' => $useCase,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_excerpt' => Str::limit($input, 500),
            'output_excerpt' => $output !== null ? Str::limit($output, 500) : null,
            'status' => $status,
        ]);
    }
}
