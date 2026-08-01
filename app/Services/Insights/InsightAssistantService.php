<?php

namespace App\Services\Insights;

use App\Services\AI\AiGateway;

class InsightAssistantService
{
    public function __construct(
        private readonly AiGateway $aiGateway,
    ) {}

    /**
     * @param  list<array{role: string, text: string}>  $history
     * @return array{
     *     answer: string,
     *     sources: list<array{type: string, id: int, label: string}>,
     *     provider?: string,
     *     tool_calls?: list<array{name: string, ok: bool}>,
     *     conversation_id?: ?string,
     *     presentation?: ?array{type: string, title: string, content: array<string, mixed>}
     * }
     */
    public function answer(
        int $companyId,
        string $question,
        ?int $userId = null,
        ?string $pageContext = null,
        ?string $conversationId = null,
        array $history = [],
    ): array {
        $user = auth()->user();
        abort_if($user === null, 401, 'Unauthenticated.');

        $result = $this->aiGateway->invokeAssistant(
            $question,
            $companyId,
            $user,
            $userId,
            $pageContext,
            $conversationId,
            $history,
        );

        if ($result !== null) {
            return $result;
        }

        return [
            'answer' => 'No fue posible consultar las herramientas internas. Reintenta en unos momentos.',
            'sources' => [],
            'provider' => 'none',
            'tool_calls' => [],
            'conversation_id' => $conversationId,
            'presentation' => null,
        ];
    }
}
