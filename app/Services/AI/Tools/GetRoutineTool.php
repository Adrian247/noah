<?php

namespace App\Services\AI\Tools;

use App\Enums\ServiceCategory;
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
        return 'Obtiene el detalle de un servicio por ID (solo lectura): tipo, categoría '
            .'(instalación / fabricación / mantenimiento), estado, trazabilidad de workflow, '
            .'activo y/o cliente, y última ejecución.';
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
                    'description' => 'ID numérico del servicio',
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
            ->with(['asset', 'client', 'routineType', 'latestExecution', 'workflowInstance'])
            ->find($id);

        if ($routine === null) {
            return [
                'data' => ['error' => 'Servicio no encontrado en esta empresa.'],
                'sources' => [],
            ];
        }

        $execution = $routine->latestExecution;
        $category = $routine->routineType?->service_category;
        $categoryEnum = $category instanceof ServiceCategory
            ? $category
            : ServiceCategory::tryFrom((string) ($category ?? '')) ?? ServiceCategory::Maintenance;

        return [
            'data' => [
                'id' => $routine->id,
                'type' => $routine->routineType?->name,
                'service_category' => $categoryEnum->value,
                'service_category_label' => $categoryEnum->label(),
                'asset_tag' => $routine->asset?->tag,
                'client_id' => $routine->client_id,
                'client_name' => $routine->client?->trade_name
                    ?? $routine->client?->legal_name
                    ?? $routine->client?->code,
                'status' => $routine->status->value ?? (string) $routine->status,
                'workflow_step' => $routine->workflowInstance?->current_step_key,
                'workflow_status' => $routine->workflowInstance?->status,
                'scheduled_at' => $routine->scheduled_at?->toDateTimeString(),
                'updated_at' => $routine->updated_at?->toDateTimeString(),
                'is_demo' => (bool) $routine->is_demo,
                'technician_comments' => $execution?->technician_comments,
                'execution_status' => $execution?->status,
                'response_keys' => is_array($execution?->responses) ? array_keys($execution->responses) : [],
            ],
            'sources' => [[
                'type' => 'routine',
                'id' => $routine->id,
                'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Servicio'),
            ]],
        ];
    }
}
