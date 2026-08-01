<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAiSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        return response()->json([
            'data' => [
                'ai_enabled' => (bool) $company->ai_enabled,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
        ]);

        $company = app(CurrentCompany::class)->company;
        abort_if($company === null, 400, 'Company context required.');

        $company->update(['ai_enabled' => $data['ai_enabled']]);

        return $this->show();
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
