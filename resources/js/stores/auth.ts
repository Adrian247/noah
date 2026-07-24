import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api, clearToken, setToken, type ApiError } from '@/api/client';

export type CompanyOption = { id: number; name: string; role: string };

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(null);
    const user = ref<{ id: number; name: string; email: string } | null>(null);
    const companies = ref<CompanyOption[]>([]);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => Boolean(token.value));

    async function login(email: string, password: string) {
        error.value = null;
        try {
            const data = await api<{
                token: string;
                user: { id: number; name: string; email: string };
                companies: CompanyOption[];
            }>('/auth/login', {
                method: 'POST',
                body: JSON.stringify({ email, password, device_name: 'noah-web' }),
            });
            token.value = data.token;
            setToken(data.token);
            user.value = data.user;
            companies.value = data.companies;
        } catch (e) {
            const err = e as ApiError;
            error.value = err.message;
            throw e;
        }
    }

    async function fetchMe() {
        const data = await api<{
            user: { id: number; name: string; email: string };
            companies: CompanyOption[];
        }>('/auth/me');
        user.value = data.user;
        companies.value = data.companies;
    }

    async function logout() {
        try {
            await api('/auth/logout', { method: 'POST' });
        } finally {
            token.value = null;
            user.value = null;
            companies.value = [];
            clearToken();
        }
    }

    return {
        token,
        user,
        companies,
        error,
        isAuthenticated,
        login,
        fetchMe,
        logout,
    };
});
