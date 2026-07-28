import { computed, ref } from 'vue';
import { buildProductTourSteps, PRODUCT_TOUR_STORAGE_KEY } from '@/lib/onboarding/tourSteps';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';

const active = ref(false);
const stepIndex = ref(0);
const muted = ref(false);

export function useProductTour() {
    const company = useCompanyStore();
    const auth = useAuthStore();

    const steps = computed(() => {
        const modules = company.current?.modules ?? {};
        const isVisible = (moduleId: string) => modules[moduleId]?.visible ?? false;

        return buildProductTourSteps(isVisible, auth.user?.is_platform_admin ?? false);
    });

    const currentStep = computed(() => steps.value[stepIndex.value] ?? null);
    const isFirst = computed(() => stepIndex.value <= 0);
    const isLast = computed(() => stepIndex.value >= Math.max(0, steps.value.length - 1));
    const progressLabel = computed(() => {
        const total = steps.value.length;
        if (total === 0) {
            return '0 / 0';
        }
        return `${stepIndex.value + 1} / ${total}`;
    });

    function hasCompleted(): boolean {
        try {
            return localStorage.getItem(PRODUCT_TOUR_STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    }

    function markCompleted() {
        try {
            localStorage.setItem(PRODUCT_TOUR_STORAGE_KEY, '1');
        } catch {
            /* ignore */
        }
    }

    function clearCompleted() {
        try {
            localStorage.removeItem(PRODUCT_TOUR_STORAGE_KEY);
        } catch {
            /* ignore */
        }
    }

    function start(from = 0) {
        const total = steps.value.length;
        if (total === 0) {
            return;
        }
        stepIndex.value = Math.max(0, Math.min(from, total - 1));
        active.value = true;
    }

    function stop(markDone = true) {
        active.value = false;
        if (markDone) {
            markCompleted();
        }
    }

    function next() {
        if (steps.value.length === 0) {
            stop(true);
            return;
        }
        if (stepIndex.value >= steps.value.length - 1) {
            stop(true);
            return;
        }
        stepIndex.value += 1;
    }

    function prev() {
        if (!isFirst.value) {
            stepIndex.value -= 1;
        }
    }

    function skip() {
        stop(true);
    }

    function toggleMute() {
        muted.value = !muted.value;
    }

    return {
        active,
        stepIndex,
        muted,
        steps,
        currentStep,
        isFirst,
        isLast,
        progressLabel,
        hasCompleted,
        markCompleted,
        clearCompleted,
        start,
        stop,
        next,
        prev,
        skip,
        toggleMute,
    };
}
