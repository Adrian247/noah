import { computed } from 'vue';
import { useCompanyStore } from '@/stores/company';

export type ModuleAccessState = { read: boolean; write: boolean; visible: boolean };

/** Debe coincidir con `App\Support\PhoenixModuleCatalog` (orden: rutas más largas primero). */
const ROUTE_MODULE_PAIRS: [string, string][] = [
    ['/app/billing/settings', 'billing'],
    ['/app/admin/users', 'company_users'],
    ['/app/catalog/clients', 'clients'],
    ['/app/catalog/suppliers', 'catalog_suppliers'],
    ['/app/catalog/items/types', 'catalog_items'],
    ['/app/catalog/equipment-types', 'catalog_items'],
    ['/app/catalog/items', 'catalog_items'],
    ['/app/inventory/types', 'inventory'],
    ['/app/inventory', 'inventory'],
    ['/app/catalog/supplies/types', 'inventory'],
    ['/app/catalog/supply-types', 'inventory'],
    ['/app/catalog/supplies', 'inventory'],
    ['/app/routines/types', 'design_routine_types'],
    ['/app/design/routine-types', 'design_routine_types'],
    ['/app/design/workflows', 'design_workflows'],
    ['/app/design/reports', 'design_reports'],
    ['/app/design/forms', 'design_forms'],
    ['/app/routines', 'routines'],
    ['/app/predictive', 'assets'],
    ['/app/assets', 'assets'],
    ['/app/sites', 'sites'],
    ['/app/billing', 'billing'],
    ['/app/integrations', 'integrations'],
    ['/app/insights', 'insights'],
    ['/app/audit', 'audit'],
    ['/app/dashboard', 'dashboard'],
];

export function moduleIdForPath(path: string): string | null {
    for (const [route, moduleId] of ROUTE_MODULE_PAIRS) {
        if (path === route || path.startsWith(`${route}/`)) {
            return moduleId;
        }
    }

    return null;
}

export function useModuleAccess() {
    const company = useCompanyStore();

    const modules = computed(() => company.current?.modules ?? {});

    function state(moduleId: string): ModuleAccessState {
        return (
            modules.value[moduleId] ?? {
                read: false,
                write: false,
                visible: false,
            }
        );
    }

    function isVisible(moduleId: string): boolean {
        return state(moduleId).visible;
    }

    function canWriteModule(moduleId: string): boolean {
        return state(moduleId).write;
    }

    function isRouteVisible(path: string): boolean {
        const moduleId = moduleIdForPath(path);
        if (moduleId === null) {
            return true;
        }

        return isVisible(moduleId);
    }

    return { modules, state, isVisible, canWriteModule, isRouteVisible, moduleIdForPath };
}
