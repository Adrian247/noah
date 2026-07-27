<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import DynamicFormRenderer from '@/components/domain/DynamicFormRenderer.vue';
import { validateRequiredFields } from '@/composables/validateFormResponses';
import { useToast } from '@/composables/useToast';
import { api, getToken, getCompanyId } from '@/api/client';
import { useCompanyStore } from '@/stores/company';

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
    rejection_reason?: string | null;
    rejected_at?: string | null;
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
    generated_reports?: { id: number; status: string; error_message?: string | null }[];
    invoice?: { id: number; status: string; total: string };
    workflow_instance?: {
        current_step_key: string;
        status: string;
        transitions?: WorkflowTransition[];
    } | null;
};

const route = useRoute();
const toast = useToast();
const companyStore = useCompanyStore();
const routine = ref<Routine | null>(null);
const supplies = ref<SupplyItem[]>([]);
const loading = ref(true);
const missingFieldKeys = ref<string[]>([]);
const submitting = ref(false);

const formResponses = ref<Record<string, unknown>>({});
const formDesignSettings = ref<{ max_image_size_kb: number; allowed_image_mimes: string[] } | null>(null);
const formOptionCatalogs = ref<{ id: number; name: string; options: { value: string; label: string }[] }[]>([]);
const technicianComments = ref('');
const durationMinutes = ref(60);
const consumptionLines = ref<{ supply_item_id: string; quantity: string }[]>([
    { supply_item_id: '', quantity: '1' },
]);
const showRejectPanel = ref(false);
const rejectReason = ref('');
const rejecting = ref(false);

const formSchema = computed(() => routine.value?.routine_type?.form_version?.schema ?? null);
const canExecute = computed(() => routine.value?.status === 'assigned');
const canValidateReject = computed(() => {
    const role = companyStore.current?.role;
    return role === 'supervisor' || role === 'administrator';
});
const isPendingValidation = computed(() => routine.value?.status === 'pending_validation');
const showRejectionNotice = computed(
    () =>
        routine.value?.status === 'assigned' &&
        Boolean(routine.value?.latest_execution?.rejection_reason),
);
const reportPollTimer = ref<ReturnType<typeof setInterval> | null>(null);

function needsReportPoll(): boolean {
    return (
        routine.value?.generated_reports?.some((x) => ['queued', 'processing'].includes(x.status)) ??
        false
    );
}

function startReportPoll() {
    if (reportPollTimer.value !== null) {
        return;
    }
    reportPollTimer.value = setInterval(() => {
        if (needsReportPoll()) {
            void load({ silent: true });
        } else {
            stopReportPoll();
        }
    }, 3000);
}

function stopReportPoll() {
    if (reportPollTimer.value !== null) {
        clearInterval(reportPollTimer.value);
        reportPollTimer.value = null;
    }
}

