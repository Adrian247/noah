import { defineStore } from 'pinia';
import { ref } from 'vue';

export type ConfirmRequest = {
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    danger: boolean;
};

export const useConfirmStore = defineStore('confirm', () => {
    const request = ref<ConfirmRequest | null>(null);
    let resolvePending: ((value: boolean) => void) | null = null;

    function settle(value: boolean) {
        resolvePending?.(value);
        resolvePending = null;
        request.value = null;
    }

    function ask(partial: Partial<ConfirmRequest> & { message: string }): Promise<boolean> {
        if (request.value) {
            settle(false);
        }

        return new Promise((resolve) => {
            resolvePending = resolve;
            request.value = {
                title: partial.title ?? '¿Confirmar acción?',
                message: partial.message,
                confirmLabel: partial.confirmLabel ?? 'Confirmar',
                cancelLabel: partial.cancelLabel ?? 'Cancelar',
                danger: partial.danger ?? false,
            };
        });
    }

    function confirm() {
        settle(true);
    }

    function cancel() {
        settle(false);
    }

    return { request, ask, confirm, cancel };
});
