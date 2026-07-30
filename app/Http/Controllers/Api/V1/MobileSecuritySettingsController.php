<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSecuritySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        return response()->json([
            'data' => $this->format($company),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'mobile_require_app_lock' => ['required', 'boolean'],
            'mobile_allow_biometric_unlock' => ['required', 'boolean'],
        ]);

        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        $company->update([
            'mobile_require_app_lock' => $data['mobile_require_app_lock'],
            'mobile_allow_biometric_unlock' => $data['mobile_allow_biometric_unlock'],
        ]);

        return $this->show();
    }

    /**
     * @return array<string, mixed>
     */
    private function format(\App\Models\Company $company): array
    {
        return [
            'mobile_require_app_lock' => (bool) $company->mobile_require_app_lock,
            'mobile_allow_biometric_unlock' => (bool) $company->mobile_allow_biometric_unlock,
        ];
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
