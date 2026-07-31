<?php

namespace App\Services\Insights;

use App\Models\AuditEntry;
use App\Models\Routine;
use Illuminate\Support\Str;

class InsightAssistantService
{
    /**
     * @return array{answer: string, sources: list<array{type: string, id: int, label: string}>}
     */
    public function answer(int $companyId, string $question): array
    {
        $normalized = Str::lower(Str::ascii(trim($question)));
        $sources = [];

        if (Str::contains($normalized, ['rutina', 'orden', 'servicio'])) {
            $routines = Routine::query()
                ->where('company_id', $companyId)
                ->with(['asset', 'routineType'])
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            foreach ($routines as $routine) {
                $sources[] = [
                    'type' => 'routine',
                    'id' => $routine->id,
                    'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Rutina'),
                ];
            }

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
            ];
        }

        if (Str::contains($normalized, ['auditor', 'historial', 'evento'])) {
            $entries = AuditEntry::query()
                ->where('company_id', $companyId)
                ->orderByDesc('occurred_at')
                ->limit(5)
                ->get();

            foreach ($entries as $entry) {
                $sources[] = [
                    'type' => 'audit',
                    'id' => $entry->id,
                    'label' => $entry->action.' · '.$entry->occurred_at?->toDateTimeString(),
                ];
            }

            $lines = $entries->map(fn (AuditEntry $e) => '- '.$e->action.' ('.$e->occurred_at?->diffForHumans().')');

            return [
                'answer' => "Últimos eventos de auditoría:\n".$lines->implode("\n"),
                'sources' => $sources,
            ];
        }

        return [
            'answer' => 'Puedo ayudarte con rutinas recientes o eventos de auditoría. Prueba: «¿Qué rutinas están activas?» o «Muéstrame el historial de auditoría».',
            'sources' => [],
        ];
    }
}
