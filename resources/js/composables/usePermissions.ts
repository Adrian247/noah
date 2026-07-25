import { computed } from 'vue';
import { useCompanyStore } from '@/stores/company';

export function usePermissions() {
    const company = useCompanyStore();

    const permissions = computed(() => company.current?.permissions ?? []);

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    return { permissions, can };
}
