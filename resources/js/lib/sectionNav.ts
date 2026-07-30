export type SectionSubnavItem = {
    to: string;
    label: string;
    moduleId?: string;
    permission?: string;
};

export const catalogEquipmentSectionNav: SectionSubnavItem[] = [
    { to: '/app/catalog/items', label: 'Equipos', moduleId: 'catalog_items' },
    { to: '/app/catalog/items/types', label: 'Tipos de equipo', moduleId: 'catalog_items' },
];

export const inventorySectionNav: SectionSubnavItem[] = [
    { to: '/app/inventory', label: 'Artículos', moduleId: 'inventory' },
    { to: '/app/inventory/types', label: 'Tipos de insumo', moduleId: 'inventory' },
];

/** @deprecated use inventorySectionNav */
export const catalogSuppliesSectionNav: SectionSubnavItem[] = inventorySectionNav;

export const routinesSectionNav: SectionSubnavItem[] = [
    { to: '/app/routines', label: 'Rutinas', moduleId: 'routines' },
    { to: '/app/validation', label: 'Validación', moduleId: 'routines', permission: 'routines.validate' },
    { to: '/app/routines/types', label: 'Tipos de rutina', moduleId: 'design_routine_types' },
];
