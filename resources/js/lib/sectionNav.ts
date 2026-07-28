export type SectionSubnavItem = {
    to: string;
    label: string;
    moduleId?: string;
};

export const catalogEquipmentSectionNav: SectionSubnavItem[] = [
    { to: '/app/catalog/items', label: 'Equipos', moduleId: 'catalog_items' },
    { to: '/app/catalog/items/types', label: 'Tipos de equipo', moduleId: 'catalog_items' },
];

export const catalogSuppliesSectionNav: SectionSubnavItem[] = [
    { to: '/app/catalog/supplies', label: 'Insumos', moduleId: 'catalog_supplies' },
    { to: '/app/catalog/supplies/types', label: 'Tipos de insumo', moduleId: 'catalog_supplies' },
];

export const routinesSectionNav: SectionSubnavItem[] = [
    { to: '/app/routines', label: 'Rutinas', moduleId: 'routines' },
    { to: '/app/routines/types', label: 'Tipos de rutina', moduleId: 'design_routine_types' },
];
