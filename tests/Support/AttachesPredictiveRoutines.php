<?php

namespace Tests\Support;

use App\Enums\RoutineStatus;
use App\Enums\ServiceLine;
use App\Models\Asset;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Adjunta rutinas validadas a activos predictivos (p. ej. tras ingerir una bitácora de referencia).
 * El motor de predicción solo evalúa flota con historial de rutinas aplicadas.
 */
trait AttachesPredictiveRoutines
{
    /**
     * @param  list<string>|null  $tags  null = todos los activos de la empresa con tag tipo SS-/JB-/VQ-
     */
    protected function attachValidatedRoutinesToAssets(
        int $companyId,
        ?array $tags = null,
        ?CarbonImmutable $asOf = null,
    ): int {
        $asOf ??= CarbonImmutable::parse('2020-09-09');

        $type = RoutineType::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('service_category', \App\Enums\ServiceCategory::Maintenance->value)
            ->first();

        if ($type === null) {
            $type = RoutineType::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        if ($type === null) {
            return 0;
        }

        $assignee = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))
            ->first();

        $query = Asset::withoutGlobalScope('company')->where('company_id', $companyId);
        if ($tags !== null) {
            $query->whereIn('tag', $tags);
        }

        $assets = $query->orderBy('tag')->limit(40)->get();
        $created = 0;

        foreach ($assets as $index => $asset) {
            $scheduled = $asOf->subDays(2 + ($index % 10));

            $exists = Routine::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('asset_id', $asset->id)
                ->where('status', RoutineStatus::Validated->value)
                ->exists();

            if ($exists) {
                continue;
            }

            $routine = Routine::withoutGlobalScope('company')->create([
                'company_id' => $companyId,
                'site_id' => $asset->site_id,
                'asset_id' => $asset->id,
                'routine_type_id' => $type->id,
                'assigned_to' => $assignee?->id,
                'status' => RoutineStatus::Validated,
                'scheduled_at' => $scheduled,
                'is_demo' => true,
            ]);

            $routine->executions()->create([
                'submitted_at' => $scheduled->addHours(2),
                'validated_at' => $scheduled->addHours(3),
                'duration_minutes' => 90 + ($index % 60),
                'status' => 'validated',
                'responses' => ['nota' => 'Rutina de referencia para pruebas predictivas'],
            ]);

            $created++;
        }

        return $created;
    }
}
