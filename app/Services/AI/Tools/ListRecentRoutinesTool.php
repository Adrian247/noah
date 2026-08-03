<?php

namespace App\Services\AI\Tools;

use App\Enums\ServiceLine;
use App\Models\Routine;
use App\Services\AI\Contracts\AiTool;

class ListRecentRoutinesTool implements AiTool
{
    public function name(): string
    {
        return 'list_recent_routines';
    }

    public function description(): string
    {
        return 'Lista rutinas recientes de la empresa (solo lectura), con línea de servicio, '
            .'activo y/o cliente. Úsala para preguntas sobre órdenes de servicio o estados.';
    }

    public function requiredPermissions(): array
    {
        return ['routines.execute', 'routines.assign', 'routines.validate', 'costs.view'];
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Máximo de rutinas (1-15). Default 8.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(15, (int) ($arguments['limit'] ?? 8)));
        $routines = Routine::query()
            ->where('company_id', $companyId)
            ->with(['asset', 'client', 'routineType'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $sources = [];
        $data = $routines->map(function (Routine $routine) use (&$sources) {
            $sources[] = [
                'type' => 'routine',
                'id' => $routine->id,
                'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Rutina'),
            ];

            $line = $routine->routineType?->service_line;
            $lineEnum = $line instanceof ServiceLine
                ? $line
                : ServiceLine::tryFrom((string) ($line ?? '')) ?? ServiceLine::Maintenance;

            return [
                'id' => $routine->id,
                'type' => $routine->routineType?->name,
                'service_line' => $lineEnum->value,
                'service_line_label' => $lineEnum->label(),
                'asset_tag' => $routine->asset?->tag,
                'client_name' => $routine->client?->trade_name
                    ?? $routine->client?->legal_name
                    ?? $routine->client?->code,
                'status' => $routine->status->value ?? (string) $routine->status,
                'updated_at' => $routine->updated_at?->toDateTimeString(),
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
