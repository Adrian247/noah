<?php

namespace App\Services\Insights;

use App\Models\Asset;
use App\Models\AuditEntry;
use App\Models\Routine;

class OperationalContextRetriever
{
    /**
     * @return array{context: string, sources: list<array{type: string, id: int, label: string}>}
     */
    public function retrieve(int $companyId, string $question, int $limit = 8): array
    {
        $sources = [];
        $blocks = [];

        $routines = Routine::query()
            ->where('company_id', $companyId)
            ->with(['asset', 'routineType'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($routines->isNotEmpty()) {
            $lines = $routines->map(function (Routine $routine) use (&$sources) {
                $sources[] = [
                    'type' => 'routine',
                    'id' => $routine->id,
                    'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Servicio'),
                ];

                return sprintf(
                    'Servicio #%d | tipo=%s | activo=%s | estado=%s | actualizada=%s',
                    $routine->id,
                    $routine->routineType?->name ?? '—',
                    $routine->asset?->tag ?? '—',
                    $routine->status->value ?? (string) $routine->status,
                    $routine->updated_at?->toDateTimeString() ?? '—',
                );
            });
            $blocks[] = "Servicios recientes:\n".$lines->implode("\n");
        }

        $audits = AuditEntry::query()
            ->where('company_id', $companyId)
            ->orderByDesc('occurred_at')
            ->limit(5)
            ->get();

        if ($audits->isNotEmpty()) {
            $lines = $audits->map(function (AuditEntry $entry) use (&$sources) {
                $sources[] = [
                    'type' => 'audit',
                    'id' => $entry->id,
                    'label' => $entry->action.' · '.$entry->occurred_at?->toDateTimeString(),
                ];

                return sprintf(
                    'Auditoría #%d | acción=%s | entidad=%s#%s | cuando=%s',
                    $entry->id,
                    $entry->action,
                    $entry->entity_type ?? '—',
                    $entry->entity_id ?? '—',
                    $entry->occurred_at?->toDateTimeString() ?? '—',
                );
            });
            $blocks[] = "Eventos de auditoría:\n".$lines->implode("\n");
        }

        $assets = Asset::query()
            ->where('company_id', $companyId)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        if ($assets->isNotEmpty()) {
            $lines = $assets->map(function (Asset $asset) use (&$sources) {
                $sources[] = [
                    'type' => 'asset',
                    'id' => $asset->id,
                    'label' => $asset->tag ?? 'Activo #'.$asset->id,
                ];

                return sprintf('Activo #%d | etiqueta=%s | sitio_id=%s', $asset->id, $asset->tag ?? '—', $asset->site_id ?? '—');
            });
            $blocks[] = "Activos recientes:\n".$lines->implode("\n");
        }

        $context = "Pregunta del usuario: {$question}\n\n".implode("\n\n", $blocks);

        return [
            'context' => $context,
            'sources' => $this->uniqueSources($sources),
        ];
    }

    /**
     * @param  list<array{type: string, id: int, label: string}>  $sources
     * @return list<array{type: string, id: int, label: string}>
     */
    private function uniqueSources(array $sources): array
    {
        $seen = [];
        $out = [];
        foreach ($sources as $source) {
            $key = $source['type'].':'.$source['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $source;
        }

        return array_slice($out, 0, 12);
    }
}
