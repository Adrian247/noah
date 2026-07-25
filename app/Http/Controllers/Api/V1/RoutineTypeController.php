<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\FormVersion;
use App\Models\ReportTemplateVersion;
use App\Models\RoutineType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoutineTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RoutineType::query()
                ->with(['formVersion.definition', 'reportTemplateVersion.template', 'workflowDefinition'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateDesign(Request $request, RoutineType $routineType): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'form_version_id' => ['nullable', 'integer', 'exists:form_versions,id'],
            'report_template_version_id' => ['nullable', 'integer', 'exists:report_template_versions,id'],
        ]);

        $companyId = app(\App\Support\CurrentCompany::class)->id();

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
