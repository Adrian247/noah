import type { FormField, FormSection } from '@/components/domain/DynamicFormRenderer';

function isEmptyValue(field: FormField, value: unknown): boolean {
    if (value === undefined || value === null || value === '') {
        return true;
    }
    if (field.type === 'photo') {
        if (typeof value === 'string') {
            return value.trim() === '';
        }
        if (Array.isArray(value)) {
            return value.length === 0;
        }
        if (typeof value === 'object' && value !== null && 'path' in value) {
            return String((value as { path?: string }).path ?? '').trim() === '';
        }
        return true;
    }
    return false;
}

export type RequiredFieldValidation = {
    keys: string[];
    labels: string[];
};

export function validateRequiredFields(
    schema: { sections?: FormSection[] } | null | undefined,
    responses: Record<string, unknown>,
): RequiredFieldValidation {
    const keys: string[] = [];
    const labels: string[] = [];
    for (const section of schema?.sections ?? []) {
        for (const field of section.fields ?? []) {
            if (!field.required) {
                continue;
            }
            if (isEmptyValue(field, responses[field.key])) {
                keys.push(field.key);
                labels.push(field.label || field.key);
            }
        }
    }
    return { keys, labels };
}

/** @deprecated Use validateRequiredFields */
export function missingRequiredFields(
    schema: { sections?: FormSection[] } | null | undefined,
    responses: Record<string, unknown>,
): string[] {
    return validateRequiredFields(schema, responses).labels;
}
