export const FORM_USAGE_OPTIONS = [
    { value: 'service', label: 'Servicio' },
    { value: 'article', label: 'Artículo' },
    { value: 'inventory', label: 'Inventario' },
] as const;

export type FormUsageValue = (typeof FORM_USAGE_OPTIONS)[number]['value'];

const LEGACY_LABELS: Record<string, string> = {
    routine: 'Servicio',
    equipment: 'Artículo',
    supply: 'Inventario',
};

export function formUsageLabel(usage: string): string {
    return FORM_USAGE_OPTIONS.find((o) => o.value === usage)?.label ?? LEGACY_LABELS[usage] ?? usage;
}

export const FORM_USAGE_SECTION_ORDER_KEY = 'phoenix-form-usage-section-order';

export function defaultFormUsageOrder(): FormUsageValue[] {
    return ['article', 'inventory', 'service'];
}
