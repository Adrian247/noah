import { ref } from 'vue';

const STORAGE_KEY = 'noah_sidebar_collapsed';

export function useSidebarCollapsed() {
    const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1');

    function toggle() {
        collapsed.value = !collapsed.value;
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    }

    return { collapsed, toggle };
}
