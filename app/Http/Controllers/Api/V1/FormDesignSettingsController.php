<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CurrentCompany;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormDesignSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormDesignSettingsController extends Controller
{
    public function show(FormDesignSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->forCurrentCompany()]);
    }

    public function update(Request $request, FormDesignSettings $settings, AuditLogger $audit): JsonResponse
    {
        $this->authorizeDesigner($request);

        $data = $request->validate([
            'max_image_size_kb' => ['required', 'integer', 'min:100', 'max:10240'],
            'allowed_image_mimes' => ['required', 'array', 'min:1'],
            'allowed_image_mimes.*' => ['string', 'in:image/jpeg,image/png,image/webp,image/gif'],
        ]);

        $companyId = app(CurrentCompany::class)->id();
        if ($companyId === null) {
            abort(400, 'Company context required.');
        }
        $company = Company::query()->findOrFail($companyId);
        $company->update([
            'form_max_image_size_kb' => $data['max_image_size_kb'],
            'form_allowed_image_mimes' => $data['allowed_image_mimes'],
        ]);

        $audit->fromRequest($request, 'form.settings_updated', Company::class, $company->id);

        return response()->json(['data' => $settings->fromCompany($company->fresh())]);
    }

    private function authorizeDesigner(Request $request): void
    {
        $membership = $request->attributes->get('membership');
        $role = $membership->role;
        $roleValue = $role instanceof MembershipRole ? $role->value : (string) $role;

        if ($roleValue !== MembershipRole::Administrator->value) {
            abort(403, 'Administrator role required for form design.');
        }
    }
}
