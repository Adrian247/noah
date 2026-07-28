<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { api, getToken, getCompanyId } from '@/api/client';
import { useToast } from '@/composables/useToast';
import StatusBadge from '@/components/ui/StatusBadge.vue';

type WorkflowTransition = {
    from_step?: string | null;
    to_step: string;
    trigger: string;
    occurred_at: string;
};

type Execution = {
    id: number;
    status?: string;
    technician_comments?: string | null;
    submitted_at?: string | null;
    validated_at?: string | null;
};

type RoutineDetail = {
    id: number;
    status: string;
    asset?: {
        tag: string;
        serial_number?: string | null;
        catalog_item?: { name?: string };
    };
    routine_type?: { name: string };
    latest_execution?: Execution | null;
    executions?: Execution[];
    invoice?: {
        id: number;
        number?: string | null;
        status: string;
        total?: string;
        currency?: string;
        issued_at?: string | null;
    } | null;
    workflow_instance?: {
        current_step_key: string;
        status: string;
        transitions?: WorkflowTransition[];
    } | null;
    generated_reports?: { id: number; status: string }[];
};

const route = useRoute();
const toast = useToast();
const routine = ref<RoutineDetail | null>(null);
const loading = ref(true);

const executionHistory = computed(() => {
    const list = routine.value?.executions ?? [];
    if (list.length) {
        return [...list].sort((a, b) => b.id - a.id);
    }
    if (routine.value?.latest_execution) {
        return [routine.value.latest_execution];
    }
    return [];
});

async function load() {
    loading.value = true;
    try {
        const id = route.params.id;
        const res = await api<{ data: RoutineDetail }>(`/portal/routines/${id}`);
        routine.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
        routine.value = null;
    } finally {
        loading.value = false;
    }
}

async function downloadInvoice(id: number) {
    const res = await fetch(`/api/v1/portal/invoices/${id}/download`, {
        headers: {
            Authorization: `Bearer ${getToken()}`,
            'X-Company-Id': getCompanyId() ?? '',
            Accept: 'application/pdf',
        },
    });
    if (!res.ok) {
        toast.error('No se pudo descargar la factura.');
        return;
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `factura-${id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <p>
            <RouterLink class="text-sm text-amber-800 underline" to="/portal/routines">← Volver a rutinas</RouterLink>
        </p>

        <p v-if="loading" class="text-slate-500">Cargando…</p>

        <template v-else-if="routine">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold">Rutina #{{ routine.id }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ routine.routine_type?.name }} · Activo {{ routine.asset?.tag }}
                        <span v-if="routine.asset?.serial_number"> · Serie {{ routine.asset.serial_number }}</span>
                    </p>
                    <p v-if="routine.asset?.catalog_item?.name" class="text-sm text-slate-500">
                        {{ routine.asset.catalog_item.name }}
                    </p>
                </div>
                <StatusBadge :status="routine.status" />
            </div>

            <section
                v-if="routine.workflow_instance?.transitions?.length"
                class="rounded-xl border border-slate-200 bg-white p-4 text-sm"
            >
                <h3 class="font-medium text-slate-800">Progreso del servicio</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Paso actual: {{ routine.workflow_instance.current_step_key }}
                </p>
                <ul class="mt-3 space-y-2 text-xs text-slate-600">
                    <li
                        v-for="(t, i) in routine.workflow_instance.transitions"
                        :key="i"
                    >
                        {{ new Date(t.occurred_at).toLocaleString() }} —
                        {{ t.from_step ?? 'inicio' }} → {{ t.to_step }} ({{ t.trigger }})
                    </li>
                </ul>
            </section>

            <section
                v-if="routine.invoice"
                class="rounded-xl border border-slate-200 bg-white p-4 text-sm"
            >
                <h3 class="font-medium text-slate-800">Factura</h3>
                <p class="mt-2">
                    {{ routine.invoice.number ?? `Factura #${routine.invoice.id}` }}
                    <span v-if="routine.invoice.total" class="font-semibold">
                        · {{ routine.invoice.total }} {{ routine.invoice.currency }}
                    </span>
                </p>
                <p v-if="routine.invoice.issued_at" class="text-xs text-slate-500">
                    Emitida {{ new Date(routine.invoice.issued_at).toLocaleString() }}
                </p>
                <button
                    type="button"
                    class="mt-3 rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-stone-950"
                    @click="downloadInvoice(routine.invoice!.id)"
                >
                    Descargar PDF
                </button>
            </section>

            <section
                v-if="routine.generated_reports?.length"
                class="rounded-xl border border-slate-200 bg-white p-4 text-sm"
            >
                <h3 class="font-medium text-slate-800">Informes</h3>
                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                    <li v-for="rep in routine.generated_reports" :key="rep.id">
                        Informe #{{ rep.id }} — {{ rep.status }}
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <h3 class="font-medium text-slate-800">Historial de visitas</h3>
                <ul v-if="executionHistory.length" class="mt-3 divide-y text-sm">
                    <li v-for="ex in executionHistory" :key="ex.id" class="py-3 first:pt-0">
                        <p class="text-xs text-slate-500">Ejecución #{{ ex.id }}</p>
                        <p v-if="ex.submitted_at" class="mt-1 text-slate-600">
                            Enviada {{ new Date(ex.submitted_at).toLocaleString() }}
                        </p>
                        <p v-if="ex.validated_at" class="text-slate-600">
                            Validada {{ new Date(ex.validated_at).toLocaleString() }}
                        </p>
                        <p v-if="ex.technician_comments" class="mt-2 text-slate-700">
                            {{ ex.technician_comments }}
                        </p>
                    </li>
                </ul>
                <p v-else class="mt-2 text-slate-500">Sin ejecuciones registradas.</p>
            </section>
        </template>

        <p v-else class="text-slate-500">Rutina no encontrada o sin acceso.</p>
    </div>
</template>
