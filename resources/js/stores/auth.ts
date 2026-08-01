import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
    api,
    clearToken,
    clearCompanyId,
    setToken,
    getToken,
    type ApiError,
} from '@/api/client';

export type ModuleAccessState = { read: boolean; write: boolean; visible: boolean };

export type CompanyOption = {
    id: number;
    name: string;
    role: string;
    client_id?: number | null;
    assumed?: boolean;
    company_is_active?: boolean;
    /** Usuario de facturación activo en esta empresa (para mensajes de solo lectura). */
    billing_contact_email?: string | null;
    ai_enabled?: boolean;
    permissions?: string[];
    modules?: Record<string, ModuleAccessState>;
};

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    avatar_url?: string | null;
    is_platform_admin?: boolean;
};

export const useAuthStore = defineStore('auth', () => {
    const token = ref<string | null>(getToken());
    const user = ref<AuthUser | null>(null);
    const companies = ref<CompanyOption[]>([]);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => Boolean(getToken()));

    function clearLocalSession(): void {
        token.value = null;
        user.value = null;
        companies.value = [];
        clearToken();
        clearCompanyId();
    }

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
                body: JSON.stringify({ email: normalizedEmail, password, device_name: 'phoenix-web' }),
            });
            token.value = data.token;
            setToken(data.token);
            clearCompanyId();
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

    /** Validates stored token; clears local session on 401. */
    async function ensureSession(): Promise<boolean> {
        const stored = getToken();
        if (!stored) {
            clearLocalSession();
            return false;
        }
        token.value = stored;
        if (user.value) {
            return true;
        }
        try {
            await fetchMe();
            return true;
        } catch (e) {
            const err = e as ApiError;
            if (err.status === 401 || err.status === 403) {
                clearLocalSession();
            }
            return false;
        }
    }

    async function uploadAvatar(file: File) {
        const form = new FormData();
        form.append('avatar', file);
        const bearer = getToken();
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (bearer) {
            headers.Authorization = `Bearer ${bearer}`;
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
        } catch {
            // Token may already be invalid; still clear local session.
        } finally {
            clearLocalSession();
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
        ensureSession,
        clearLocalSession,
        uploadAvatar,
        logout,
    };
});
