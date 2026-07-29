import { ref } from 'vue';

const STORAGE_KEY = 'phoenix_sidebar_collapsed';
const ANIM_MS = 560;

export type SidebarCollapsePhase = 'idle' | 'collapsing' | 'expanding';

export function useSidebarCollapsed() {
    const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1');
    const animating = ref(false);
    const phase = ref<SidebarCollapsePhase>('idle');

    function toggle() {
        const nextCollapsed = !collapsed.value;
        phase.value = nextCollapsed ? 'collapsing' : 'expanding';
        animating.value = true;
        collapsed.value = nextCollapsed;
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
        window.setTimeout(() => {
            animating.value = false;
            phase.value = 'idle';
        }, ANIM_MS);
    }

    return { collapsed, animating, phase, toggle, animMs: ANIM_MS };
}
