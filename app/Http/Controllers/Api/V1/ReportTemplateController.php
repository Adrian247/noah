<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Events\ReportTemplateVersionPublished;
use App\Http\Controllers\Controller;
use App\Models\ReportSectionTemplate;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\RoutineType;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormFieldCatalog;
use App\Services\Reports\FormReportFieldAlignment;
use App\Services\Reports\ReportComponentValidator;
use App\Services\Reports\ReportDesignPresetCatalog;
use App\Services\Reports\ReportHtmlBuilder;
use App\Services\Reports\ReportPageSettingsNormalizer;
use App\Services\Reports\ReportPresetApplier;
use App\Services\Reports\ReportTemplateGuard;
use App\Enums\FormUsage;
use App\Models\FormDefinition;
use App\Models\Company;
use App\Services\Platform\PlatformTenantService;
use App\Support\PlatformAdmin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = ReportTemplate::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version')])
            ->orderBy('name')
            ->get()
            ->map(function (ReportTemplate $template) {
                $latest = $template->versions->first();
                $published = $template->versions->firstWhere('status', 'published');
                $draft = $template->versions->firstWhere('status', 'draft');

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'description' => $template->description,
                    'latest_version' => $latest ? [
                        'id' => $latest->id,
                        'version' => $latest->version,
                        'status' => $latest->status,
                    ] : null,
                    'published_version' => $published ? [
                        'id' => $published->id,
                        'version' => $published->version,
                        'status' => $published->status,
                    ] : null,
                    'draft_version' => $draft ? [
                        'id' => $draft->id,
                        'version' => $draft->version,
                        'status' => $draft->status,
                    ] : null,
                ];
            });

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $template = ReportTemplate::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        ReportTemplateVersion::query()->create([
            'report_template_id' => $template->id,
            'version' => 1,
            'status' => 'draft',
            'components' => [
                ['type' => 'title', 'text' => 'Nuevo reporte'],
            ],
            'page_settings' => ['size' => 'A4'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $template->load('versions')], 201);
    }

    public function update(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $updates = ['name' => $data['name']];
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        $reportTemplate->update($updates);
        $audit->fromRequest($request, 'report.template_renamed', ReportTemplate::class, $reportTemplate->id);

        return response()->json(['data' => $reportTemplate->fresh()]);
    }

    public function destroy(
        Request $request,
        ReportTemplate $reportTemplate,
        ReportTemplateGuard $guard,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeDesigner($request);
        $guard->assertCanDelete($reportTemplate);

        $templateId = $reportTemplate->id;
        $name = $reportTemplate->name;

        $reportTemplate->load('versions');
        foreach ($reportTemplate->versions as $version) {
            $this->purgeCoverImageFromPageSettings(is_array($version->page_settings) ? $version->page_settings : []);
        }

        $reportTemplate->versions()->delete();
        $reportTemplate->delete();

        $audit->fromRequest($request, 'report.template_deleted', ReportTemplate::class, $templateId, [
            'name' => $name,
        ]);

        return response()->json(null, 204);
    }

    public function preview(Request $request, ReportTemplate $reportTemplate, ReportHtmlBuilder $htmlBuilder): \Illuminate\Http\Response
    {
        $thumbnail = $request->boolean('thumbnail');
        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();
        $version = $draft ?? $reportTemplate->versions()->orderByDesc('version')->first();
        $components = $version?->components ?? [];
        $pageSettings = $version?->page_settings ?? [];

        $html = $htmlBuilder->buildPreview($components, $pageSettings, [], $reportTemplate->id, $thumbnail);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function previewDraft(Request $request, ReportTemplate $reportTemplate, ReportHtmlBuilder $htmlBuilder): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'components' => ['nullable', 'array'],
            'page_settings' => ['nullable', 'array'],
        ]);

        $html = $htmlBuilder->buildPreview(
            $data['components'] ?? [],
            $data['page_settings'] ?? [],
            [],
            $reportTemplate->id,
        );

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function previewDraftPdf(Request $request, ReportTemplate $reportTemplate, ReportHtmlBuilder $htmlBuilder): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'components' => ['nullable', 'array'],
            'page_settings' => ['nullable', 'array'],
        ]);

        $html = $htmlBuilder->buildPreviewPdfHtml(
            $data['components'] ?? [],
            $data['page_settings'] ?? [],
            $reportTemplate->id,
        );

        $enablePhp = str_contains($html, 'type="text/php"');
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $pdf->getDomPDF()->set_option('isPhpEnabled', $enablePhp);
        $pdf->getDomPDF()->set_option('isRemoteEnabled', false);

        $filename = 'vista-previa-'.Str::slug($reportTemplate->name ?: 'reporte').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function show(ReportTemplate $reportTemplate, FormFieldCatalog $fields, FormReportFieldAlignment $alignment): JsonResponse
    {
        $sections = ReportSectionTemplate::query()
            ->where('company_id', $reportTemplate->company_id)
            ->orderBy('name')
            ->get()
            ->map(fn (ReportSectionTemplate $row) => ReportSectionTemplateController::format($row));

        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();
        $components = $draft?->components ?? [];
        $orphans = $alignment->orphanFieldsAgainstRoutineForms(
            is_array($components) ? $components : [],
            (int) $reportTemplate->company_id,
        );

        $company = Company::query()->find($reportTemplate->company_id);

        $routineTypeLinks = $this->routineTypeLinksForTemplate($reportTemplate, $alignment, $draft);

        return response()->json([
            'data' => $reportTemplate->load(['versions' => fn ($q) => $q->orderByDesc('version')]),
            'form_fields' => $fields->listForCurrentCompany(),
            'section_templates' => $sections,
            'routine_forms' => $this->routineFormSources((int) $reportTemplate->company_id),
            'routine_type_links' => $routineTypeLinks,
            'company_branding' => [
                'name' => $company?->name,
                'logo_url' => PlatformTenantService::logoUrl($company),
            ],
            'field_alignment' => [
                'orphan_fields' => $orphans,
                'aligned' => $orphans === [],
            ],
        ]);
    }

    public function presets(Request $request): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = array_map(static fn (array $preset) => [
            'id' => $preset['id'],
            'label' => $preset['label'],
            'description' => $preset['description'],
            'layout' => $preset['layout'],
            'swatch' => $preset['swatch'],
        ], ReportDesignPresetCatalog::all());

        return response()->json(['data' => $data]);
    }

    public function applyPreset(
        Request $request,
        ReportTemplate $reportTemplate,
        ReportPresetApplier $applier,
        AuditLogger $audit,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'preset_id' => ['required', 'string', 'max:64'],
            'form_slug' => ['nullable', 'string', 'max:128'],
            'mode' => ['sometimes', 'string', 'in:full,theme_only'],
        ]);

        $draft = $applier->applyToDraft(
            $reportTemplate,
            $data['preset_id'],
            (int) $request->user()->id,
            $data['form_slug'] ?? null,
            $data['mode'] ?? 'full',
        );

        $audit->fromRequest($request, 'report.preset_applied', ReportTemplate::class, $reportTemplate->id, [
            'preset_id' => $data['preset_id'],
            'form_slug' => $data['form_slug'] ?? null,
            'mode' => $data['mode'] ?? 'full',
        ]);

        return response()->json(['data' => $draft]);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function routineFormSources(int $companyId): array
    {
        return FormDefinition::query()
            ->where('company_id', $companyId)
            ->where('usage', FormUsage::Routine)
            ->whereHas('versions', fn ($q) => $q->where('status', 'published'))
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (FormDefinition $form) => [
                'slug' => (string) $form->slug,
                'name' => (string) $form->name,
            ])
            ->all();
    }

    public function updateComponents(
        Request $request,
        ReportTemplate $reportTemplate,
        AuditLogger $audit,
        ReportComponentValidator $validator,
        ReportPageSettingsNormalizer $pageSettingsNormalizer,
    ): JsonResponse {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'components' => ['required', 'array'],
            'page_settings' => ['nullable', 'array'],
        ]);

        $components = $validator->validate($data['components']);
        $pageSettings = array_key_exists('page_settings', $data)
            ? $pageSettingsNormalizer->normalize($data['page_settings'] ?? [])
            : null;

        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();

        if ($draft === null) {
            $next = (int) $reportTemplate->versions()->max('version') + 1;
            $draft = ReportTemplateVersion::query()->create([
                'report_template_id' => $reportTemplate->id,
                'version' => $next,
                'status' => 'draft',
                'components' => $components,
                'page_settings' => $pageSettings ?? $pageSettingsNormalizer->normalize(['size' => 'A4']),
                'created_by' => $request->user()->id,
            ]);
        } else {
            $draft->update([
                'components' => $components,
                'page_settings' => $pageSettings ?? $pageSettingsNormalizer->normalize(
                    is_array($draft->page_settings) ? $draft->page_settings : ['size' => 'A4'],
                ),
            ]);
        }

        $audit->fromRequest($request, 'report.components_updated', ReportTemplate::class, $reportTemplate->id);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function uploadCoverImage(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit, ReportPageSettingsNormalizer $pageSettingsNormalizer): JsonResponse
    {
        $this->authorizeDesigner($request);

        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $draft = $this->ensureDraft($reportTemplate, $request);

        $pageSettings = is_array($draft->page_settings) ? $draft->page_settings : [];
        $cover = is_array($pageSettings['cover_page'] ?? null) ? $pageSettings['cover_page'] : [];
        $oldPath = (string) ($cover['image_path'] ?? '');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->store('report-templates/'.$reportTemplate->id.'/cover', 'public');
        $cover['image_path'] = $path;
        $cover['logo_source'] = 'custom';
        $cover['use_client_logo'] = false;
        $cover['enabled'] = $cover['enabled'] ?? true;
        unset($cover['client_id']);
        $pageSettings['cover_page'] = $cover;
        $pageSettings = $pageSettingsNormalizer->normalize($pageSettings);
        $draft->update(['page_settings' => $pageSettings]);

        $audit->fromRequest($request, 'report.cover_image_updated', ReportTemplate::class, $reportTemplate->id);

        return response()->json([
            'data' => [
                'image_path' => $path,
                'image_url' => self::coverImageUrl($path),
                'page_settings' => $draft->fresh()->page_settings,
            ],
        ]);
    }

    public function deleteCoverImage(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $draft = $this->ensureDraft($reportTemplate, $request);

        $pageSettings = $draft->page_settings ?? [];
        $cover = is_array($pageSettings['cover_page'] ?? null) ? $pageSettings['cover_page'] : [];
        $oldPath = (string) ($cover['image_path'] ?? '');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        unset($cover['image_path']);
        $pageSettings['cover_page'] = $cover;
        $draft->update(['page_settings' => $pageSettings]);

        $audit->fromRequest($request, 'report.cover_image_removed', ReportTemplate::class, $reportTemplate->id);

        return response()->json(['data' => ['page_settings' => $draft->fresh()->page_settings]]);
    }

    public function publish(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit, FormReportFieldAlignment $alignment): JsonResponse
    {
        $this->authorizeDesigner($request);

        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();
        if ($draft === null) {
            return response()->json(['message' => 'No draft to publish.'], 422);
        }

        $orphans = $alignment->orphanFieldsAgainstRoutineForms(
            is_array($draft->components) ? $draft->components : [],
            (int) $reportTemplate->company_id,
        );
        if ($orphans !== []) {
            throw ValidationException::withMessages([
                'components' => [
                    'No se puede publicar: el informe referencia campos que no existen en ningún formulario de rutina publicado: '
                    .implode(', ', $orphans)
                    .'. Corrige los bloques párrafo/imagen o publica primero el formulario con esas keys.',
                ],
            ]);
        }

        $this->assertLinkedRoutineTypesAligned($reportTemplate, $draft, $alignment);

        $draft->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        ReportTemplateVersionPublished::dispatch($draft->fresh());

        $audit->fromRequest($request, 'report.version_published', ReportTemplate::class, $reportTemplate->id, [
            'version' => $draft->version,
        ]);

        ReportTemplateVersion::query()->create([
            'report_template_id' => $reportTemplate->id,
            'version' => $draft->version + 1,
            'status' => 'draft',
            'components' => $draft->components,
            'page_settings' => $draft->page_settings,
            'created_by' => $request->user()->id,
        ]);

        $versionIds = $reportTemplate->versions()->pluck('id');
        $staleTypes = RoutineType::query()
            ->where('company_id', $reportTemplate->company_id)
            ->whereIn('report_template_version_id', $versionIds)
            ->where('report_template_version_id', '!=', $draft->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $draft->fresh(),
            'meta' => [
                'published_version' => $draft->version,
                'routine_types_pending_relink' => $staleTypes->map(fn (RoutineType $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ])->values()->all(),
            ],
        ]);
    }

    private function authorizeDesigner(Request $request): void
    {
        if (PlatformAdmin::isPlatformAdmin($request->user())) {
            return;
        }

        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required for report design.');
        }
    }

    /**
     * @param  array<string, mixed>  $pageSettings
     */
    private function purgeCoverImageFromPageSettings(array $pageSettings): void
    {
        $cover = is_array($pageSettings['cover_page'] ?? null) ? $pageSettings['cover_page'] : [];
        $oldPath = (string) ($cover['image_path'] ?? '');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function draftVersion(ReportTemplate $reportTemplate): ?ReportTemplateVersion
    {
        return $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();
    }

    private function ensureDraft(ReportTemplate $reportTemplate, Request $request): ReportTemplateVersion
    {
        $draft = $this->draftVersion($reportTemplate);
        if ($draft !== null) {
            return $draft;
        }

        $latest = $reportTemplate->versions()->orderByDesc('version')->first();
        $next = (int) ($latest?->version ?? 0) + 1;

        return ReportTemplateVersion::query()->create([
            'report_template_id' => $reportTemplate->id,
            'version' => $next,
            'status' => 'draft',
            'components' => $latest?->components ?? [['type' => 'title', 'text' => 'Nuevo reporte']],
            'page_settings' => $latest?->page_settings ?? ['size' => 'A4'],
            'created_by' => $request->user()->id,
        ]);
    }

    public static function coverImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return list<array{
     *     routine_type_id: int,
     *     routine_type_name: string,
     *     form_slug: string|null,
     *     form_name: string|null,
     *     aligned_with_draft: bool,
     *     missing: list<string>
     * }>
     */
    private function routineTypeLinksForTemplate(
        ReportTemplate $reportTemplate,
        FormReportFieldAlignment $alignment,
        ?ReportTemplateVersion $draft,
    ): array {
        $types = RoutineType::query()
            ->where('company_id', $reportTemplate->company_id)
            ->whereNotNull('report_template_version_id')
            ->whereHas(
                'reportTemplateVersion',
                fn ($q) => $q->where('report_template_id', $reportTemplate->id),
            )
            ->with(['formVersion.definition', 'reportTemplateVersion'])
            ->orderBy('name')
            ->get();

        return $types->map(function (RoutineType $type) use ($alignment, $draft) {
            $compareVersion = $draft ?? $type->reportTemplateVersion;
            $result = $alignment->compare($type->formVersion, $compareVersion);

            return [
                'routine_type_id' => $type->id,
                'routine_type_name' => $type->name,
                'form_slug' => $type->formVersion?->definition?->slug,
                'form_name' => $type->formVersion?->definition?->name,
                'aligned_with_draft' => $result['aligned'],
                'missing' => $result['missing'],
            ];
        })->all();
    }

    private function assertLinkedRoutineTypesAligned(
        ReportTemplate $reportTemplate,
        ReportTemplateVersion $draft,
        FormReportFieldAlignment $alignment,
    ): void {
        $types = RoutineType::query()
            ->where('company_id', $reportTemplate->company_id)
            ->whereNotNull('form_version_id')
            ->whereNotNull('report_template_version_id')
            ->whereHas(
                'reportTemplateVersion',
                fn ($q) => $q->where('report_template_id', $reportTemplate->id),
            )
            ->with(['formVersion.definition', 'reportTemplateVersion'])
            ->get();

        foreach ($types as $type) {
            $result = $alignment->compare($type->formVersion, $draft);
            if ($result['aligned']) {
                continue;
            }

            $alignment->assertAlignedOrFail($type->formVersion, $draft);
        }
    }
}
