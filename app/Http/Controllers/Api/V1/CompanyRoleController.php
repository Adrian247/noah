<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NoahPermission;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Identity\CompanyAuthorizationService;
use App\Support\CurrentCompany;
use App\Support\NoahModuleCatalog;
use Illuminate\Http\JsonResponse;

class CompanyRoleController extends Controller
{
    public function index(CompanyAuthorizationService $authorization): JsonResponse
    {
        $company = Company::query()->findOrFail(app(CurrentCompany::class)->id());

        return response()->json([
            'data' => $authorization->rolesForCompany($company),
            'permission_labels' => $authorization->permissionLabels(),
            'all_permissions' => NoahPermission::values(),
            'permission_groups' => $authorization->permissionGroupsForGranting(),
            'modules_catalog' => NoahModuleCatalog::forApi(),
        ]);
    }
}
