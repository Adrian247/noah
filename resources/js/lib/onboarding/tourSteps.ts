import narration from '../../../onboarding/narration.es.json';

export type TourStep = {
    id: string;
    title: string;
    body: string;
    route?: string;
    target?: string;
    audioUrl: string;
    /** Voz local macOS (`afconvert`) cuando no hay ffmpeg */
    audioUrlAlt?: string;
    padding?: number;
    spotlight?: 'sidebar' | 'nav' | 'panel';
};

type TourStepDef = {
    id: string;
    moduleId?: string;
    requiresPlatformAdmin?: boolean;
    requiresAi?: boolean;
    route?: string;
    target?: string;
    padding?: number;
    spotlight?: TourStep['spotlight'];
};

const narrationById = Object.fromEntries(narration.map((row) => [row.id, row]));

function materialize(def: TourStepDef): TourStep {
    const row = narrationById[def.id];
    if (!row) {
        throw new Error(`Missing narration for tour step ${def.id}`);
    }

    return {
        id: def.id,
        title: row.title,
        body: row.text,
        route: def.route,
        target: def.target,
        padding: def.padding ?? 8,
        audioUrl: `/audio/onboarding/${def.id}.m4a`,
        audioUrlAlt: `/audio/onboarding/${def.id}.mp3`,
        spotlight: def.spotlight,
    };
}

/**
 * Definición completa del tour (se filtra por perfil al ejecutar).
 * Los pasos de módulo abren la página real para que el highlight muestre contenido legible.
 */
export const TOUR_STEP_DEFS: TourStepDef[] = [
    { id: '01-welcome' },
    { id: '02-dashboard', route: '/app/dashboard', target: '[data-tour="dashboard-cards"]', padding: 12, spotlight: 'panel' },
    { id: '03-navigation', route: '/app/dashboard', target: '[data-tour="app-sidebar"]', padding: 4, spotlight: 'sidebar' },
    {
        id: '04-workspace',
        requiresPlatformAdmin: true,
        route: '/app/dashboard',
        target: '[data-tour="sidebar-workspace"]',
        padding: 6,
        spotlight: 'sidebar',
    },
    {
        id: '10-routines',
        moduleId: 'routines',
        route: '/app/routines',
        target: '[data-tour="page-routines"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '11-assets',
        moduleId: 'assets',
        route: '/app/assets',
        target: '[data-tour="page-assets"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '12-catalog-items',
        moduleId: 'catalog_items',
        route: '/app/catalog/items',
        target: '[data-tour="page-catalog-items"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '13-catalog-supplies',
        moduleId: 'inventory',
        route: '/app/inventory',
        target: '[data-tour="page-inventory"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '14-catalog-suppliers',
        moduleId: 'catalog_suppliers',
        route: '/app/catalog/suppliers',
        target: '[data-tour="page-catalog-suppliers"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '15-clients',
        moduleId: 'clients',
        route: '/app/catalog/clients',
        target: '[data-tour="page-clients"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '16-sites',
        moduleId: 'sites',
        route: '/app/sites',
        target: '[data-tour="page-sites"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '20-routine-types',
        moduleId: 'design_routine_types',
        route: '/app/routines/types',
        target: '[data-tour="page-routine-types"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '21-design-forms',
        moduleId: 'design_forms',
        route: '/app/design/forms',
        target: '[data-tour="page-design-forms"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '22-design-reports',
        moduleId: 'design_reports',
        route: '/app/design/reports',
        target: '[data-tour="page-design-reports"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '23-design-workflows',
        moduleId: 'design_workflows',
        requiresPlatformAdmin: true,
        route: '/app/design/workflows',
        target: '[data-tour="page-design-workflows"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '30-billing',
        moduleId: 'billing',
        route: '/app/billing',
        target: '[data-tour="page-billing"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '32-integrations',
        moduleId: 'integrations',
        route: '/app/integrations',
        target: '[data-tour="page-integrations"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '33-settings',
        route: '/app/settings',
        target: '[data-tour="page-settings"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '34-assistant',
        requiresAi: true,
        route: '/app/dashboard',
        target: '[data-tour="assistant-fab"]',
        padding: 12,
        spotlight: 'panel',
    },
    {
        id: '40-audit',
        moduleId: 'audit',
        route: '/app/audit',
        target: '[data-tour="page-audit"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '41-company-users',
        moduleId: 'company_users',
        route: '/app/admin/users',
        target: '[data-tour="page-company-users"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '42-platform-roles',
        requiresPlatformAdmin: true,
        route: '/app/platform/role-permissions',
        target: '[data-tour="page-platform-roles"]',
        padding: 10,
        spotlight: 'panel',
    },
    {
        id: '43-platform-tenants',
        requiresPlatformAdmin: true,
        route: '/app/platform/tenants',
        target: '[data-tour="page-platform-tenants"]',
        padding: 10,
        spotlight: 'panel',
    },
    { id: '99-finish', route: '/app/dashboard' },
];

export function buildProductTourSteps(
    isModuleVisible: (moduleId: string) => boolean,
    isPlatformAdmin: boolean,
    canUseAi = false,
): TourStep[] {
    return TOUR_STEP_DEFS.filter((def) => {
        if (def.requiresPlatformAdmin && !isPlatformAdmin) {
            return false;
        }
        if (def.requiresAi && !canUseAi) {
            return false;
        }
        if (def.moduleId && !isModuleVisible(def.moduleId)) {
            return false;
        }
        return true;
    }).map(materialize);
}

/** @deprecated usar buildProductTourSteps; se mantiene para tests estáticos. */
export const productTourSteps = buildProductTourSteps(() => true, true, true);

/** v6: highlight con cutout + pasos en páginas reales. */
export const PRODUCT_TOUR_STORAGE_KEY = 'phoenix_product_tour_v6_completed';
