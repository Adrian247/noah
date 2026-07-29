<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PhoenixPermission;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Identity\CompanyAuthorizationService;
use App\Support\CurrentCompany;
use App\Support\PhoenixModuleCatalog;
use Illuminate\Http\JsonResponse;

class CompanyRoleController extends Controller
{
    public function index(CompanyAuthorizationService $authorization): JsonResponse
    {
        $company = Company::query()->findOrFail(app(CurrentCompany::class)->id());

        return response()->json([
            'data' => $authorization->rolesForCompany($company),
            'permission_labels' => $authorization->permissionLabels(),
            'all_permissions' => PhoenixPermission::values(),
            'permission_groups' => $authorization->permissionGroupsForCompanyGranting(),
            'modules_catalog' => PhoenixModuleCatalog::forApi(),
        ]);
    }
}
