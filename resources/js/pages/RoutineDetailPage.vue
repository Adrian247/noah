<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, getToken, getCompanyId } from '@/api/client';

type Execution = {
    technician_comments?: string;
    corrected_comments?: string;
    duration_minutes?: number;
};

type Routine = {
    id: number;
    status: string;
    asset?: { tag: string };
    routine_type?: { name: string };
    latest_execution?: Execution;
    generated_reports?: { id: number; status: string }[];
    invoice?: { id: number; status: string; total: string };
};

const route = useRoute();
const routine = ref<Routine | null>(null);
const loading = ref(true);
const message = ref<string | null>(null);

async function load() {
    loading.value = true;
    message.value = null;
    try {
        const res = await api<{ data: Routine }>(`/routines/${route.params.id}`);
        routine.value = res.data;
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function downloadReport(reportId: number) {
    const res = await fetch(`/api/v1/reports/${reportId}/download`, {
        headers: {
            Authorization: `Bearer ${getToken()}`,
            'X-Company-Id': getCompanyId() ?? '',
            Accept: 'application/pdf',
        },
    });
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `reporte-${routine.value?.id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

async function validateRoutine() {
    await api(`/routines/${route.params.id}/validate`, { method: 'POST' });
    message.value = 'Rutina validada; generando reporte y borrador de factura.';
    await load();
}

async function submitExecution() {
    await api(`/routines/${route.params.id}/executions`, {
        method: 'POST',
        body: JSON.stringify({
            technician_comments: 'servicio preventivo completado',
            duration_minutes: 60,
        }),
    });
    message.value = 'Ejecución enviada.';
    await load();
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-slate-500">Cargando…</div>
    <div v-else-if="routine" class="max-w-3xl space-y-4">
        <h2 class="text-xl font-semibold">Rutina #{{ routine.id }}</h2>
        <p class="text-sm text-slate-600">
            {{ routine.routine_type?.name }} · {{ routine.asset?.tag }} ·
            <span class="font-medium">{{ routine.status }}</span>
        </p>
        <div v-if="routine.latest_execution" class="rounded-lg border bg-white p-4 text-sm">
            <p class="font-medium">Comentario técnico</p>
            <p class="mt-1 text-slate-700">{{ routine.latest_execution.technician_comments }}</p>
            <p class="mt-3 font-medium">Texto corregido</p>
            <p class="mt-1 text-slate-700">{{ routine.latest_execution.corrected_comments }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                v-if="routine.status === 'assigned'"
                type="button"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white"
                @click="submitExecution"
            >
                Simular ejecución (técnico)
            </button>
            <button
                v-if="routine.status === 'pending_validation'"
                type="button"
                class="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white"
                @click="validateRoutine"
            >
                Validar
            </button>
            <button
                v-for="r in routine.generated_reports?.filter((x) => x.status === 'ready')"
                :key="r.id"
                type="button"
                class="rounded-md border px-3 py-2 text-sm"
                @click="downloadReport(r.id)"
            >
                Descargar PDF
            </button>
        </div>
        <p v-if="routine.invoice" class="text-sm">
            Factura borrador #{{ routine.invoice.id }} — {{ routine.invoice.status }} — ${{ routine.invoice.total }}
        </p>
        <p v-if="message" class="text-sm text-emerald-700">{{ message }}</p>
    </div>
</template>
