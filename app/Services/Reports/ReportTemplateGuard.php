<?php

namespace App\Services\Reports;

use App\Models\ReportTemplate;
use App\Models\RoutineType;
use Illuminate\Validation\ValidationException;

class ReportTemplateGuard
{
    /**
     * @return list<string>
     */
    public function deleteBlockers(ReportTemplate $template): array
    {
        $versionIds = $template->versions()->pluck('id');

        if ($versionIds->isEmpty()) {
            return [];
        }

        $routineTypes = RoutineType::query()
            ->whereIn('report_template_version_id', $versionIds)
            ->orderBy('name')
            ->pluck('name');

        if ($routineTypes->isEmpty()) {
            return [];
        }

        $sample = $routineTypes->take(4)->implode(', ');
        $extra = $routineTypes->count() > 4 ? ' y otros' : '';

        return [
            "Hay tipos de rutina enlazados a este reporte ({$sample}{$extra}). Quita el reporte en Tipos de rutina antes de eliminar la plantilla.",
        ];
    }

    public function assertCanDelete(ReportTemplate $template): void
    {
        $reasons = $this->deleteBlockers($template);

        if ($reasons !== []) {
            throw ValidationException::withMessages([
                'report' => $reasons,
            ]);
        }
    }
}
