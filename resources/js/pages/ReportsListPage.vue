<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';

type ReportRow = {
    id: number;
    name: string;
    slug: string;
    latest_version?: { version: number; status: string } | null;
};

const reports = ref<ReportRow[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const name = ref('');

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: ReportRow[] }>('/design/reports');
        reports.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function createReport() {
    if (!name.value.trim()) {
        return;
    }
    try {
        await api('/design/reports', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim() }),
        });
        name.value = '';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Plantillas de reporte</h2>
        <form class="flex flex-wrap gap-2" @submit.prevent="createReport">
            <input
                v-model="name"
                placeholder="Nombre de plantilla"
                class="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
            <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white">
                Crear
            </button>
        </form>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <ul v-else class="divide-y rounded-lg border bg-white">
            <li v-for="r in reports" :key="r.id" class="flex justify-between px-4 py-3 text-sm">
                <RouterLink class="font-medium underline" :to="`/app/design/reports/${r.id}`">
                    {{ r.name }}
                </RouterLink>
                <span class="text-xs text-slate-500">
                    v{{ r.latest_version?.version }} · {{ r.latest_version?.status }}
                </span>
            </li>
        </ul>
    </div>
</template>
