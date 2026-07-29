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
        audioUrl: `/audio/onboarding/${def.id}.mp3`,
        audioUrlAlt: `/audio/onboarding/${def.id}.m4a`,
        spotlight: def.spotlight,
    };
}

/** Definición completa del tour (se filtra por perfil al ejecutar). */
export const TOUR_STEP_DEFS: TourStepDef[] = [
    { id: '01-welcome' },
    { id: '02-dashboard', route: '/app/dashboard', target: '[data-tour="dashboard-cards"]', spotlight: 'panel' },
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
        route: '/app/dashboard',
        target: '[data-tour="nav-routines"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '11-assets',
        moduleId: 'assets',
        route: '/app/dashboard',
        target: '[data-tour="nav-assets"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '12-catalog-items',
        moduleId: 'catalog_items',
        route: '/app/dashboard',
        target: '[data-tour="nav-catalog-items"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '13-catalog-supplies',
        moduleId: 'inventory',
        route: '/app/dashboard',
        target: '[data-tour="nav-inventory"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '14-catalog-suppliers',
        moduleId: 'catalog_suppliers',
        route: '/app/dashboard',
        target: '[data-tour="nav-catalog-suppliers"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '15-clients',
        moduleId: 'clients',
        route: '/app/dashboard',
        target: '[data-tour="nav-clients"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '16-sites',
        moduleId: 'sites',
        route: '/app/dashboard',
        target: '[data-tour="nav-sites"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '20-routine-types',
        moduleId: 'design_routine_types',
        route: '/app/routines/types',
        target: '[data-tour="page-routine-types"]',
        spotlight: 'panel',
    },
    {
        id: '21-design-forms',
        moduleId: 'design_forms',
        route: '/app/dashboard',
        target: '[data-tour="nav-design-forms"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '22-design-reports',
        moduleId: 'design_reports',
        route: '/app/dashboard',
        target: '[data-tour="nav-design-reports"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '23-design-workflows',
        moduleId: 'design_workflows',
        requiresPlatformAdmin: true,
        route: '/app/dashboard',
        target: '[data-tour="nav-design-workflows"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '30-billing',
        moduleId: 'billing',
        route: '/app/dashboard',
        target: '[data-tour="nav-billing"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '40-audit',
        moduleId: 'audit',
        route: '/app/dashboard',
        target: '[data-tour="nav-audit"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '41-company-users',
        moduleId: 'company_users',
        route: '/app/dashboard',
        target: '[data-tour="nav-company-users"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '42-platform-roles',
        requiresPlatformAdmin: true,
        route: '/app/dashboard',
        target: '[data-tour="nav-platform-roles"]',
        padding: 6,
        spotlight: 'nav',
    },
    {
        id: '43-platform-tenants',
        requiresPlatformAdmin: true,
        route: '/app/dashboard',
        target: '[data-tour="nav-platform-tenants"]',
        padding: 6,
        spotlight: 'nav',
    },
    { id: '99-finish', route: '/app/dashboard' },
];

export function buildProductTourSteps(
    isModuleVisible: (moduleId: string) => boolean,
    isPlatformAdmin: boolean,
): TourStep[] {
    return TOUR_STEP_DEFS.filter((def) => {
        if (def.requiresPlatformAdmin && !isPlatformAdmin) {
            return false;
        }
        if (def.moduleId && !isModuleVisible(def.moduleId)) {
            return false;
        }
        return true;
    }).map(materialize);
}

/** @deprecated usar buildProductTourSteps; se mantiene para tests estáticos. */
export const productTourSteps = buildProductTourSteps(() => true, true);

export const PRODUCT_TOUR_STORAGE_KEY = 'phoenix_product_tour_v3_completed';
