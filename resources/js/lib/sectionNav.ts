export type SectionSubnavItem = {
    to: string;
    label: string;
    moduleId?: string;
    permission?: string;
};

export const catalogEquipmentSectionNav: SectionSubnavItem[] = [
    { to: '/app/catalog/items', label: 'Artículos', moduleId: 'catalog_items' },
    { to: '/app/catalog/items/types', label: 'Tipos de artículo', moduleId: 'catalog_items' },
];

export const inventorySectionNav: SectionSubnavItem[] = [
    { to: '/app/inventory', label: 'Artículos', moduleId: 'inventory' },
    { to: '/app/inventory/types', label: 'Tipos de artículo', moduleId: 'inventory' },
];

/** @deprecated use inventorySectionNav */
export const catalogSuppliesSectionNav: SectionSubnavItem[] = inventorySectionNav;

export const routinesSectionNav: SectionSubnavItem[] = [
    { to: '/app/routines', label: 'Servicios', moduleId: 'routines' },
    { to: '/app/validation', label: 'Validación', moduleId: 'routines', permission: 'routines.validate' },
    { to: '/app/routines/types', label: 'Tipos de servicio', moduleId: 'design_routine_types' },
];

export const clientsSectionNav: SectionSubnavItem[] = [
    { to: '/app/catalog/clients', label: 'Clientes', moduleId: 'clients' },
];

export const integrationsSectionNav: SectionSubnavItem[] = [
    { to: '/app/integrations/webhooks', label: 'Webhooks', moduleId: 'integrations' },
    { to: '/app/integrations/automation', label: 'Automatización', moduleId: 'integrations' },
    { to: '/app/integrations/mcp', label: 'MCP', moduleId: 'integrations' },
];

export function clientDetailSectionNav(clientId: number): SectionSubnavItem[] {
    return [
        { to: '/app/catalog/clients', label: 'Lista', moduleId: 'clients' },
        { to: `/app/catalog/clients/${clientId}/sites`, label: 'Sitios', moduleId: 'clients' },
        { to: `/app/catalog/clients/${clientId}/inventory`, label: 'Inventario', moduleId: 'clients' },
    ];
}

export const SERVICE_CATEGORY_LABELS: Record<string, string> = {
    installation: 'Instalación',
    manufacturing: 'Fabricación',
    maintenance: 'Mantenimiento',
    fabrication: 'Fabricación',
    supply: 'Fabricación',
};
