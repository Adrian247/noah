<?php

namespace App\Services\AI\Tools;

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
        return 'Lista rutinas recientes de la empresa (solo lectura). Úsala para preguntas sobre órdenes de servicio o estados.';
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
            ->with(['asset', 'routineType'])
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

            return [
                'id' => $routine->id,
                'type' => $routine->routineType?->name,
                'asset_tag' => $routine->asset?->tag,
                'status' => $routine->status->value ?? (string) $routine->status,
                'updated_at' => $routine->updated_at?->toDateTimeString(),
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
