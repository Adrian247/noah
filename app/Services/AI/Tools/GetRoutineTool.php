<?php

namespace App\Services\AI\Tools;

use App\Models\Routine;
use App\Services\AI\Contracts\AiTool;

class GetRoutineTool implements AiTool
{
    public function name(): string
    {
        return 'get_routine';
    }

    public function description(): string
    {
        return 'Obtiene el detalle de una rutina por ID (solo lectura), incluyendo tipo, activo y última ejecución.';
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
                'routine_id' => [
                    'type' => 'integer',
                    'description' => 'ID numérico de la rutina',
                ],
            ],
            'required' => ['routine_id'],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $id = (int) ($arguments['routine_id'] ?? 0);
        $routine = Routine::query()
            ->where('company_id', $companyId)
            ->with(['asset', 'routineType', 'latestExecution'])
            ->find($id);

        if ($routine === null) {
            return [
                'data' => ['error' => 'Rutina no encontrada en esta empresa.'],
                'sources' => [],
            ];
        }

        $execution = $routine->latestExecution;

        return [
            'data' => [
                'id' => $routine->id,
                'type' => $routine->routineType?->name,
                'asset_tag' => $routine->asset?->tag,
                'status' => $routine->status->value ?? (string) $routine->status,
                'updated_at' => $routine->updated_at?->toDateTimeString(),
                'technician_comments' => $execution?->technician_comments,
                'response_keys' => is_array($execution?->responses) ? array_keys($execution->responses) : [],
            ],
            'sources' => [[
                'type' => 'routine',
                'id' => $routine->id,
                'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Rutina'),
            ]],
        ];
    }
}
