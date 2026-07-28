<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';

const auth = useAuthStore();
const company = useCompanyStore();
const router = useRouter();
const loading = ref(true);

onMounted(async () => {
    try {
        const ok = await auth.ensureSession();
        if (!ok) {
            await router.replace({ name: 'login' });
            return;
        }
        company.hydrate(auth.companies);
    } finally {
        loading.value = false;
    }
});

const companyName = computed(
    () => auth.companies.find((c) => c.role === 'client')?.name ?? 'Portal cliente',
);

async function logout() {
    loading.value = true;
    try {
        await auth.logout();
        company.clear();
    } finally {
        await router.replace({ name: 'login' });
        loading.value = false;
    }
}
</script>

<template>
    <div class="min-h-dvh bg-slate-200 text-slate-900">
        <header class="border-b border-slate-300 bg-white px-6 py-4 shadow-sm">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Noah</p>
                    <h1 class="text-lg font-semibold">{{ companyName }}</h1>
                </div>
                <nav class="flex gap-4 text-sm font-medium">
                    <RouterLink class="text-amber-800 underline" to="/portal/invoices">Facturas</RouterLink>
                    <RouterLink class="text-amber-800 underline" to="/portal/routines">Rutinas</RouterLink>
                    <button type="button" class="text-slate-600 underline" @click="logout">Salir</button>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-5xl px-6 py-8">
            <p v-if="loading" class="text-slate-600">Cargando…</p>
            <router-view v-else />
        </main>
    </div>
</template>
