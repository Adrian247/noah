export type AppTheme = 'dark' | 'light';

const STORAGE_KEY = 'phoenix-theme';

export function getStoredTheme(): AppTheme {
    if (typeof localStorage === 'undefined') {
        return 'dark';
    }
    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'light' ? 'light' : 'dark';
}

export function applyTheme(theme: AppTheme): void {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem(STORAGE_KEY, theme);
}

/** Login siempre oscuro; no modifica la preferencia guardada de la app. */
export function applyLoginTheme(): void {
    document.documentElement.dataset.theme = 'dark';
}

export function applyStoredThemeForApp(): void {
    document.documentElement.dataset.theme = getStoredTheme();
}

export function initTheme(): void {
    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    if (path === '/login') {
        applyLoginTheme();
        return;
    }
    applyStoredThemeForApp();
}
