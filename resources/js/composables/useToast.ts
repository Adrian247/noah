import { useToastStore } from '@/stores/toast';

/** Notificaciones globales (esquina superior derecha). */
export function useToast() {
    return useToastStore();
}
