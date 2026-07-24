<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import DynamicFormRenderer from '@/components/domain/DynamicFormRenderer.vue';
import { api, getToken, getCompanyId } from '@/api/client';

type FormVersion = {
    id: number;
    schema?: { sections?: { title?: string; fields: { key: string; type: string; label: string }[] }[] };
};

type SupplyItem = {
    id: number;
    sku: string;
    name: string;
    standard_cost?: string | number | null;
};

type ConsumptionLine = {
    supply_item_id: number;
    quantity: number;
    unit_cost?: number;
    supply_item?: SupplyItem;
};

type Execution = {
    technician_comments?: string;
    corrected_comments?: string;
    duration_minutes?: number;
    responses?: Record<string, unknown>;
    consumptions?: ConsumptionLine[];
};

type WorkflowTransition = {
    from_step?: string | null;
    to_step: string;
    trigger: string;
    occurred_at: string;
};

type Routine = {
    id: number;
    status: string;
    asset?: { tag: string };
    routine_type?: { name: string; form_version?: FormVersion | null };
    latest_execution?: Execution;
    generated_reports?: { id: number; status: string }[];
    invoice?: { id: number; status: string; total: string };
    workflow_instance?: {
        current_step_key: string;
        status: string;
        transitions?: WorkflowTransition[];
    } | null;
};

const route = useRoute();
const routine = ref<Routine | null>(null);
const supplies = ref<SupplyItem[]>([]);
const loading = ref(true);
const message = ref<string | null>(null);
const submitting = ref(false);

const formResponses = ref<Record<string, string | number>>({});
const technicianComments = ref('');
const durationMinutes = ref(60);
const consumptionSupplyId = ref('');
const consumptionQty = ref('1');

const formSchema = computed(() => routine.value?.routine_type?.form_version?.schema ?? null);
const canExecute = computed(() => routine.value?.status === 'assigned');

