import { ref } from 'vue';
import { api } from '@/api/client';
import type { FormDesignSettings, OptionCatalog, FormSection } from '@/components/domain/DynamicFormRenderer';

export type CatalogFormCapturePayload = {
    configured: boolean;
    message?: string;
    form?: { id: number; name: string };
    schema?: { sections?: FormSection[] };
    form_design?: {
        settings: FormDesignSettings;
        option_catalogs: OptionCatalog[];
    };
};

export type CatalogFormCaptureState = {
    loading: boolean;
    configured: boolean;
    message: string;
    formName: string;
    schema: { sections: FormSection[] } | null;
    formSettings: FormDesignSettings | null;
    optionCatalogs: OptionCatalog[];
};

function emptyState(): CatalogFormCaptureState {
    return {
        loading: false,
        configured: false,
        message: '',
        formName: '',
        schema: null,
        formSettings: null,
        optionCatalogs: [],
    };
}

export function useCatalogTypeFormCapture(capturePath: (typeId: number) => string) {
    const capture = ref<CatalogFormCaptureState>(emptyState());

    async function loadForType(typeId: string | number, resetResponses?: () => void) {
        const id = Number(typeId);
        if (!id) {
            capture.value = emptyState();
            return;
        }

        capture.value = { ...emptyState(), loading: true };
        try {
            const res = await api<{ data: CatalogFormCapturePayload }>(capturePath(id));
            const data = res.data;
            if (!data.configured) {
                capture.value = {
                    ...emptyState(),
                    message: data.message ?? 'Falta asignar un formulario al tipo seleccionado.',
                };
                resetResponses?.();
                return;
            }

            capture.value = {
                loading: false,
                configured: true,
                message: '',
                formName: data.form?.name ?? 'Formulario',
                schema: { sections: data.schema?.sections ?? [] },
                formSettings: data.form_design?.settings ?? null,
                optionCatalogs: data.form_design?.option_catalogs ?? [],
            };
        } catch (e) {
            capture.value = {
                ...emptyState(),
                message: (e as Error).message,
            };
        }
    }

    function reset() {
        capture.value = emptyState();
    }

    return { capture, loadForType, reset };
}
