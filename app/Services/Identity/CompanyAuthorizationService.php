<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Enums\PhoenixPermission;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\PhoenixModuleCatalog;
use App\Support\PlatformAdmin;
use App\Support\TenantAdministratorPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyAuthorizationService
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
        private readonly RolePermissionTemplateService $roleTemplates,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array
    {
        return $this->roleTemplates->map();
    }

    public function syncPermissionCatalog(): void
    {
        foreach (PhoenixPermission::cases() as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web'],
            );
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function ensureCompanyRoles(Company $company): void
    {
        $this->syncPermissionCatalog();

        $this->registrar->setPermissionsTeamId($company->id);

        $map = $this->rolePermissionMap();

        foreach ($map as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissionNames);
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function syncMembershipRole(CompanyMembership $membership): void
    {
        $user = $membership->user;
        if ($user === null) {
            $membership->load('user');
            $user = $membership->user;
        }

        if ($user === null) {
            return;
        }

        $membership->loadMissing('company');
        $this->ensureCompanyRoles($membership->company);

        $roleValue = $membership->role instanceof MembershipRole
            ? $membership->role->value
            : (string) $membership->role;

        $this->registrar->setPermissionsTeamId($membership->company_id);
        $role = Role::findByName($roleValue, 'web');
        $user->syncRoles($role);
        $this->registrar->forgetCachedPermissions();
    }

    public function bootstrapAllCompanies(): void
    {
        $this->syncPermissionCatalog();

        Company::query()->where('is_active', true)->each(function (Company $company): void {
            $this->ensureCompanyRoles($company);
        });

        CompanyMembership::query()
            ->where('is_active', true)
            ->with(['user', 'company'])
            ->each(function (CompanyMembership $membership): void {
                $this->syncMembershipRole($membership);
            });
    }

    public function assignMembershipRole(
        CompanyMembership $membership,
        MembershipRole $role,
        bool $isActive,
    ): CompanyMembership {
        return DB::transaction(function () use ($membership, $role, $isActive) {
            $updates = [
                'role' => $role,
                'is_active' => $isActive,
            ];
            if ($role !== MembershipRole::Client) {
                $updates['client_id'] = null;
            }
            $membership->update($updates);

            if ($isActive) {
                $this->syncMembershipRole($membership->fresh(['user', 'company']));
                $membership = $membership->fresh(['user', 'company']);
                $this->pruneDirectPermissions($membership);
            } else {
                $this->registrar->setPermissionsTeamId($membership->company_id);
                $membership->user?->syncRoles([]);
                $this->registrar->forgetCachedPermissions();
            }

            return $membership->fresh();
        });
    }

    /**
     * @return list<string>
     */
    public function permissionsForUser(User $user, int $companyId): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            if (PlatformAdmin::isPlatformAdmin($user)) {
                return $this->permissionsForPlatformAssumption();
            }

            return [];
        }

        return $this->baseSpatiePermissions($user, $companyId);
    }

    /**
     * @return array<string, array{read: bool, write: bool, visible: bool}>
     */
    public function modulesForMembership(CompanyMembership $membership): array
    {
        $membership->loadMissing('user');
        $user = $membership->user;
        if ($user === null) {
            return [];
        }

        if (! $membership->exists && PlatformAdmin::isPlatformAdmin($user)) {
            return $this->modulesForPlatformAssumption($user);
        }

        $base = $this->baseSpatiePermissions($user, $membership->company_id);
        $effective = $base;

        $result = [];
        foreach (PhoenixModuleCatalog::definitions() as $module) {
            $id = $module['id'];

            if (PlatformAdmin::isPlatformAdmin($user) && $id === 'design_workflows') {
                $result[$id] = ['read' => true, 'write' => true, 'visible' => true];

                continue;
            }

            if (! empty($module['always_visible'])) {
                $result[$id] = ['read' => true, 'write' => true, 'visible' => true];

                continue;
            }

            $readPerms = $module['read'] ?? [];
            $writePerms = $module['write'] ?? [];
            $hasRead = $this->hasAnyPermission($effective, $readPerms);
            $hasWrite = $this->hasAnyPermission($effective, $writePerms);
            $defaultVisible = (bool) ($module['default_visible'] ?? false);
            $visible = $defaultVisible || $hasRead || $hasWrite;

            if ($defaultVisible && ! $hasRead && ! $hasWrite) {
                $hasRead = true;
            }

            $result[$id] = [
                'read' => $hasRead,
                'write' => $hasWrite,
                'visible' => $visible,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function permissionsForPlatformAssumption(): array
    {
        return array_values(array_unique(array_merge(
            TenantAdministratorPermissions::slugs(),
            [
                PhoenixPermission::DesignWorkflows->value,
                PhoenixPermission::DesignWorkflowsView->value,
            ],
        )));
    }

    /**
     * @return array<string, array{read: bool, write: bool, visible: bool}>
     */
    public function modulesForPlatformAssumption(User $user): array
    {
        $effective = $this->permissionsForPlatformAssumption();
        $result = [];

        foreach (PhoenixModuleCatalog::definitions() as $module) {
            $id = $module['id'];

            if (! empty($module['always_visible'])) {
                $result[$id] = ['read' => true, 'write' => true, 'visible' => true];

                continue;
            }

            $readPerms = $module['read'] ?? [];
            $writePerms = $module['write'] ?? [];
            $hasRead = $this->hasAnyPermission($effective, $readPerms);
            $hasWrite = $this->hasAnyPermission($effective, $writePerms);
            $visible = $hasRead || $hasWrite;

            $result[$id] = [
                'read' => $hasRead,
                'write' => $hasWrite,
                'visible' => $visible,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function baseSpatiePermissions(User $user, int $companyId): array
    {
        $this->registrar->setPermissionsTeamId($companyId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }

    public function clearLegacyModuleAccess(CompanyMembership $membership): void
    {
        if ($membership->module_access !== null && $membership->module_access !== []) {
            $membership->update(['module_access' => null]);
        }
    }

    /**
     * Grupos de permisos para la UI de concesiones (admin de empresa).
     *
     * @return list<array{
     *     module_id: string,
     *     module_label: string,
     *     permissions: list<array{slug: string, label: string}>
     * }>
     */
    public function permissionGroupsForGranting(): array
    {
        return $this->buildPermissionGroups([]);
    }

    /**
     * Concesiones de permisos en empresas cliente (sin diseño de workflows).
     *
     * @return list<array{
     *     module_id: string,
     *     module_label: string,
     *     permissions: list<array{slug: string, label: string}>
     * }>
     */
    public function permissionGroupsForCompanyGranting(): array
    {
        return $this->buildPermissionGroups([
            PhoenixPermission::DesignWorkflows->value,
            PhoenixPermission::DesignWorkflowsView->value,
        ]);
    }

    /**
     * @param  list<string>  $excludeSlugs
     * @return list<array{
     *     module_id: string,
     *     module_label: string,
     *     permissions: list<array{slug: string, label: string}>
     * }>
     */
    private function buildPermissionGroups(array $excludeSlugs): array
    {
        $labels = $this->permissionLabels();
        $groups = [];

        foreach (PhoenixModuleCatalog::definitions() as $module) {
            if (! empty($module['always_visible'])) {
                continue;
            }

            if ($module['id'] === 'design_workflows' && $excludeSlugs !== []) {
                continue;
            }

            $slugs = PhoenixModuleCatalog::allPermissionSlugsForModule($module);
            if ($slugs === []) {
                continue;
            }

            $permissions = [];
            foreach ($slugs as $slug) {
                if (in_array($slug, $excludeSlugs, true)) {
                    continue;
                }
                $permissions[] = [
                    'slug' => $slug,
                    'label' => $labels[$slug] ?? $slug,
                ];
            }

            if ($permissions === []) {
                continue;
            }

            usort($permissions, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

            $groups[] = [
                'module_id' => $module['id'],
                'module_label' => $module['label'],
                'permissions' => $permissions,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $needles
     */
    private function hasAnyPermission(array $permissions, array $needles): bool
    {
        if ($needles === []) {
            return false;
        }

        foreach ($needles as $needle) {
            if (in_array($needle, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function rolePermissionsForMembership(CompanyMembership $membership): array
    {
        $roleValue = $membership->role instanceof MembershipRole
            ? $membership->role->value
            : (string) $membership->role;

        return $this->rolePermissionMap()[$roleValue] ?? [];
    }

    /**
     * Permisos asignados directamente al usuario (sin pasar por el rol).
     *
     * @return list<string>
     */
    public function directPermissionsForUser(User $user, int $companyId): array
    {
        $this->registrar->setPermissionsTeamId($companyId);
        $user->unsetRelation('permissions');

        return $user->permissions->pluck('name')->values()->all();
    }

    /**
     * @param  list<string>  $requestedSlugs
     * @return list<string>
     */
    public function syncDirectPermissions(CompanyMembership $membership, array $requestedSlugs): array
    {
        $this->syncPermissionCatalog();

        $membership->loadMissing('user');
        $user = $membership->user;
        if ($user === null) {
            return [];
        }

        $rolePermissions = $this->rolePermissionsForMembership($membership);
        $allowedCatalog = PhoenixPermission::values();

        $extras = [];
        foreach ($requestedSlugs as $slug) {
            if (! is_string($slug) || ! in_array($slug, $allowedCatalog, true)) {
                throw ValidationException::withMessages([
                    'extra_permissions' => ["Permiso no válido: {$slug}"],
                ]);
            }
            if (in_array($slug, $rolePermissions, true)) {
                continue;
            }
            if ($slug === PhoenixPermission::CompanyUsersManage->value) {
                $roleValue = $membership->role instanceof MembershipRole
                    ? $membership->role->value
                    : (string) $membership->role;
                if ($roleValue !== MembershipRole::Administrator->value) {
                    throw ValidationException::withMessages([
                        'extra_permissions' => ['Solo un administrador puede tener permiso de administrar usuarios.'],
                    ]);
                }
            }
            if (in_array($slug, [
                PhoenixPermission::DesignWorkflows->value,
                PhoenixPermission::DesignWorkflowsView->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'extra_permissions' => ['Los permisos de workflow solo los gestiona el administrador de plataforma.'],
                ]);
            }
            $extras[] = $slug;
        }

        $extras = array_values(array_unique($extras));

        $this->registrar->setPermissionsTeamId($membership->company_id);
        $user->syncPermissions($extras);
        $this->registrar->forgetCachedPermissions();

        return $extras;
    }

    public function pruneDirectPermissions(CompanyMembership $membership): void
    {
        $membership->loadMissing('user');
        if ($membership->user === null) {
            return;
        }

        $current = $this->directPermissionsForUser($membership->user, $membership->company_id);
        if ($current === []) {
            return;
        }

        $this->syncDirectPermissions($membership, $current);
    }

    /**
     * @return array<string, string>
     */
    public function permissionLabels(): array
    {
        return [
            'catalog.manage' => 'Gestionar catálogos',
            'catalog.view' => 'Ver catálogos (equipos e insumos)',
            'assets.manage' => 'Gestionar activos',
            'assets.view' => 'Ver activos',
            'design.forms' => 'Diseñar formularios',
            'design.forms.view' => 'Ver formularios y tipos de servicio',
            'design.reports' => 'Diseñar reportes',
            'design.reports.view' => 'Ver reportes',
            'design.workflows' => 'Diseñar workflows',
            'design.workflows.view' => 'Ver workflows',
            'routines.assign' => 'Asignar servicios',
            'routines.execute' => 'Ejecutar servicios',
            'routines.validate' => 'Validar servicios',
            'costs.view' => 'Ver costos',
            'billing.draft' => 'Borradores de factura',
            'billing.issue' => 'Emitir facturas',
            'billing.settings' => 'Configuración de facturación',
            'audit.view' => 'Ver auditoría',
            'company.users.manage' => 'Administrar usuarios',
            'catalog.suppliers.manage' => 'Gestionar proveedores',
            'catalog.suppliers.view' => 'Ver proveedores',
            'inventory.view' => 'Ver inventario operativo',
            'inventory.manage' => 'Gestionar inventario y movimientos',
            'sites.view' => 'Ver sitios',
            'sites.manage' => 'Gestionar sitios',
            'clients.manage' => 'Administrar clientes',
            'clients.view' => 'Ver clientes',
            'billing.draft.edit' => 'Editar prefacturas',
            'portal.invoices.view' => 'Portal: ver facturas',
            'portal.invoices.download' => 'Portal: descargar facturas',
            'portal.routines.view' => 'Portal: ver servicios',
            'integrations.view' => 'Ver integraciones',
            'integrations.manage' => 'Gestionar integraciones',
            'automation.manage' => 'Gestionar automatización',
            'insights.use' => 'Usar insights IA',
        ];
    }

    public function userHasPermission(User $user, int $companyId, string $permission): bool
    {
        return in_array($permission, $this->permissionsForUser($user, $companyId), true);
    }

    /**
     * @return list<array{name: string, label: string, permissions: list<string>}>
     */
    public function rolesForCompany(Company $company): array
    {
        $this->ensureCompanyRoles($company);
        $labels = $this->roleLabels();
        $map = $this->rolePermissionMap();
        $result = [];

        foreach ($map as $roleName => $permissions) {
            $result[] = [
                'name' => $roleName,
                'label' => $labels[$roleName] ?? $roleName,
                'permissions' => $permissions,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function roleLabels(): array
    {
        return [
            MembershipRole::Administrator->value => 'Administrador',
            MembershipRole::Supervisor->value => 'Supervisor',
            MembershipRole::Technician->value => 'Técnico',
            MembershipRole::Billing->value => 'Facturación',
            MembershipRole::Auditor->value => 'Auditor',
            MembershipRole::Client->value => 'Cliente (portal)',
        ];
    }
}
