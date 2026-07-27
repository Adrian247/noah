import { computed, ref } from 'vue';
import { applyTheme, getStoredTheme, type AppTheme } from '@/lib/theme';

const theme = ref<AppTheme>(getStoredTheme());

export function useTheme() {
    const isDark = computed(() => theme.value === 'dark');

    function setTheme(next: AppTheme) {
        theme.value = next;
        applyTheme(next);
    }

    function toggleTheme() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return { theme, isDark, setTheme, toggleTheme };
}

export type { AppTheme };
