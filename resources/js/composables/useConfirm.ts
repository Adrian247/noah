import { useConfirmStore } from '@/stores/confirm';

export type ConfirmOptions = {
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    danger?: boolean;
};

export function useConfirm() {
    const store = useConfirmStore();

    return (message: string, options?: ConfirmOptions) =>
        store.ask({
            message,
            title: options?.title,
            confirmLabel: options?.confirmLabel,
            cancelLabel: options?.cancelLabel,
            danger: options?.danger,
        });
}
