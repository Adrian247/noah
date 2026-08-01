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
                    $label = trim((string) ($field['label'] ?? ''));
                    $paragraph = ['type' => 'paragraph', 'field' => $key, 'align' => 'left', 'show_field_key' => false];
                    if ($label !== '') {
                        $paragraph['label'] = $label;
                    }
                    $components[] = $paragraph;
                }
            }

            if (! $compact && $index < count($sections) - 1) {
                $components[] = ['type' => 'divider', 'style' => 'dashed', 'margin_pt' => 14];
            }
        }

        $components[] = ['type' => 'divider', 'margin_pt' => 16];
        $components[] = ['type' => 'subtitle', 'text' => 'Comentarios y cierre', 'align' => 'left'];
        $components[] = ['type' => 'paragraph', 'field' => 'corrected_comments', 'align' => 'left', 'show_field_key' => false];
        $components[] = ['type' => 'paragraph', 'field' => 'technician_comments', 'align' => 'left', 'show_field_key' => false];

        return $components;
    }

    /**
     * @return array{components: list<array<string, mixed>>, page_settings: array<string, mixed>}
     */
    public function build(string $presetId, int $companyId, ?string $formSlug = null, string $mode = 'full'): array
    {
        $preset = ReportDesignPresetCatalog::find($presetId);
        if ($preset === null) {
            throw ValidationException::withMessages([
                'preset_id' => ['Plantilla de diseño no válida.'],
            ]);
        }

        if ($mode === 'theme_only') {
            return [
                'components' => [],
                'page_settings' => $preset['page_settings'],
            ];
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
        string $mode = 'full',
    ): ReportTemplateVersion {
        $mode = $mode === 'theme_only' ? 'theme_only' : 'full';
        $built = $this->build($presetId, (int) $template->company_id, $formSlug, $mode);

        $draft = $template->versions()->where('status', 'draft')->orderByDesc('version')->first();

        if ($draft === null) {
            $next = (int) $template->versions()->max('version') + 1;
            $components = $mode === 'theme_only'
                ? [
                    ['type' => 'title', 'text' => 'Informe de servicio — {{asset_tag}}', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => '{{company}}', 'align' => 'center'],
                ]
                : $built['components'];

            return ReportTemplateVersion::query()->create([
                'report_template_id' => $template->id,
                'version' => $next,
                'status' => 'draft',
                'components' => $components,
                'page_settings' => $this->normalizeSettings($built['page_settings']),
                'created_by' => $userId,
            ]);
        }

        if ($mode === 'theme_only') {
            $draft->update([
                'page_settings' => $this->normalizeSettings($this->mergeThemePageSettings(
                    is_array($draft->page_settings) ? $draft->page_settings : [],
                    $built['page_settings'],
                )),
            ]);
        } else {
            $draft->update([
                'components' => $built['components'],
                'page_settings' => $this->normalizeSettings($built['page_settings']),
            ]);
        }

        return $draft->fresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeSettings(array $settings): array
    {
        return app(ReportPageSettingsNormalizer::class)->normalize($settings);
    }

    /**
     * Conserva textos/imágenes de portada existentes; aplica tema, tipografía y chrome.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $presetSettings
     * @return array<string, mixed>
     */
    private function mergeThemePageSettings(array $current, array $presetSettings): array
    {
        $merged = $current;
        foreach (['font_family', 'typography', 'header', 'footer', 'page_number', 'theme'] as $key) {
            if (array_key_exists($key, $presetSettings)) {
                $merged[$key] = $presetSettings[$key];
            }
        }

        $presetCover = is_array($presetSettings['cover_page'] ?? null) ? $presetSettings['cover_page'] : [];
        $currentCover = is_array($current['cover_page'] ?? null) ? $current['cover_page'] : [];
        $merged['cover_page'] = array_merge($presetCover, array_filter([
            'title' => $currentCover['title'] ?? null,
            'subtitle' => $currentCover['subtitle'] ?? null,
            'body' => $currentCover['body'] ?? null,
            'image_path' => $currentCover['image_path'] ?? null,
            'logo_source' => $currentCover['logo_source'] ?? null,
            'client_id' => $currentCover['client_id'] ?? null,
            'enabled' => $currentCover['enabled'] ?? ($presetCover['enabled'] ?? true),
            'show_date' => $currentCover['show_date'] ?? ($presetCover['show_date'] ?? null),
            'date_fixed' => $currentCover['date_fixed'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));
        $merged['cover_page']['omit_header_footer'] = true;

        return $merged;
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
