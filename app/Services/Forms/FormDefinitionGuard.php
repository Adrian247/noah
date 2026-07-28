<?php

namespace App\Services\Forms;

use App\Enums\FormUsage;
use App\Models\EquipmentType;
use App\Models\FormDefinition;
use App\Models\RoutineExecution;
use App\Models\RoutineType;
use App\Models\SupplyType;
use Illuminate\Validation\ValidationException;

class FormDefinitionGuard
{
    public function assertUsageForCompany(?int $formDefinitionId, FormUsage $expectedUsage, int $companyId): void
    {
        if ($formDefinitionId === null) {
            return;
        }

        $form = FormDefinition::query()
            ->whereKey($formDefinitionId)
            ->where('company_id', $companyId)
            ->first();

        if ($form === null) {
            throw ValidationException::withMessages([
                'default_form_definition_id' => ['El formulario no existe en esta empresa.'],
            ]);
        }

        if ($form->usage !== $expectedUsage) {
            throw ValidationException::withMessages([
                'default_form_definition_id' => [
                    "El formulario debe ser de uso «{$expectedUsage->label()}».",
                ],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function deleteBlockers(FormDefinition $form): array
    {
        $reasons = [];
        $versionIds = $form->versions()->pluck('id');

        if (EquipmentType::query()->where('default_form_definition_id', $form->id)->exists()) {
            $reasons[] = 'Hay tipos de equipo que usan este formulario como ficha.';
        }

        if (SupplyType::query()->where('default_form_definition_id', $form->id)->exists()) {
            $reasons[] = 'Hay tipos de insumo que usan este formulario como ficha.';
        }

        if ($versionIds->isNotEmpty() && RoutineType::query()->whereIn('form_version_id', $versionIds)->exists()) {
            $reasons[] = 'Hay tipos de rutina enlazados a una versión de este formulario.';
        }

        if ($versionIds->isNotEmpty()) {
            $hasExecutions = RoutineExecution::query()
                ->whereHas('routine.routineType', fn ($q) => $q->whereIn('form_version_id', $versionIds))
                ->exists();

            if ($hasExecutions) {
                $reasons[] = 'Existen ejecuciones de rutina con respuestas asociadas a este formulario.';
            }
        }

        return $reasons;
    }

    public function assertCanDelete(FormDefinition $form): void
    {
        $reasons = $this->deleteBlockers($form);

        if ($reasons !== []) {
            throw ValidationException::withMessages([
                'form' => $reasons,
            ]);
        }
    }
}