async function load(options: { silent?: boolean } = {}) {
    if (!options.silent) {
        loading.value = true;
    }
    try {
        const res = await api<{
            data: Routine;
            form_design?: {
                settings: { max_image_size_kb: number; allowed_image_mimes: string[] };
                option_catalogs: { id: number; name: string; options: { value: string; label: string }[] }[];
            };
        }>(`/routines/${route.params.id}`);
        routine.value = res.data;
        if (res.form_design) {
            formDesignSettings.value = res.form_design.settings;
            formOptionCatalogs.value = res.form_design.option_catalogs;
        }
        if (res.data.latest_execution?.responses) {
            formResponses.value = { ...(res.data.latest_execution.responses as Record<string, unknown>) };
        }
        if (needsReportPoll()) {
            startReportPoll();
        } else {
            stopReportPoll();
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        if (!options.silent) {
            loading.value = false;
        }
    }
}

async function loadSupplies() {
    try {
        const res = await api<{ data: SupplyItem[] }>('/inventory/supplies');
        supplies.value = res.data;
        if (consumptionLines.value.length === 1 && !consumptionLines.value[0].supply_item_id && res.data[0]) {
            consumptionLines.value[0].supply_item_id = String(res.data[0].id);
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
    const contentType = res.headers.get('content-type') ?? '';
    if (!res.ok || !contentType.includes('pdf')) {
        let detail = 'No se pudo descargar el PDF.';
        try {
            const body = await res.json();
            if (body?.message) {
                detail = body.message;
            }
        } catch {
            /* not JSON */
        }
        toast.error(detail);
        return;
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `reporte-${routine.value?.id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

async function validateRoutine() {
    try {
        await api(`/routines/${route.params.id}/validate`, { method: 'POST' });
        toast.success('Rutina validada; generando reporte y borrador de factura.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function rejectRoutine() {
    if (!rejectReason.value.trim()) {
        toast.warning('Indica el motivo del rechazo.');
        return;
    }
    rejecting.value = true;
    try {
        await api(`/routines/${route.params.id}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason: rejectReason.value.trim() }),
        });
        showRejectPanel.value = false;
        rejectReason.value = '';
        toast.success('Rutina devuelta al técnico para corrección.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        rejecting.value = false;
    }
}

function openRejectPanel() {
    showRejectPanel.value = true;
    rejectReason.value = '';
}

function cancelReject() {
    showRejectPanel.value = false;
    rejectReason.value = '';
}

function addConsumptionLine() {
    consumptionLines.value.push({ supply_item_id: supplies.value[0] ? String(supplies.value[0].id) : '', quantity: '1' });
}

function removeConsumptionLine(index: number) {
    if (consumptionLines.value.length <= 1) {
        consumptionLines.value[0] = { supply_item_id: supplies.value[0] ? String(supplies.value[0].id) : '', quantity: '1' };
        return;
    }
    consumptionLines.value.splice(index, 1);
}

