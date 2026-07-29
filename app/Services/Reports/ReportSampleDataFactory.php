<?php

namespace App\Services\Reports;

use App\Models\FormVersion;
use App\Models\RoutineType;

class ReportSampleDataFactory
{
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public function buildForPreview(array $components, ?int $reportTemplateId = null): array
    {
        $samples = [
            'corrected_comments' => 'Comentario de ejemplo para vista previa.',
            'technician_comments' => 'Texto técnico de muestra.',
            'asset_tag' => 'DEMO-001',
        ];

        $formVersion = $this->resolveFormVersion($reportTemplateId);
        if ($formVersion !== null) {
            foreach ($formVersion->schema['sections'] ?? [] as $section) {
                foreach ($section['fields'] ?? [] as $field) {
                    $key = $field['key'] ?? null;
                    if ($key === null || $key === '') {
                        continue;
                    }
                    $samples[$key] = $this->sampleForField($field);
                }
            }
        }

        foreach ($components as $component) {
            $field = $component['field'] ?? null;
            if ($field !== null && $field !== '' && ! array_key_exists($field, $samples)) {
                $samples[$field] = match ($component['type'] ?? '') {
                    'image' => null,
                    default => 'Valor de ejemplo ('.$field.')',
                };
            }
        }

        return $samples;
    }

    public function resolveFormVersionForTemplate(?int $reportTemplateId): ?FormVersion
    {
        return $this->resolveFormVersion($reportTemplateId);
    }

    private function resolveFormVersion(?int $reportTemplateId): ?FormVersion
    {
        if ($reportTemplateId === null) {
            return null;
        }

        $versionIds = \App\Models\ReportTemplateVersion::query()
            ->where('report_template_id', $reportTemplateId)
            ->pluck('id');

        $routineType = RoutineType::query()
            ->whereIn('report_template_version_id', $versionIds)
            ->with('formVersion')
            ->first();

        if ($routineType?->formVersion !== null) {
            return $routineType->formVersion;
        }

        $template = \App\Models\ReportTemplate::query()->find($reportTemplateId);

        return RoutineType::query()
            ->when($template !== null, fn ($q) => $q->where('company_id', $template->company_id))
            ->whereNotNull('form_version_id')
            ->with('formVersion')
            ->first()
            ?->formVersion;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function sampleForField(array $field): mixed
    {
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? $field['key'];

        return match ($type) {
            'number' => 42,
            'select', 'options' => $this->firstCatalogValue($field),
            'photo' => $this->samplePhoto($field),
            'textarea' => 'Texto largo de ejemplo para «'.$label.'».',
            default => 'Ejemplo: '.$label,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function firstCatalogValue(array $field): string
    {
        $catalogId = $field['option_catalog_id'] ?? null;
        if ($catalogId === null) {
            return 'opcion-demo';
        }

        $catalog = \App\Models\FormOptionCatalog::query()->find($catalogId);
        $first = $catalog?->options[0]['value'] ?? null;

        return $first !== null ? (string) $first : 'opcion-demo';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function samplePhoto(array $field): mixed
    {
        $captionEnabled = (bool) ($field['caption_enabled'] ?? false);
        $allowMultiple = (bool) ($field['allow_multiple'] ?? false);
        $maxImages = $allowMultiple ? max(1, (int) ($field['max_images'] ?? 4)) : 1;
        $key = (string) ($field['key'] ?? 'foto');

        $count = 1;
        if ($allowMultiple) {
            $count = min($maxImages, $key === 'foto_frenos' || $key === 'foto_neumaticos' ? 3 : 2);
        }

        $captions = ['Vista frontal', 'Detalle izquierdo', 'Detalle derecho', 'Panorámica'];
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $item = ['path' => '__preview_placeholder__'];
            if ($captionEnabled) {
                $item['caption'] = $count > 1
                    ? ($captions[$i] ?? ('Vista '.($i + 1)))
                    : 'Foto de ejemplo';
            }
            $items[] = $item;
        }

        if ($allowMultiple) {
            return $items;
        }

        return $captionEnabled ? $items[0] : '__preview_placeholder__';
    }
}
