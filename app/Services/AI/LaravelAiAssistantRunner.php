<?php

namespace App\Services\AI;

use App\Ai\Agents\OperationalAssistant;
use App\Ai\Support\ToolInvocationContext;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\AI\Tools\AiToolRegistry;
use Laravel\Ai\Enums\Lab;

/**
 * Orquesta el asistente operativo vía Laravel AI SDK (providers + tool loop + historial).
 */
class LaravelAiAssistantRunner
{
    public function __construct(
        private readonly AiToolRegistry $tools,
        private readonly AiToolAuthorizer $authorizer,
        private readonly AiPlatformSettingsService $platformSettings,
        private readonly AssistantDashboardBuilder $dashboardBuilder,
    ) {}

    public function isAvailable(): bool
    {
        $settings = $this->platformSettings->get();
        $provider = $settings['provider'];

        if ($provider === 'local') {
            return false;
        }

        return (bool) ($settings['providers_available'][$provider]['configured'] ?? false);
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return array{
     *     answer: string,
     *     sources: list<array{type: string, id: int, label: string}>,
     *     provider: string,
     *     tool_calls: list<array{name: string, ok: bool}>,
     *     input_tokens: ?int,
     *     output_tokens: ?int,
     *     model: ?string,
     *     conversation_id: ?string,
     *     presentation: ?array{type: string, title: string, content: array<string, mixed>}
     * }
     */
    public function run(
        string $question,
        int $companyId,
        User $user,
        ?string $systemOverride = null,
        ?string $conversationId = null,
        bool $preferDashboard = false,
    ): array {
        $allowed = $this->authorizer->allowedTools($user, $companyId, $this->tools);
        $context = new ToolInvocationContext;

        if ($allowed === []) {
            return [
                'answer' => 'No tienes permisos para consultar datos operativos con el asistente.',
                'sources' => [],
                'provider' => $this->lab()->value,
                'tool_calls' => [],
                'input_tokens' => null,
                'output_tokens' => null,
                'model' => null,
                'conversation_id' => $conversationId,
                'presentation' => null,
            ];
        }

        $template = PromptTemplate::activeFor('insights_assistant_v1', $companyId);
        $system = $systemOverride
            ?? $template?->system_prompt
            ?? '';

        $settings = $this->platformSettings->get();
        $model = match ($settings['provider']) {
            'google' => $settings['google_model'],
            'openai' => $settings['openai_model'],
            default => null,
        };

        $agent = new OperationalAssistant(
            user: $user,
            companyId: $companyId,
            authorizer: $this->authorizer,
            context: $context,
            allowedDomainTools: $allowed,
            systemInstructions: $system,
        );

        if (is_string($conversationId) && $conversationId !== '') {
            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        $response = $agent->prompt(
            $question,
            provider: $this->lab(),
            model: $model,
            timeout: 60,
        );

        $toolTrace = $context->toolCalls();
        if ($toolTrace === [] && $response->toolCalls->isNotEmpty()) {
            $toolTrace = $response->toolCalls
                ->map(fn ($call) => ['name' => $call->name, 'ok' => true])
                ->values()
                ->all();
        }

        $answer = trim($response->text);
        if ($answer === '') {
            $answer = 'No hay datos suficientes en las herramientas para responder.';
        }

        $presentation = null;
        if ($preferDashboard || $this->dashboardBuilder->wantsDashboard($question)) {
            $presentation = $this->buildPresentationFromContext($context, $companyId, $user);
        }

        return [
            'answer' => $answer,
            'sources' => $context->sources(),
            'provider' => $this->lab()->value,
            'tool_calls' => $toolTrace,
            'input_tokens' => $response->usage->promptTokens ?: null,
            'output_tokens' => $response->usage->completionTokens ?: null,
            'model' => $model,
            'conversation_id' => $response->conversationId ?? $agent->currentConversation(),
            'presentation' => $presentation,
        ];
    }

    private function buildPresentationFromContext(ToolInvocationContext $context, int $companyId, User $user): ?array
    {
        try {
            $tool = $this->tools->get('get_operational_kpis');
            if (! $this->authorizer->canUseTool($user, $companyId, $tool)) {
                return null;
            }
            $payload = $tool->execute([], $companyId);
            $context->addSources($payload['sources'] ?? []);
            $context->recordTool('get_operational_kpis', true);

            return $this->dashboardBuilder->fromOperationalKpis($payload['data'] ?? []);
        } catch (\Throwable) {
            return null;
        }
    }

    private function lab(): Lab
    {
        $provider = $this->platformSettings->get()['provider'];

        return match ($provider) {
            'google' => Lab::Gemini,
            'openai' => Lab::OpenAI,
            default => Lab::OpenAI,
        };
    }
}
