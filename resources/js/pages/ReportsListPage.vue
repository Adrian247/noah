<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';

type ReportRow = {
    id: number;
    name: string;
    slug: string;
    published_version?: { version: number; status: string } | null;
    draft_version?: { version: number; status: string } | null;
};

const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('design_reports'));

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
        <p class="text-sm text-slate-600">
            Publicar deja una versión en producción y abre un borrador nuevo. El enlace al tipo de rutina se hace en
            <strong>Tipos de rutina</strong>.
        </p>
        <form v-if="canWrite" class="flex flex-wrap gap-2" @submit.prevent="createReport">
            <input
                v-model="name"
                placeholder="Nombre de plantilla"
                class="rounded-md border border-slate-300 px-2 py-1.5 text-sm"
            />
            <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white">
                Crear
            </button>
        </form>
        <ReadOnlyNotice v-else module-label="Reportes" />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <ul v-else class="divide-y rounded-lg border bg-white">
            <li v-for="r in reports" :key="r.id" class="flex justify-between px-4 py-3 text-sm">
                <RouterLink class="font-medium underline" :to="`/app/design/reports/${r.id}`">
                    {{ r.name }}
                </RouterLink>
                <div class="text-right text-xs text-slate-500">
                    <p v-if="r.published_version">
                        En uso: <span class="font-medium text-emerald-800">v{{ r.published_version.version }} publicada</span>
                    </p>
                    <p v-else class="text-amber-800">Sin versión publicada</p>
                    <p v-if="r.draft_version">Borrador: v{{ r.draft_version.version }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
