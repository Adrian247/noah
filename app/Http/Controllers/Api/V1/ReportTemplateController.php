<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Events\ReportTemplateVersionPublished;
use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function show(ReportTemplate $reportTemplate, FormFieldCatalog $fields): JsonResponse
    {
        return response()->json([
            'data' => $reportTemplate->load(['versions' => fn ($q) => $q->orderByDesc('version')]),
            'form_fields' => $fields->listForCurrentCompany(),
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
}
