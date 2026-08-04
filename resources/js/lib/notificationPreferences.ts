import type { ToastVariant } from '@/stores/toast';

export type ToastPosition =
    | 'top-right'
    | 'top-left'
    | 'top-center'
    | 'bottom-right'
    | 'bottom-left';

export type NotificationPreferences = {
    position: ToastPosition;
    soundEnabled: boolean;
    autoCloseEnabled: boolean;
    /** Segundos visibles antes de cerrar (solo si autoCloseEnabled). */
    autoCloseSeconds: number;
};

/** Rango recomendado: 3 s (aviso rápido) — 30 s (mensajes largos). */
export const AUTO_CLOSE_DURATION_MIN_SEC = 3;
export const AUTO_CLOSE_DURATION_MAX_SEC = 30;
export const AUTO_CLOSE_DURATION_DEFAULT_SEC = 6;
export const AUTO_CLOSE_DURATION_STEP_SEC = 1;

export const NOTIFICATION_POSITIONS: {
    id: ToastPosition;
    label: string;
}[] = [
    { id: 'top-right', label: 'Arriba derecha' },
    { id: 'top-left', label: 'Arriba izquierda' },
    { id: 'top-center', label: 'Arriba centro' },
    { id: 'bottom-right', label: 'Abajo derecha' },
    { id: 'bottom-left', label: 'Abajo izquierda' },
];

export const NOTIFICATION_SOUND_URLS: Record<ToastVariant, string> = {
    // ?v=2 fuerza recarga tras regenerar clips más suaves.
    success: '/audio/notifications/success.mp3?v=2',
    danger: '/audio/notifications/danger.mp3?v=2',
    warning: '/audio/notifications/warning.mp3?v=2',
    info: '/audio/notifications/info.mp3?v=2',
};

const STORAGE_KEY = 'phoenix_notification_prefs';

const defaults: NotificationPreferences = {
    position: 'top-right',
    soundEnabled: true,
    autoCloseEnabled: true,
    autoCloseSeconds: AUTO_CLOSE_DURATION_DEFAULT_SEC,
};

export function clampAutoCloseSeconds(value: unknown): number {
    const n =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.round(value)
            : AUTO_CLOSE_DURATION_DEFAULT_SEC;
    return Math.min(AUTO_CLOSE_DURATION_MAX_SEC, Math.max(AUTO_CLOSE_DURATION_MIN_SEC, n));
}

export function notificationEntryAxis(position: ToastPosition): 'from-right' | 'from-left' | 'from-top' {
    switch (position) {
        case 'top-right':
        case 'bottom-right':
            return 'from-right';
        case 'top-left':
        case 'bottom-left':
            return 'from-left';
        case 'top-center':
            return 'from-top';
    }
}

export function loadNotificationPreferences(): NotificationPreferences {
    if (typeof window === 'undefined') {
        return { ...defaults };
    }
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return { ...defaults };
        }
        const parsed = JSON.parse(raw) as Partial<NotificationPreferences>;
        const position = NOTIFICATION_POSITIONS.some((p) => p.id === parsed.position)
            ? (parsed.position as ToastPosition)
            : defaults.position;
        return {
            position,
            soundEnabled: parsed.soundEnabled ?? defaults.soundEnabled,
            autoCloseEnabled: parsed.autoCloseEnabled ?? defaults.autoCloseEnabled,
            autoCloseSeconds: clampAutoCloseSeconds(parsed.autoCloseSeconds),
        };
    } catch {
        return { ...defaults };
    }
}

export function saveNotificationPreferences(prefs: NotificationPreferences): void {
    if (typeof window === 'undefined') {
        return;
    }
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
}
