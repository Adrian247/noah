<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\FormVersion;
use App\Models\ReportTemplateVersion;
use App\Models\RoutineType;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoutineTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RoutineType::query()
            ->with(['formVersion.definition', 'reportTemplateVersion.template', 'workflowDefinition'])
            ->orderBy('name');

        if (! $request->boolean('all')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
        ]);

        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            abort(400, 'Company context required.');
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);
        if (RoutineType::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => ['Ya existe un tipo con ese slug.']]);
        }

        $type = RoutineType::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => $slug,
            'is_active' => true,
        ]);

        return response()->json(['data' => $type->fresh(['formVersion.definition', 'reportTemplateVersion.template', 'workflowDefinition'])], 201);
    }

    public function update(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $routineType->update($data);

        return response()->json([
            'data' => $routineType->fresh(['formVersion.definition', 'reportTemplateVersion.template', 'workflowDefinition']),
        ]);
    }

    public function destroy(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeAdministrator($request);

        if ($routineType->routines()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay rutinas de este tipo. Desactívalo en su lugar.'], 422);
        }

        $routineType->delete();

        return response()->json(null, 204);
    }

    public function updateDesign(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'form_version_id' => ['nullable', 'integer', 'exists:form_versions,id'],
            'report_template_version_id' => ['nullable', 'integer', 'exists:report_template_versions,id'],
        ]);

        $companyId = app(CurrentCompany::class)->id();

        if (! empty($data['form_version_id'])) {
            $this->assertPublishedFormVersion($data['form_version_id'], $companyId);
        }

        if (! empty($data['report_template_version_id'])) {
            $this->assertPublishedReportVersion($data['report_template_version_id'], $companyId);
        }

        $updates = [];
        if (array_key_exists('form_version_id', $data)) {
            $updates['form_version_id'] = $data['form_version_id'];
        }
        if (array_key_exists('report_template_version_id', $data)) {
            $updates['report_template_version_id'] = $data['report_template_version_id'];
        }

        $routineType->update($updates);

        return response()->json([
            'data' => $routineType->fresh(['formVersion.definition', 'reportTemplateVersion.template', 'workflowDefinition']),
        ]);
    }

    private function assertPublishedFormVersion(int $formVersionId, int $companyId): void
    {
        $version = FormVersion::query()
            ->whereKey($formVersionId)
            ->where('status', 'published')
            ->whereHas('definition', fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if ($version === null) {
            throw ValidationException::withMessages([
                'form_version_id' => ['Debe ser una versión de formulario publicada de esta empresa.'],
            ]);
        }
    }

    private function assertPublishedReportVersion(int $reportVersionId, int $companyId): void
    {
        $version = ReportTemplateVersion::query()
            ->whereKey($reportVersionId)
            ->where('status', 'published')
            ->whereHas('template', fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if ($version === null) {
            throw ValidationException::withMessages([
                'report_template_version_id' => ['Debe ser una versión de reporte publicada de esta empresa.'],
            ]);
        }
    }

    private function authorizeAdministrator(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required.');
        }
    }
}
