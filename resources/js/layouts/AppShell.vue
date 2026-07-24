<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { getToken } from '@/api/client';

const auth = useAuthStore();
const company = useCompanyStore();
const router = useRouter();

const companyName = computed(() => company.current?.name ?? 'Empresa');

onMounted(async () => {
    if (getToken() && !auth.user) {
        await auth.fetchMe();
        company.hydrate(auth.companies);
    }
});

async function onCompanyChange(event: Event) {
    const id = Number((event.target as HTMLSelectElement).value);
    const found = auth.companies.find((c) => c.id === id);
    if (found) {
        company.select(found);
        await router.go(0);
    }
}

async function logout() {
    await auth.logout();
    company.clear();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 text-slate-900">
        <aside class="w-56 shrink-0 border-r border-slate-200 bg-white p-4">
            <p class="text-lg font-semibold tracking-tight">Noah</p>
            <p class="mt-1 text-xs text-slate-500">{{ companyName }}</p>
            <nav class="mt-6 flex flex-col gap-1 text-sm">
                <RouterLink
                    class="rounded-md px-3 py-2 hover:bg-slate-100"
                    active-class="bg-slate-100 font-medium"
                    to="/app/dashboard"
                >
                    Dashboard
                </RouterLink>
                <RouterLink
                    class="rounded-md px-3 py-2 hover:bg-slate-100"
                    active-class="bg-slate-100 font-medium"
                    to="/app/routines"
                >
                    Rutinas
                </RouterLink>
            </nav>
        </aside>
        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3"
            >
                <h1 class="text-base font-medium">{{ auth.user?.name }}</h1>
                <div class="flex items-center gap-3">
                    <select
                        v-if="auth.companies.length"
                        class="rounded-md border border-slate-300 px-2 py-1 text-sm"
                        :value="company.current?.id"
                        @change="onCompanyChange"
                    >
                        <option v-for="c in auth.companies" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="text-sm text-slate-600 hover:text-slate-900"
                        @click="logout"
                    >
                        Salir
                    </button>
                </div>
            </header>
            <main class="flex-1 p-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>
