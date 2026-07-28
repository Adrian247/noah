<?php

namespace App\Support;

/**
 * Catálogo de módulos de la UI: lectura / escritura → permisos Noah.
 *
 * @phpstan-type ModuleDefinition array{
 *     id: string,
 *     label: string,
 *     nav_routes: list<string>,
 *     read?: list<string>,
 *     write?: list<string>,
 *     always_visible?: bool,
 *     default_visible?: bool,
 * }
 */
class NoahModuleCatalog
{
    /**
     * @return list<ModuleDefinition>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Inicio',
                'nav_routes' => ['/app/dashboard'],
                'always_visible' => true,
            ],
            [
                'id' => 'routines',
                'label' => 'Rutinas',
                'nav_routes' => ['/app/routines'],
                'read' => ['routines.execute', 'routines.assign', 'routines.validate', 'costs.view'],
                'write' => ['routines.assign', 'routines.validate'],
            ],
            [
                'id' => 'assets',
                'label' => 'Activos',
                'nav_routes' => ['/app/assets'],
                'read' => ['assets.view', 'assets.manage'],
                'write' => ['assets.manage'],
            ],
            [
                'id' => 'catalog_items',
                'label' => 'Equipos',
                'nav_routes' => ['/app/catalog/items'],
                'read' => ['catalog.view', 'catalog.manage'],
                'write' => ['catalog.manage'],
            ],
            [
                'id' => 'catalog_supplies',
                'label' => 'Insumos',
                'nav_routes' => ['/app/catalog/supplies'],
                'read' => ['catalog.view', 'catalog.manage'],
                'write' => ['catalog.manage'],
            ],
            [
                'id' => 'catalog_suppliers',
                'label' => 'Proveedores',
                'nav_routes' => ['/app/catalog/suppliers'],
                'read' => ['catalog.suppliers.view', 'catalog.suppliers.manage'],
                'write' => ['catalog.suppliers.manage'],
            ],
            [
                'id' => 'clients',
                'label' => 'Clientes',
                'nav_routes' => ['/app/catalog/clients'],
                'read' => ['clients.view', 'clients.manage'],
                'write' => ['clients.manage'],
            ],
            [
                'id' => 'sites',
                'label' => 'Sitios',
                'nav_routes' => ['/app/sites'],
                'read' => ['sites.view', 'sites.manage'],
                'write' => ['sites.manage'],
            ],
            [
                'id' => 'design_routine_types',
                'label' => 'Tipos de rutina',
                'nav_routes' => ['/app/routines/types'],
                'read' => ['design.forms.view', 'design.forms'],
                'write' => ['design.forms'],
            ],
            [
                'id' => 'design_forms',
                'label' => 'Formularios',
                'nav_routes' => ['/app/design/forms'],
                'read' => ['design.forms.view', 'design.forms'],
                'write' => ['design.forms'],
            ],
            [
                'id' => 'design_reports',
                'label' => 'Reportes',
                'nav_routes' => ['/app/design/reports'],
                'read' => ['design.reports.view', 'design.reports'],
                'write' => ['design.reports'],
            ],
            [
                'id' => 'design_workflows',
                'label' => 'Workflows',
                'nav_routes' => ['/app/design/workflows'],
                'read' => ['design.workflows.view', 'design.workflows'],
                'write' => ['design.workflows'],
            ],
            [
                'id' => 'billing',
                'label' => 'Facturación',
                'nav_routes' => ['/app/billing'],
                'read' => ['billing.draft', 'billing.draft.edit', 'billing.issue', 'billing.settings', 'costs.view'],
                'write' => ['billing.draft.edit', 'billing.issue', 'billing.settings'],
            ],
            [
                'id' => 'audit',
                'label' => 'Auditoría',
                'nav_routes' => ['/app/audit'],
                'read' => ['audit.view'],
                'write' => [],
            ],
            [
                'id' => 'company_users',
                'label' => 'Usuarios',
                'nav_routes' => ['/app/admin/users'],
                'read' => ['company.users.manage'],
                'write' => ['company.users.manage'],
            ],
        ];
    }

    public static function findById(string $id): ?array
    {
        foreach (self::definitions() as $module) {
            if ($module['id'] === $id) {
                return $module;
            }
        }

        return null;
    }

    public static function findByRoute(string $path): ?array
    {
        foreach (self::definitions() as $module) {
            foreach ($module['nav_routes'] as $route) {
                if ($path === $route || str_starts_with($path, $route.'/')) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function allPermissionSlugsForModule(array $module): array
    {
        $read = $module['read'] ?? [];
        $write = $module['write'] ?? [];

        return array_values(array_unique(array_merge($read, $write)));
    }

    /**
     * @return list<array{id: string, label: string, supports_write: bool, nav_routes: list<string>}>
     */
    public static function forApi(): array
    {
        return array_map(function (array $module): array {
            return [
                'id' => $module['id'],
                'label' => $module['label'],
                'supports_write' => ! empty($module['write']),
                'nav_routes' => $module['nav_routes'],
            ];
        }, self::definitions());
    }
}