async function submitExecution() {
    const missing = validateRequiredFields(formSchema.value, formResponses.value);
    if (missing.keys.length > 0) {
        missingFieldKeys.value = missing.keys;
        toast.error(`Faltan campos obligatorios: ${missing.labels.join(', ')}`, 16_000);
        await nextTick();
        const first = document.getElementById(`routine-field-${missing.keys[0]}`);
        first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    submitting.value = true;
    missingFieldKeys.value = [];
    try {
        const consumptions = consumptionLines.value
            .filter((line) => line.supply_item_id && Number(line.quantity) > 0)
            .map((line) => ({
                supply_item_id: Number(line.supply_item_id),
                quantity: Number(line.quantity),
            }));

        const responses: Record<string, unknown> = {};
        for (const [key, val] of Object.entries(formResponses.value)) {
            if (val === '' || val === undefined) {
                continue;
            }
            if (typeof val === 'object' && val !== null) {
                responses[key] = val;
                continue;
            }
            responses[key] =
                typeof val === 'string' && /^\d+(\.\d+)?$/.test(val) ? Number(val) : val;
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
        toast.success('Ejecución enviada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        submitting.value = false;
    }
}

onMounted(async () => {
    await Promise.all([load(), loadSupplies()]);
});

watch(
    formResponses,
    () => {
        if (!missingFieldKeys.value.length) {
            return;
        }
        const still = validateRequiredFields(formSchema.value, formResponses.value);
        missingFieldKeys.value = still.keys;
    },
    { deep: true },
);

onUnmounted(() => {
    stopReportPoll();
});
</script>

<template>
    <div v-if="loading" class="text-slate-500">Cargando…</div>
    <div v-else-if="routine" class="w-full max-w-[1600px] space-y-4">
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
            class="portal-form-panel p-4 text-xs text-slate-600"
        >
            <li
                v-for="(t, i) in routine.workflow_instance.transitions"
                :key="i"
            >
                {{ t.occurred_at }} — {{ t.from_step ?? 'inicio' }} → {{ t.to_step }} ({{ t.trigger }})
            </li>
        </ul>

        <div
            v-if="showRejectionNotice"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-950"
        >
            <p class="font-medium">Devuelta para corrección</p>
            <p class="mt-1">{{ routine.latest_execution?.rejection_reason }}</p>
            <p v-if="routine.latest_execution?.rejected_at" class="mt-2 text-xs text-red-800">
                {{ routine.latest_execution.rejected_at }}
            </p>
        </div>

        <div v-if="canExecute" class="space-y-4">
            <DynamicFormRenderer
                v-model="formResponses"
                :schema="formSchema"
                :routine-id="routine.id"
                :form-settings="formDesignSettings"
                :option-catalogs="formOptionCatalogs"
                :highlight-keys="missingFieldKeys"
            />
            <div class="portal-form-panel p-4 space-y-3 text-sm">
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
                <div v-if="supplies.length" class="space-y-3">
                    <p class="font-medium text-slate-800">Insumos utilizados</p>
                    <div
                        v-for="(line, idx) in consumptionLines"
                        :key="idx"
                        class="grid gap-2 sm:grid-cols-[1fr_8rem_auto]"
                    >
                        <label class="block">
                            <span class="text-xs text-slate-600">Insumo</span>
                            <select
                                v-model="line.supply_item_id"
                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                            >
                                <option value="">—</option>
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
                            <span class="text-xs text-slate-600">Cantidad</span>
                            <input
                                v-model="line.quantity"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                            />
                        </label>
                        <div class="flex items-end pb-0.5">
                            <button
                                type="button"
                                class="rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700"
                                @click="removeConsumptionLine(idx)"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-slate-700 underline"
                        @click="addConsumptionLine"
                    >
                        + Agregar otro insumo
                    </button>
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

        <div v-if="routine.latest_execution" class="portal-form-panel p-4 text-sm space-y-3">
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

        <div
            v-if="isPendingValidation && canValidateReject"
            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"
        >
            Esta rutina espera tu validación como supervisor o administrador.
        </div>
        <div
            v-else-if="isPendingValidation"
            class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"
        >
            Enviada a validación. Un supervisor o administrador debe aprobarla (revisa Mailpit en
            <span class="font-mono text-xs">http://localhost:8025</span> si eres supervisor).
        </div>

        <div
            v-if="isPendingValidation && canValidateReject && showRejectPanel"
            class="portal-form-panel border-red-500/30 text-sm space-y-3"
        >
            <p class="font-medium text-red-900">Rechazar rutina</p>
            <label class="block">
                Motivo (visible para el técnico)
                <textarea
                    v-model="rejectReason"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                    placeholder="Ej. Falta evidencia fotográfica del filtro nuevo"
                />
            </label>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md bg-red-700 px-3 py-2 text-sm text-white disabled:opacity-50"
                    :disabled="rejecting"
                    @click="rejectRoutine"
                >
                    Confirmar rechazo
                </button>
                <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="cancelReject">
                    Cancelar
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-if="isPendingValidation && canValidateReject && !showRejectPanel"
                type="button"
                class="rounded-md bg-emerald-700 px-3 py-2 text-sm text-white"
                @click="validateRoutine"
            >
                Validar
            </button>
            <button
                v-if="isPendingValidation && canValidateReject && !showRejectPanel"
                type="button"
                class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-800"
                @click="openRejectPanel"
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
            Generando PDF en segundo plano… se actualizará automáticamente.
        </p>
        <p
            v-for="r in routine.generated_reports?.filter((x) => x.status === 'failed')"
            :key="'err-' + r.id"
            class="text-sm text-red-700"
        >
            Error al generar PDF: {{ r.error_message ?? 'desconocido' }}
        </p>
        <p v-if="routine.invoice" class="text-sm">
            Factura borrador #{{ routine.invoice.id }} — {{ routine.invoice.status }} — ${{
                routine.invoice.total
            }}
        </p>
    </div>
</template>
