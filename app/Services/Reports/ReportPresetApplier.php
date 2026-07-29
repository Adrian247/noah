<?php

namespace App\Services\Reports;

use App\Enums\FormUsage;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use Illuminate\Validation\ValidationException;

class ReportPresetApplier
{
    /**
     * @return list<array<string, mixed>>
     */
    public function componentsFromFormVersion(FormVersion $version, string $layout = 'full_form'): array
    {
        $components = [
            [
                'type' => 'title',
                'text' => 'Informe de servicio — {{asset_tag}}',
                'align' => 'center',
            ],
            [
                'type' => 'subtitle',
                'text' => '{{company}}',
                'align' => 'center',
            ],
            ['type' => 'divider', 'margin_pt' => 20],
        ];

        $sections = $version->schema['sections'] ?? [];
        $compact = $layout === 'compact_form';

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            if ($sectionTitle !== '') {
                $components[] = [
                    'type' => 'subtitle',
                    'text' => $sectionTitle,
                    'align' => 'left',
                ];
            }

            foreach ($section['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $key = $field['key'] ?? null;
                if (! is_string($key) || $key === '') {
                    continue;
                }
                $fieldType = (string) ($field['type'] ?? 'text');
                if ($fieldType === 'photo') {
                    $components[] = ['type' => 'image', 'field' => $key, 'align' => 'left'];
                } else {
                    $components[] = ['type' => 'paragraph', 'field' => $key, 'align' => 'left'];
                }
            }

            if (! $compact && $index < count($sections) - 1) {
                $components[] = ['type' => 'divider', 'style' => 'dashed', 'margin_pt' => 14];
            }
        }

        $components[] = ['type' => 'divider', 'margin_pt' => 16];
        $components[] = ['type' => 'subtitle', 'text' => 'Comentarios y cierre', 'align' => 'left'];
        $components[] = ['type' => 'paragraph', 'field' => 'corrected_comments', 'align' => 'left'];
        $components[] = ['type' => 'paragraph', 'field' => 'technician_comments', 'align' => 'left'];

        return $components;
    }

    /**
     * @return array{components: list<array<string, mixed>>, page_settings: array<string, mixed>}
     */
    public function build(string $presetId, int $companyId, ?string $formSlug = null): array
    {
        $preset = ReportDesignPresetCatalog::find($presetId);
        if ($preset === null) {
            throw ValidationException::withMessages([
                'preset_id' => ['Plantilla de diseño no válida.'],
            ]);
        }

        $formVersion = $this->resolveFormVersion($companyId, $formSlug);
        if ($formVersion === null) {
            throw ValidationException::withMessages([
                'form_slug' => ['No hay formulario de rutina publicado para generar el informe. Publica un formulario con uso Rutina primero.'],
            ]);
        }

        $components = $this->componentsFromFormVersion($formVersion, (string) $preset['layout']);

        return [
            'components' => $components,
            'page_settings' => $preset['page_settings'],
        ];
    }

    public function applyToDraft(
        ReportTemplate $template,
        string $presetId,
        int $userId,
        ?string $formSlug = null,
    ): ReportTemplateVersion {
        $built = $this->build($presetId, (int) $template->company_id, $formSlug);

        $draft = $template->versions()->where('status', 'draft')->orderByDesc('version')->first();

        if ($draft === null) {
            $next = (int) $template->versions()->max('version') + 1;

            return ReportTemplateVersion::query()->create([
                'report_template_id' => $template->id,
                'version' => $next,
                'status' => 'draft',
                'components' => $built['components'],
                'page_settings' => $built['page_settings'],
                'created_by' => $userId,
            ]);
        }

        $draft->update([
            'components' => $built['components'],
            'page_settings' => $built['page_settings'],
        ]);

        return $draft->fresh();
    }

    private function resolveFormVersion(int $companyId, ?string $formSlug): ?FormVersion
    {
        $query = FormDefinition::query()
            ->where('company_id', $companyId)
            ->where('usage', FormUsage::Routine);

        if ($formSlug !== null && $formSlug !== '') {
            $query->where('slug', $formSlug);
        }

        $forms = $query
            ->with(['versions' => fn ($q) => $q->where('status', 'published')->orderByDesc('version')])
            ->orderBy('name')
            ->get();

        foreach ($forms as $form) {
            $published = $form->versions->first();
            if ($published !== null) {
                return $published;
            }
        }

        return null;
    }
}
