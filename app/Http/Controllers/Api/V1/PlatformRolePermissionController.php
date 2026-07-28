<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Identity\RolePermissionTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformRolePermissionController extends Controller
{
    public function show(
        RolePermissionTemplateService $templates,
        CompanyAuthorizationService $authorization,
    ): JsonResponse {
        return response()->json([
            'data' => $this->payload($templates, $authorization),
        ]);
    }

    public function update(
        Request $request,
        RolePermissionTemplateService $templates,
        CompanyAuthorizationService $authorization,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
        ]);

        $map = $templates->saveMap($validated['roles']);
        $authorization->bootstrapAllCompanies();

        $audit->fromRequest(
            $request,
            'platform.role_permissions_updated',
            null,
            null,
            ['roles' => array_keys($map)],
        );

        return response()->json([
            'data' => $this->payload($templates, $authorization),
            'message' => 'Plantilla de roles actualizada y sincronizada en todas las empresas.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        RolePermissionTemplateService $templates,
        CompanyAuthorizationService $authorization,
    ): array {
        $map = $templates->map();
        $labels = $authorization->roleLabels();

        $roles = [];
        foreach ($map as $name => $permissions) {
            $roles[] = [
                'name' => $name,
                'label' => $labels[$name] ?? $name,
                'permissions' => $permissions,
                'locked' => $name === 'administrator',
            ];
        }

        return [
            'roles' => $roles,
            'permission_labels' => $authorization->permissionLabels(),
            'permission_groups' => $authorization->permissionGroupsForGranting(),
        ];
    }
}
