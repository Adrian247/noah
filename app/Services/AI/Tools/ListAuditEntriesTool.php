<?php

namespace App\Services\AI\Tools;

use App\Models\AuditEntry;
use App\Services\AI\Contracts\AiTool;

class ListAuditEntriesTool implements AiTool
{
    public function name(): string
    {
        return 'list_audit_entries';
    }

    public function description(): string
    {
        return 'Lista eventos recientes de auditoría de la empresa (solo lectura).';
    }

    public function requiredPermissions(): array
    {
        return ['audit.view'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de eventos (1-20). Default 5.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 5)));
        $entries = AuditEntry::query()
            ->where('company_id', $companyId)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        $sources = [];
        $data = $entries->map(function (AuditEntry $entry) use (&$sources) {
            $sources[] = [
                'type' => 'audit',
                'id' => $entry->id,
                'label' => $entry->action.' · '.$entry->occurred_at?->toDateTimeString(),
            ];

            return [
                'id' => $entry->id,
                'action' => $entry->action,
                'entity_type' => $entry->entity_type,
                'entity_id' => $entry->entity_id,
                'occurred_at' => $entry->occurred_at?->toDateTimeString(),
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
