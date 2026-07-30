import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useNotificationPreferencesStore } from '@/stores/notificationPreferences';

export type ToastVariant = 'success' | 'danger' | 'warning' | 'info';

export type ToastItem = {
    id: number;
    message: string;
    variant: ToastVariant;
};

let nextId = 0;
const dismissTimers = new Map<number, ReturnType<typeof setTimeout>>();

export const useToastStore = defineStore('toast', () => {
    const items = ref<ToastItem[]>([]);

    function dismiss(id: number) {
        const timer = dismissTimers.get(id);
        if (timer !== undefined) {
            window.clearTimeout(timer);
            dismissTimers.delete(id);
        }
        items.value = items.value.filter((t) => t.id !== id);
    }

    function resolveDurationMs(explicit?: number): number {
        if (explicit !== undefined) {
            return explicit;
        }
        return useNotificationPreferencesStore().autoCloseDurationMs;
    }

    function push(message: string, variant: ToastVariant = 'info', durationMs?: number) {
        const trimmed = message.trim();
        if (!trimmed) {
            return;
        }
        const id = ++nextId;
        items.value.push({ id, message: trimmed, variant });
        useNotificationPreferencesStore().playSound(variant);
        const resolved = resolveDurationMs(durationMs);
        if (resolved > 0) {
            const timer = window.setTimeout(() => dismiss(id), resolved);
            dismissTimers.set(id, timer);
        }
    }

    function success(message: string, durationMs?: number) {
        push(message, 'success', durationMs);
    }

    function error(message: string, durationMs?: number) {
        push(message, 'danger', durationMs);
    }

    function warning(message: string, durationMs?: number) {
        push(message, 'warning', durationMs);
    }

    function info(message: string, durationMs?: number) {
        push(message, 'info', durationMs);
    }

    return { items, push, dismiss, success, error, warning, info };
});
