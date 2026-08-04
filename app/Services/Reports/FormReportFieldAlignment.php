<?php

namespace App\Services\Reports;

use App\Enums\FormUsage;
use App\Models\FormVersion;
use App\Models\ReportTemplateVersion;

/**
 * Detecta campos del informe que no existen en el formulario de servicio enlazado.
 */
final class FormReportFieldAlignment
{
    /**
     * Keys permitidas aunque no estén en el esquema del formulario (datos de ejecución).
     *
     * @var list<string>
     */
    public const EXECUTION_KEYS = [
        'technician_comments',
        'corrected_comments',
    ];

    /**
     * @return array{
     *     aligned: bool,
     *     missing: list<string>,
     *     missing_images: list<string>,
     *     form_keys: list<string>,
     *     report_fields: list<array{type: string, field: string}>
     * }
     */
    public function compare(?FormVersion $formVersion, ?ReportTemplateVersion $reportVersion): array
    {
        $formKeys = $this->formFieldKeys($formVersion);
        $reportFields = $this->reportFieldRefs($reportVersion);

        $allowed = array_values(array_unique([
            ...$formKeys,
            ...self::EXECUTION_KEYS,
        ]));

        $missing = [];
        $missingImages = [];

        foreach ($reportFields as $ref) {
            $field = $ref['field'];
            if (in_array($field, $allowed, true)) {
                continue;
            }
            $missing[] = $field;
            if ($ref['type'] === 'image') {
                $missingImages[] = $field;
            }
        }

        $missing = array_values(array_unique($missing));
        $missingImages = array_values(array_unique($missingImages));

        return [
            'aligned' => $missing === [],
            'missing' => $missing,
            'missing_images' => $missingImages,
            'form_keys' => $formKeys,
            'report_fields' => $reportFields,
        ];
    }

    /**
     * @return list<string>
     */
    public function formFieldKeys(?FormVersion $formVersion): array
    {
        if ($formVersion === null) {
            return [];
        }

        $keys = [];
        foreach ($formVersion->schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if (is_string($key) && $key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{type: string, field: string}>
     */
    public function reportFieldRefs(?ReportTemplateVersion $reportVersion): array
    {
        if ($reportVersion === null) {
            return [];
        }

        $refs = [];
        foreach ($reportVersion->components ?? [] as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = (string) ($component['type'] ?? '');
            if (! in_array($type, ['paragraph', 'image'], true)) {
                continue;
            }
            $field = $component['field'] ?? null;
            if (! is_string($field) || $field === '') {
                continue;
            }
            $refs[] = ['type' => $type, 'field' => $field];
        }

        return $refs;
    }

    public function assertAlignedOrFail(?FormVersion $formVersion, ?ReportTemplateVersion $reportVersion): void
    {
        if ($formVersion === null || $reportVersion === null) {
            return;
        }

        $result = $this->compare($formVersion, $reportVersion);
        if ($result['aligned']) {
            return;
        }

        $list = implode(', ', $result['missing']);
        $images = $result['missing_images'] !== []
            ? ' Incluye imágenes sin campo en el formulario: '.implode(', ', $result['missing_images']).'.'
            : '';

        $formLabel = $formVersion->definition?->name ?? 'formulario de servicio';
        $formSlug = $formVersion->definition?->slug;
        $slugHint = is_string($formSlug) && $formSlug !== ''
            ? " (slug «{$formSlug}»)"
            : '';

        $message = 'El informe referencia campos que no existen en el formulario de servicio enlazado «'
            .$formLabel.'»'.$slugHint.': '.$list.'.'
            .$images
            .' Ajusta el diseñador de reportes para usar las mismas keys del formulario, o enlaza en el tipo de servicio la versión de formulario que corresponda al informe.';

        throw \Illuminate\Validation\ValidationException::withMessages([
            'report_template_version_id' => [$message],
            'form_version_id' => [$message],
        ]);
    }

    /**
     * Campos huérfanos del informe respecto a formularios de uso Servicio publicados.
     *
     * @param  list<array<string, mixed>>  $components
     * @return list<string>
     */
    public function orphanFieldsAgainstRoutineForms(array $components, int $companyId): array
    {
        $allowed = self::EXECUTION_KEYS;

        $forms = \App\Models\FormDefinition::query()
            ->where('company_id', $companyId)
            ->where('usage', FormUsage::Service)
            ->with(['versions' => fn ($q) => $q->where('status', 'published')->orderByDesc('version')])
            ->get();

        foreach ($forms as $form) {
            $published = $form->versions->first();
            if ($published === null) {
                continue;
            }
            $allowed = array_merge($allowed, $this->formFieldKeys($published));
        }

        $allowed = array_values(array_unique($allowed));
        $orphan = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = (string) ($component['type'] ?? '');
            if (! in_array($type, ['paragraph', 'image'], true)) {
                continue;
            }
            $field = $component['field'] ?? null;
            if (! is_string($field) || $field === '' || in_array($field, $allowed, true)) {
                continue;
            }
            $orphan[] = $field;
        }

        return array_values(array_unique($orphan));
    }
}
