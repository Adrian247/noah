<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Events\ReportTemplateVersionPublished;
use App\Http\Controllers\Controller;
use App\Models\ReportSectionTemplate;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormFieldCatalog;
use App\Services\Reports\ReportHtmlBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function show(ReportTemplate $reportTemplate, FormFieldCatalog $fields): JsonResponse
    {
        $sections = ReportSectionTemplate::query()
            ->where('company_id', $reportTemplate->company_id)
            ->orderBy('name')
            ->get()
            ->map(fn (ReportSectionTemplate $row) => ReportSectionTemplateController::format($row));

        return response()->json([
            'data' => $reportTemplate->load(['versions' => fn ($q) => $q->orderByDesc('version')]),
            'form_fields' => $fields->listForCurrentCompany(),
            'section_templates' => $sections,
        ]);
    }

    public function updateComponents(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'components' => ['required', 'array'],
            'page_settings' => ['nullable', 'array'],
        ]);

        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();

        if ($draft === null) {
            $next = (int) $reportTemplate->versions()->max('version') + 1;
            $draft = ReportTemplateVersion::query()->create([
                'report_template_id' => $reportTemplate->id,
                'version' => $next,
                'status' => 'draft',
                'components' => $data['components'],
                'page_settings' => $data['page_settings'] ?? ['size' => 'A4'],
                'created_by' => $request->user()->id,
            ]);
        } else {
            $draft->update([
                'components' => $data['components'],
                'page_settings' => $data['page_settings'] ?? $draft->page_settings,
            ]);
        }

        $audit->fromRequest($request, 'report.components_updated', ReportTemplate::class, $reportTemplate->id);

        return response()->json(['data' => $draft->fresh()]);
    }

    public function uploadCoverImage(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $draft = $this->ensureDraft($reportTemplate, $request);

        $pageSettings = $draft->page_settings ?? [];
        $cover = is_array($pageSettings['cover_page'] ?? null) ? $pageSettings['cover_page'] : [];
        $oldPath = (string) ($cover['image_path'] ?? '');
        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->store('report-templates/'.$reportTemplate->id.'/cover', 'public');
        $cover['image_path'] = $path;
        $cover['use_client_logo'] = false;
        unset($cover['client_id']);
        $pageSettings['cover_page'] = $cover;
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

    public function publish(Request $request, ReportTemplate $reportTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $draft = $reportTemplate->versions()->where('status', 'draft')->orderByDesc('version')->first();
        if ($draft === null) {
            return response()->json(['message' => 'No draft to publish.'], 422);
        }

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

        return response()->json(['data' => $draft->fresh()]);
    }

    private function authorizeDesigner(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required for report design.');
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
}
