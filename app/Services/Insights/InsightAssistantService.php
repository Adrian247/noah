<?php

namespace App\Services\Insights;

use App\Models\AuditEntry;
use App\Models\Routine;
use App\Services\AI\AiGateway;
use Illuminate\Support\Str;

class InsightAssistantService
{
    public function __construct(
        private readonly OperationalContextRetriever $contextRetriever,
        private readonly AiGateway $aiGateway,
    ) {}

    /**
     * @return array{answer: string, sources: list<array{type: string, id: int, label: string}>, provider?: string}
     */
    public function answer(int $companyId, string $question, ?int $userId = null): array
    {
        $retrieved = $this->contextRetriever->retrieve($companyId, $question);
        $llmAnswer = $this->aiGateway->answerOperationalQuestion(
            $question,
            $retrieved['context'],
            $companyId,
            $userId,
        );

        if ($llmAnswer !== null) {
            return [
                'answer' => $llmAnswer,
                'sources' => $retrieved['sources'],
                'provider' => config('phoenix.ai.default_provider', 'local'),
            ];
        }

        return $this->answerWithRules($companyId, $question, $retrieved['sources']);
    }

    /**
     * @param  list<array{type: string, id: int, label: string}>  $sources
     * @return array{answer: string, sources: list<array{type: string, id: int, label: string}>, provider: string}
     */
    private function answerWithRules(int $companyId, string $question, array $sources): array
    {
        $normalized = Str::lower(Str::ascii(trim($question)));

        if (Str::contains($normalized, ['rutina', 'orden', 'servicio'])) {
            $routines = Routine::query()
                ->where('company_id', $companyId)
                ->with(['asset', 'routineType'])
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            $lines = $routines->map(fn (Routine $r) => sprintf(
                '- Rutina #%d (%s) en activo %s, estado %s.',
                $r->id,
                $r->routineType?->name ?? '—',
                $r->asset?->tag ?? '—',
                $r->status->value ?? (string) $r->status,
            ));

            return [
                'answer' => "Rutinas recientes de la empresa:\n".$lines->implode("\n"),
                'sources' => $sources,
                'provider' => 'rules',
            ];
        }

        if (Str::contains($normalized, ['auditor', 'historial', 'evento'])) {
            $entries = AuditEntry::query()
                ->where('company_id', $companyId)
                ->orderByDesc('occurred_at')
                ->limit(5)
                ->get();

            $lines = $entries->map(fn (AuditEntry $e) => '- '.$e->action.' ('.$e->occurred_at?->diffForHumans().')');

            return [
                'answer' => "Últimos eventos de auditoría:\n".$lines->implode("\n"),
                'sources' => $sources,
                'provider' => 'rules',
            ];
        }

        return [
            'answer' => 'Puedo ayudarte con rutinas recientes o eventos de auditoría. Prueba: «¿Qué rutinas están activas?» o «Muéstrame el historial de auditoría».',
            'sources' => $sources,
            'provider' => 'rules',
        ];
    }
}
