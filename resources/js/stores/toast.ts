import { defineStore } from 'pinia';
import { ref } from 'vue';

export type ToastVariant = 'success' | 'danger' | 'warning' | 'info';

export type ToastItem = {
    id: number;
    message: string;
    variant: ToastVariant;
};

let nextId = 0;

export const useToastStore = defineStore('toast', () => {
    const items = ref<ToastItem[]>([]);

    function dismiss(id: number) {
        items.value = items.value.filter((t) => t.id !== id);
    }

    function push(message: string, variant: ToastVariant = 'info', durationMs = 12_000) {
        const trimmed = message.trim();
        if (!trimmed) {
            return;
        }
        const id = ++nextId;
        items.value.push({ id, message: trimmed, variant });
        if (durationMs > 0) {
            window.setTimeout(() => dismiss(id), durationMs);
        }
    }

    function success(message: string, durationMs?: number) {
        push(message, 'success', durationMs);
    }

    function error(message: string, durationMs?: number) {
        push(message, 'danger', durationMs ?? 14_000);
    }

    function warning(message: string, durationMs?: number) {
        push(message, 'warning', durationMs);
    }

    function info(message: string, durationMs?: number) {
        push(message, 'info', durationMs);
    }

    return { items, push, dismiss, success, error, warning, info };
});
