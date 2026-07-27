<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\ReportSectionTemplate;
use App\Support\CurrentCompany;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportSectionTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            return response()->json(['data' => []]);
        }

        $rows = ReportSectionTemplate::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (ReportSectionTemplate $row) => self::format($row));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
        ]);

        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            abort(400, 'Company context required.');
        }

        $row = ReportSectionTemplate::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'body' => $data['body'],
        ]);

        $audit->fromRequest($request, 'report.section_template_created', ReportSectionTemplate::class, $row->id);

        return response()->json(['data' => self::format($row)], 201);
    }

    public function update(Request $request, ReportSectionTemplate $reportSectionTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string', 'max:50000'],
        ]);

        $reportSectionTemplate->update($data);
        $audit->fromRequest($request, 'report.section_template_updated', ReportSectionTemplate::class, $reportSectionTemplate->id);

        return response()->json(['data' => self::format($reportSectionTemplate->fresh())]);
    }

    public function destroy(Request $request, ReportSectionTemplate $reportSectionTemplate, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);
        $id = $reportSectionTemplate->id;
        $reportSectionTemplate->delete();
        $audit->fromRequest($request, 'report.section_template_deleted', ReportSectionTemplate::class, $id);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    public static function format(ReportSectionTemplate $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'description' => $row->description,
            'body' => $row->body,
        ];
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
