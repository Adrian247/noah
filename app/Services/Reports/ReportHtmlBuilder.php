<?php

namespace App\Services\Reports;

use App\Models\Routine;
use App\Models\RoutineExecution;

class ReportHtmlBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    public function build(Routine $routine, RoutineExecution $execution, array $components): string
    {
        $company = $routine->company;
        $asset = $routine->asset;
        $body = '';

        if ($components === []) {
            $components = [
                ['type' => 'title', 'text' => 'Reporte de mantenimiento'],
                ['type' => 'paragraph', 'field' => 'corrected_comments'],
            ];
        }

        foreach ($components as $component) {
            $body .= $this->renderComponent($component, $routine, $execution, $company?->name, $asset?->tag);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
h1 { font-size: 18pt; margin-bottom: 12px; }
.meta { color: #555; font-size: 9pt; margin-bottom: 16px; }
p { line-height: 1.5; }
</style>
</head>
<body>
<div class="meta">Noah · {$company?->name} · Rutina #{$routine->id}</div>
{$body}
</body>
</html>
HTML;
    }

  /**
     * @param  array<string, mixed>  $component
     */
    private function renderComponent(
        array $component,
        Routine $routine,
        RoutineExecution $execution,
        ?string $companyName,
        ?string $assetTag,
    ): string {
        return match ($component['type'] ?? '') {
            'title' => '<h1>'.e($component['text'] ?? 'Reporte').'</h1>',
            'paragraph' => '<p>'.e($this->fieldValue($component['field'] ?? '', $execution, $assetTag)).'</p>',
            default => '',
        };
    }

    private function fieldValue(string $field, RoutineExecution $execution, ?string $assetTag): string
    {
        return match ($field) {
            'corrected_comments' => $execution->corrected_comments ?? $execution->technician_comments ?? '',
            'technician_comments' => $execution->technician_comments ?? '',
            'asset_tag' => $assetTag ?? '',
            default => '',
        };
    }
}