async function load() {
    loading.value = true;
    message.value = null;
    try {
        const res = await api<{ data: Routine }>(`/routines/${route.params.id}`);
        routine.value = res.data;
        if (res.data.latest_execution?.responses) {
            formResponses.value = { ...(res.data.latest_execution.responses as Record<string, string | number>) };
        }
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function loadSupplies() {
    try {
        const res = await api<{ data: SupplyItem[] }>('/inventory/supplies');
        supplies.value = res.data;
        if (res.data[0]) {
            consumptionSupplyId.value = String(res.data[0].id);
        }
    } catch {
        supplies.value = [];
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

async function rejectRoutine() {
    const reason = window.prompt('Motivo del rechazo:');
    if (!reason?.trim()) {
        return;
    }
    await api(`/routines/${route.params.id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ reason: reason.trim() }),
    });
    message.value = 'Rutina devuelta al técnico para corrección.';
    await load();
}

async function submitExecution() {
    submitting.value = true;
    message.value = null;
    try {
        const consumptions =
            consumptionSupplyId.value && Number(consumptionQty.value) > 0
                ? [
                      {
                          supply_item_id: Number(consumptionSupplyId.value),
                          quantity: Number(consumptionQty.value),
                      },
                  ]
                : [];

        const responses: Record<string, string | number> = {};
        for (const [key, val] of Object.entries(formResponses.value)) {
            if (val === '' || val === undefined) {
                continue;
            }
            responses[key] = typeof val === 'string' && /^\d+(\.\d+)?$/.test(val) ? Number(val) : val;
        }

        await api(`/routines/${route.params.id}/executions`, {
            method: 'POST',
            body: JSON.stringify({
                responses,
                technician_comments: technicianComments.value || null,
                duration_minutes: durationMinutes.value,
                consumptions,
            }),
        });
        message.value = 'Ejecución enviada.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    await Promise.all([load(), loadSupplies()]);
});
</script>

<template>
    <div v-if="loading" class="text-slate-500">Cargando…</div>
    <div v-else-if="routine" class="max-w-3xl space-y-4">
        <h2 class="text-xl font-semibold">Rutina #{{ routine.id }}</h2>
        <p class="text-sm text-slate-600">
            {{ routine.routine_type?.name }} · {{ routine.asset?.tag }} ·
            <span class="font-medium">{{ routine.status }}</span>
            <span v-if="routine.workflow_instance" class="text-slate-500">
                · paso {{ routine.workflow_instance.current_step_key }}
            </span>
        </p>

        <ul
            v-if="routine.workflow_instance?.transitions?.length"
            class="rounded-lg border border-slate-200 bg-white p-4 text-xs text-slate-600"
        >
            <li
                v-for="(t, i) in routine.workflow_instance.transitions"
                :key="i"
            >
                {{ t.occurred_at }} — {{ t.from_step ?? 'inicio' }} → {{ t.to_step }} ({{ t.trigger }})
            </li>
        </ul>

        <div v-if="canExecute" class="space-y-4">
            <DynamicFormRenderer v-model="formResponses" :schema="formSchema" />
            <div class="rounded-lg border border-slate-200 bg-white p-4 space-y-3 text-sm">
                <label class="block">
                    Comentario técnico (resumen)
                    <textarea
                        v-model="technicianComments"
                        rows="2"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                        placeholder="Ej. se reemplazó filtro y se verificó presión"
                    />
                </label>
                <label class="block">
                    Duración (minutos)
                    <input
                        v-model.number="durationMinutes"
                        type="number"
                        min="0"
                        class="mt-1 w-32 rounded-md border border-slate-300 px-2 py-1.5"
                    />
                </label>
                <div v-if="supplies.length" class="grid gap-2 sm:grid-cols-2">
                    <label class="block">
                        Insumo consumido
                        <select
                            v-model="consumptionSupplyId"
                            class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                        >
                            <option
                                v-for="s in supplies"
                                :key="s.id"
                                :value="String(s.id)"
                            >
                                {{ s.sku }} — {{ s.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        Cantidad
                        <input
                            v-model="consumptionQty"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                        />
                    </label>
                </div>
            </div>
            <button
                type="button"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
                :disabled="submitting"
                @click="submitExecution"
            >
                Enviar ejecución
            </button>
        </div>

        <div v-if="routine.latest_execution" class="rounded-lg border bg-white p-4 text-sm space-y-3">
            <p class="font-medium">Última ejecución</p>
            <div v-if="routine.latest_execution.responses && Object.keys(routine.latest_execution.responses).length">
                <p class="text-slate-500">Respuestas del formulario</p>
                <ul class="mt-1 list-inside list-disc text-slate-700">
                    <li v-for="(val, key) in routine.latest_execution.responses" :key="key">
                        <span class="font-mono text-xs">{{ key }}</span>: {{ val }}
                    </li>
                </ul>
            </div>
            <p v-if="routine.latest_execution.technician_comments">
                <span class="text-slate-500">Comentario técnico</span><br />
                {{ routine.latest_execution.technician_comments }}
            </p>
            <p v-if="routine.latest_execution.corrected_comments">
                <span class="text-slate-500">Texto corregido</span><br />
                {{ routine.latest_execution.corrected_comments }}
            </p>
            <ul
                v-if="routine.latest_execution.consumptions?.length"
                class="list-inside list-disc text-slate-700"
            >
                <li v-for="c in routine.latest_execution.consumptions" :key="c.supply_item_id">
                    {{ c.supply_item?.name ?? 'Insumo' }} × {{ c.quantity }}
                </li>
            </ul>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-if="routine.status === 'pending_validation'"
                type="button"
                class="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white"
                @click="validateRoutine"
            >
                Validar
            </button>
            <button
                v-if="routine.status === 'pending_validation'"
                type="button"
                class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-800"
                @click="rejectRoutine"
            >
                Rechazar
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
        <p
            v-if="routine.generated_reports?.some((x) => ['queued', 'processing'].includes(x.status))"
            class="text-sm text-amber-800"
        >
            Generando PDF en segundo plano… recarga en unos segundos.
        </p>
        <p v-if="routine.invoice" class="text-sm">
            Factura borrador #{{ routine.invoice.id }} — {{ routine.invoice.status }} — ${{
                routine.invoice.total
            }}
        </p>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
