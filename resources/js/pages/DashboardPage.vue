<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useCompanyStore } from '@/stores/company';
import { api } from '@/api/client';

const company = useCompanyStore();
const apiOk = ref(false);
const routineCount = ref<number | null>(null);

onMounted(async () => {
    try {
        const health = await fetch('/api/v1/health').then((r) => r.json());
        apiOk.value = health.status === 'ok';
    } catch {
        apiOk.value = false;
    }

    if (!company.current) {
        return;
    }

    try {
        const res = await api<{ total: number }>('/routines?per_page=1');
        routineCount.value = res.total;
    } catch {
        routineCount.value = null;
    }
});
</script>

<template>
    <div class="max-w-2xl space-y-4">
        <h2 class="text-xl font-semibold">Dashboard</h2>
        <p class="text-slate-600">
            Empresa activa:
            <strong>{{ company.current?.name ?? '—' }}</strong>
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">API</p>
                <p class="font-medium">{{ apiOk ? 'Operativa' : 'No disponible' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <p class="text-sm text-slate-500">Rutinas (total)</p>
                <p class="font-medium">{{ routineCount ?? '—' }}</p>
            </div>
        </div>
    </div>
</template>
