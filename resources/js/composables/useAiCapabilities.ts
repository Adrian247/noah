import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useCompanyStore } from '@/stores/company';

/** Capacidad de IA operativa (asistente FAB + paneles contextuales). */
export function useAiCapabilities() {
    const { can } = usePermissions();
    const { isVisible } = useModuleAccess();
    const company = useCompanyStore();

    const canUseAi = computed(
        () =>
            isVisible('insights') &&
            can('insights.use') &&
            company.current?.ai_enabled !== false,
    );

    return { canUseAi };
}
