export const FORM_USAGE_OPTIONS = [
    { value: 'routine', label: 'Rutina' },
    { value: 'equipment', label: 'Equipo' },
    { value: 'supply', label: 'Insumo' },
] as const;

export type FormUsageValue = (typeof FORM_USAGE_OPTIONS)[number]['value'];

export function formUsageLabel(usage: string): string {
    return FORM_USAGE_OPTIONS.find((o) => o.value === usage)?.label ?? usage;
}
