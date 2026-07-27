import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api, clearToken, setToken, getToken, type ApiError } from '@/api/client';

export type ModuleAccessState = { read: boolean; write: boolean; visible: boolean };

export type CompanyOption = {
    id: number;
    name: string;
    role: string;
    permissions?: string[];
    modules?: Record<string, ModuleAccessState>;
};

export type AuthUser = { id: number; name: string; email: string; avatar_url?: string | null };

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(null);
    const user = ref<AuthUser | null>(null);
    const companies = ref<CompanyOption[]>([]);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => Boolean(token.value));

    async function login(email: string, password: string) {
        error.value = null;
        const normalizedEmail = email.trim().toLowerCase();
        try {
            const data = await api<{
                token: string;
                user: AuthUser;
                companies: CompanyOption[];
            }>('/auth/login', {
                method: 'POST',
                body: JSON.stringify({ email: normalizedEmail, password, device_name: 'noah-web' }),
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
            user: AuthUser;
            companies: CompanyOption[];
        }>('/auth/me');
        user.value = data.user;
        companies.value = data.companies;
    }

    async function uploadAvatar(file: File) {
        const form = new FormData();
        form.append('avatar', file);
        const token = getToken();
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        const res = await fetch('/api/v1/auth/avatar', { method: 'POST', headers, body: form });
        const text = await res.text();
        const data = text ? JSON.parse(text) : null;
        if (!res.ok) {
            throw new Error(data?.message ?? res.statusText);
        }
        user.value = data.user as AuthUser;
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
        uploadAvatar,
        logout,
    };
});
