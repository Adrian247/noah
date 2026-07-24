import { defineStore } from 'pinia';
import { ref } from 'vue';
import { getCompanyId, setCompanyId, clearCompanyId } from '@/api/client';
import type { CompanyOption } from '@/stores/auth';

export const useCompanyStore = defineStore('company', () => {
    const current = ref<CompanyOption | null>(null);

    function hydrate(companies: CompanyOption[]) {
        const saved = getCompanyId();
        const found = saved
            ? companies.find((c) => String(c.id) === saved)
            : companies[0];
        if (found) {
            select(found);
        }
    }

    function select(company: CompanyOption) {
        current.value = company;
        setCompanyId(company.id);
    }

    function clear() {
        current.value = null;
        clearCompanyId();
    }

    return { current, hydrate, select, clear };
});
