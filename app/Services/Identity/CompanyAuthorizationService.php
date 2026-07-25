<?php

namespace App\Services\Identity;

use App\Enums\MembershipRole;
use App\Enums\NoahPermission;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\NoahModuleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyAuthorizationService
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public function rolePermissionMap(): array
    {
        $all = NoahPermission::values();

        return [
            MembershipRole::Administrator->value => $all,
            MembershipRole::Supervisor->value => [
                NoahPermission::RoutinesAssign->value,
                NoahPermission::RoutinesValidate->value,
                NoahPermission::CostsView->value,
                NoahPermission::CatalogSuppliersManage->value,
            ],
            MembershipRole::Technician->value => [
                NoahPermission::RoutinesExecute->value,
            ],
            MembershipRole::Billing->value => [
                NoahPermission::BillingDraft->value,
                NoahPermission::BillingDraftEdit->value,
                NoahPermission::BillingIssue->value,
                NoahPermission::BillingSettings->value,
                NoahPermission::CostsView->value,
                NoahPermission::ClientsView->value,
            ],
            MembershipRole::Auditor->value => [
                NoahPermission::AuditView->value,
            ],
        ];
    }

    public function syncPermissionCatalog(): void
    {
        foreach (NoahPermission::cases() as $permission) {
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
            $membership->update([
                'role' => $role,
                'is_active' => $isActive,
            ]);

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
            return [];
        }

        $this->registrar->setPermissionsTeamId($companyId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        $base = $user->getAllPermissions()->pluck('name')->values()->all();

        return $this->applyModuleAccessOverrides($membership, $base);
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

        $base = $this->baseSpatiePermissions($user, $membership->company_id);
        $effective = $this->applyModuleAccessOverrides($membership, $base);
        $overrides = $this->normalizedModuleAccess($membership->module_access);

        $result = [];
        foreach (NoahModuleCatalog::definitions() as $module) {
            $id = $module['id'];

            if (! empty($module['always_visible'])) {
                $result[$id] = ['read' => true, 'write' => true, 'visible' => true];

                continue;
            }

            if ($overrides !== null && array_key_exists($id, $overrides)) {
                $read = $overrides[$id]['read'];
                $write = $overrides[$id]['write'];
                $result[$id] = [
                    'read' => $read,
                    'write' => $write,
                    'visible' => $read || $write,
                ];

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
     * @param  array<string, array{read?: bool, write?: bool}>  $modules
     */
    public function syncModuleAccess(CompanyMembership $membership, array $modules): void
    {
        $normalized = [];
        foreach (NoahModuleCatalog::definitions() as $definition) {
            $id = $definition['id'];
            if (! empty($definition['always_visible'])) {
                continue;
            }
            if (! array_key_exists($id, $modules)) {
                throw ValidationException::withMessages([
                    'modules' => ["Falta el módulo: {$id}"],
                ]);
            }
            $read = (bool) ($modules[$id]['read'] ?? false);
            $write = (bool) ($modules[$id]['write'] ?? false);

            if ($id === 'company_users' && ($read || $write)) {
                $roleValue = $membership->role instanceof MembershipRole
                    ? $membership->role->value
                    : (string) $membership->role;
                if ($roleValue !== MembershipRole::Administrator->value) {
                    throw ValidationException::withMessages([
                        'modules' => ['El módulo Usuarios solo aplica al rol Administrador.'],
                    ]);
                }
            }

            if ($write && empty($definition['write'])) {
                throw ValidationException::withMessages([
                    'modules' => ["El módulo {$id} no admite escritura."],
                ]);
            }

            $normalized[$id] = ['read' => $read, 'write' => $write];
        }

        $membership->update(['module_access' => $normalized]);
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

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function applyModuleAccessOverrides(CompanyMembership $membership, array $permissions): array
    {
        $overrides = $this->normalizedModuleAccess($membership->module_access);
        if ($overrides === null) {
            return array_values(array_unique($permissions));
        }

        $set = array_fill_keys($permissions, true);

        foreach (NoahModuleCatalog::definitions() as $module) {
            $id = $module['id'];
            if (! array_key_exists($id, $overrides) || ! empty($module['always_visible'])) {
                continue;
            }

            foreach (NoahModuleCatalog::allPermissionSlugsForModule($module) as $slug) {
                unset($set[$slug]);
            }

            $entry = $overrides[$id];
            if (! $entry['read'] && ! $entry['write']) {
                continue;
            }

            if ($entry['read'] || $entry['write']) {
                foreach ($module['read'] ?? [] as $slug) {
                    $set[$slug] = true;
                }
            }
            if ($entry['write']) {
                foreach ($module['write'] ?? [] as $slug) {
                    $set[$slug] = true;
                }
            }
        }

        return array_keys($set);
    }

    /**
     * @return array<string, array{read: bool, write: bool}>|null
     */
    private function normalizedModuleAccess(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $out = [];
        foreach ($raw as $id => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $out[(string) $id] = [
                'read' => (bool) ($entry['read'] ?? false),
                'write' => (bool) ($entry['write'] ?? false),
            ];
        }

        return $out === [] ? null : $out;
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
        $allowedCatalog = NoahPermission::values();

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
            if ($slug === NoahPermission::CompanyUsersManage->value) {
                $roleValue = $membership->role instanceof MembershipRole
                    ? $membership->role->value
                    : (string) $membership->role;
                if ($roleValue !== MembershipRole::Administrator->value) {
                    throw ValidationException::withMessages([
                        'extra_permissions' => ['Solo un administrador puede tener permiso de administrar usuarios.'],
                    ]);
                }
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
            'design.forms.view' => 'Ver formularios y tipos de rutina',
            'design.reports' => 'Diseñar reportes',
            'design.reports.view' => 'Ver reportes',
            'design.workflows' => 'Diseñar workflows',
            'design.workflows.view' => 'Ver workflows',
            'routines.assign' => 'Asignar rutinas',
            'routines.execute' => 'Ejecutar rutinas',
            'routines.validate' => 'Validar rutinas',
            'costs.view' => 'Ver costos',
            'billing.draft' => 'Borradores de factura',
            'billing.issue' => 'Emitir facturas',
            'billing.settings' => 'Configuración de facturación',
            'audit.view' => 'Ver auditoría',
            'company.users.manage' => 'Administrar usuarios',
            'catalog.suppliers.manage' => 'Gestionar proveedores',
            'catalog.suppliers.view' => 'Ver proveedores',
            'sites.view' => 'Ver sitios',
            'sites.manage' => 'Gestionar sitios',
            'clients.manage' => 'Administrar clientes',
            'clients.view' => 'Ver clientes',
            'billing.draft.edit' => 'Editar prefacturas',
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
        ];
    }
}
