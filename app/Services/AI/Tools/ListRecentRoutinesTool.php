<?php

namespace App\Services\AI\Tools;

use App\Enums\ServiceCategory;
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
        return 'Lista servicios recientes de la empresa (solo lectura), con categoría, estado, '
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
                    'description' => 'Máximo de servicios (1-15). Default 8.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filtro opcional por estado (p. ej. assigned, in_progress, validated).',
                ],
            ],
        ];
    }

    public function execute(array $arguments, int $companyId): array
    {
        $limit = max(1, min(15, (int) ($arguments['limit'] ?? 8)));
        $status = trim((string) ($arguments['status'] ?? ''));

        $builder = Routine::query()
            ->where('company_id', $companyId)
            ->with(['asset', 'client', 'routineType', 'workflowInstance'])
            ->orderByDesc('updated_at');

        if ($status !== '') {
            $builder->where('status', $status);
        }

        $routines = $builder->limit($limit)->get();

        $sources = [];
        $data = $routines->map(function (Routine $routine) use (&$sources) {
            $sources[] = [
                'type' => 'routine',
                'id' => $routine->id,
                'label' => '#'.$routine->id.' · '.($routine->routineType?->name ?? 'Servicio'),
            ];

            $category = $routine->routineType?->service_category;
            $categoryEnum = $category instanceof ServiceCategory
                ? $category
                : ServiceCategory::tryFrom((string) ($category ?? '')) ?? ServiceCategory::Maintenance;

            return [
                'id' => $routine->id,
                'type' => $routine->routineType?->name,
                'service_category' => $categoryEnum->value,
                'service_category_label' => $categoryEnum->label(),
                'asset_tag' => $routine->asset?->tag,
                'client_name' => $routine->client?->trade_name
                    ?? $routine->client?->legal_name
                    ?? $routine->client?->code,
                'status' => $routine->status->value ?? (string) $routine->status,
                'workflow_step' => $routine->workflowInstance?->current_step_key,
                'updated_at' => $routine->updated_at?->toDateTimeString(),
            ];
        })->all();

        return ['data' => $data, 'sources' => $sources];
    }
}
