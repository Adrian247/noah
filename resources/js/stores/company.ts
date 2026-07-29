import { defineStore } from 'pinia';
import { ref } from 'vue';
import { setCompanyId, clearCompanyId } from '@/api/client';
import type { CompanyOption } from '@/stores/auth';
import { preferredCompany } from '@/lib/sessionCompany';

export const useCompanyStore = defineStore('company', () => {
    const current = ref<CompanyOption | null>(null);

    function hydrate(companies: CompanyOption[]) {
        if (companies.length === 0) {
            clear();
            return;
        }
        select(preferredCompany(companies)!);
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
