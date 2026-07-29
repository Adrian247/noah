<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, nextTick, watch } from 'vue';
import { useRoute, RouterLink, useRouter } from 'vue-router';
import DynamicFormRenderer from '@/components/domain/DynamicFormRenderer.vue';
import { validateRequiredFields } from '@/composables/validateFormResponses';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { api, getToken, getCompanyId } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { usePermissions } from '@/composables/usePermissions';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import { auditActionLabel } from '@/lib/auditLabels';

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

type WorkflowAction = {
    trigger: string;
    label: string;
    to_step: string;
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
        correlation_id?: string | null;
        transitions?: WorkflowTransition[];
        available_actions?: WorkflowAction[];
    } | null;
};

type AuditEntry = {
    id: number;
    action: string;
    subject_type?: string | null;
    subject_type_label?: string | null;
    subject_id?: number | null;
    metadata?: Record<string, unknown> | null;
    ip?: string | null;
    occurred_at: string;
    actor?: { id: number; name: string; email: string } | null;
};

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const companyStore = useCompanyStore();
const { can } = usePermissions();
const routine = ref<Routine | null>(null);
const auditEntries = ref<AuditEntry[]>([]);
const auditLoading = ref(false);
const expandedAuditIds = ref<Set<number>>(new Set());
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
const isAdmin = computed(() => companyStore.current?.role === 'administrator');
const deletingRoutine = ref(false);
const isPendingValidation = computed(() => routine.value?.status === 'pending_validation');
const showRejectionNotice = computed(
    () =>
        routine.value?.status === 'assigned' &&
        Boolean(routine.value?.latest_execution?.rejection_reason),
);
const reportPollTimer = ref<ReturnType<typeof setInterval> | null>(null);

const workflowCorrelationId = computed(
    () => routine.value?.workflow_instance?.correlation_id ?? null,
);
const showAuditTimeline = computed(
    () => can('audit.view') && Boolean(workflowCorrelationId.value),
);

function workflowActionLabel(trigger: string, fallback: string): string {
    const actions = routine.value?.workflow_instance?.available_actions;
    const match = actions?.find((a) => a.trigger === trigger);
    return match?.label?.trim() ? match.label : fallback;
}

const submitActionLabel = computed(() =>
    workflowActionLabel('execution_submitted', 'Enviar ejecución'),
);
const approveActionLabel = computed(() => workflowActionLabel('approved', 'Validar'));
const rejectActionLabel = computed(() => workflowActionLabel('rejected', 'Rechazar'));

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
        await loadAuditTimeline();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        if (!options.silent) {
            loading.value = false;
        }
    }
}

async function loadAuditTimeline() {
    auditEntries.value = [];
    const correlationId = routine.value?.workflow_instance?.correlation_id;
    if (!correlationId || !can('audit.view')) {
        return;
    }
    auditLoading.value = true;
    expandedAuditIds.value = new Set();
    try {
        const res = await api<{ data: AuditEntry[] }>(
            `/audit/entries?correlation_id=${encodeURIComponent(correlationId)}&per_page=50`,
        );
        auditEntries.value = res.data ?? [];
    } catch {
        auditEntries.value = [];
    } finally {
        auditLoading.value = false;
    }
}

function toggleAuditEntry(id: number) {
    const next = new Set(expandedAuditIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedAuditIds.value = next;
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
        toast.success(`${approveActionLabel.value} registrada; generando reporte y borrador de factura.`);
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
        toast.success(`Rutina devuelta al técnico (${rejectActionLabel.value}).`);
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

async function deleteRoutine() {
    if (!routine.value) {
        return;
    }
    const accepted = await confirm(
        `¿Eliminar la rutina #${routine.value.id}? Esta acción no se puede deshacer.`,
        { title: 'Eliminar rutina', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    deletingRoutine.value = true;
    try {
        await api(`/routines/${routine.value.id}`, { method: 'DELETE' });
        toast.success('Rutina eliminada.');
        await router.push('/app/routines');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deletingRoutine.value = false;
    }
}
</script>

<template>
    <div v-if="loading" class="text-portal-muted">Cargando…</div>
    <div v-else-if="routine" class="portal-page w-full max-w-[1600px] space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-portal-heading text-xl font-semibold">Rutina #{{ routine.id }}</h2>
                <p class="text-portal-muted text-sm">
                    {{ routine.routine_type?.name }} · {{ routine.asset?.tag }} ·
                    <span class="text-portal-heading font-medium">{{ routine.status }}</span>
                    <span v-if="routine.workflow_instance">
                        · paso {{ routine.workflow_instance.current_step_key }}
                    </span>
                </p>
            </div>
            <IconActionButton
                v-if="isAdmin"
                icon="trash"
                label="Eliminar rutina"
                variant="danger"
                :disabled="deletingRoutine"
                @click="deleteRoutine"
            />
        </div>

        <ul
            v-if="routine.workflow_instance?.transitions?.length"
            class="portal-form-panel text-portal-muted p-4 text-xs"
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
            class="portal-callout portal-callout--danger"
        >
            <p class="font-medium">Devuelta para corrección</p>
            <p class="mt-1 opacity-90">{{ routine.latest_execution?.rejection_reason }}</p>
            <p v-if="routine.latest_execution?.rejected_at" class="mt-2 text-xs opacity-80">
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
            <div class="portal-form-panel space-y-3 p-4 text-sm">
                <label class="text-portal-heading block">
                    Comentario técnico (resumen)
                    <textarea
                        v-model="technicianComments"
                        rows="2"
                        class="field-input mt-1 w-full"
                        placeholder="Ej. se reemplazó filtro y se verificó presión"
                    />
                </label>
                <label class="text-portal-heading block">
                    Duración (minutos)
                    <input
                        v-model.number="durationMinutes"
                        type="number"
                        min="0"
                        class="field-input mt-1 w-32"
                    />
                </label>
                <div v-if="supplies.length" class="space-y-3">
                    <p class="text-portal-heading font-medium">Insumos utilizados</p>
                    <div
                        v-for="(line, idx) in consumptionLines"
                        :key="idx"
                        class="grid gap-2 sm:grid-cols-[1fr_8rem_auto]"
                    >
                        <label class="block">
                            <span class="text-portal-muted text-xs">Insumo</span>
                            <select
                                v-model="line.supply_item_id"
                                class="field-input mt-1 w-full"
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
                            <span class="text-portal-muted text-xs">Cantidad</span>
                            <input
                                v-model="line.quantity"
                                type="number"
                                min="0"
                                step="0.01"
                                class="field-input mt-1 w-full"
                            />
                        </label>
                        <div class="flex items-end pb-0.5">
                            <IconActionButton
                                icon="trash"
                                label="Quitar línea de consumo"
                                variant="danger"
                                @click="removeConsumptionLine(idx)"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-portal-link text-sm underline"
                        @click="addConsumptionLine"
                    >
                        + Agregar otro insumo
                    </button>
                </div>
            </div>
            <AppButton
                type="button"
                :disabled="submitting"
                @click="submitExecution"
            >
                {{ submitting ? 'Enviando…' : submitActionLabel }}
            </AppButton>
        </div>

        <details
            v-if="showAuditTimeline"
            class="portal-form-panel group space-y-3 p-4 text-sm"
            :open="!canExecute"
        >
            <summary
                class="text-portal-heading flex cursor-pointer list-none items-center justify-between gap-2 font-medium marker:content-none"
            >
                <span>Trazabilidad (auditoría)</span>
                <span class="text-portal-muted text-xs font-normal group-open:hidden">Mostrar</span>
            </summary>
            <div class="flex flex-wrap items-baseline justify-between gap-2 pt-2">
                <div class="flex flex-wrap items-center gap-3">
                    <RouterLink
                        v-if="workflowCorrelationId"
                        class="text-portal-link text-xs underline"
                        :to="{ name: 'audit', query: { correlation: workflowCorrelationId } }"
                    >
                        Ver en Auditoría
                    </RouterLink>
                    <span class="text-portal-muted font-mono text-xs">{{ workflowCorrelationId }}</span>
                </div>
            </div>
            <p v-if="auditLoading" class="text-portal-muted text-xs">Cargando eventos…</p>
            <p v-else-if="!auditEntries.length" class="text-portal-muted text-xs">
                Sin eventos de auditoría para este ciclo.
            </p>
            <ol v-else class="relative space-y-0 border-l border-white/15 pl-4 text-xs">
                <li
                    v-for="entry in auditEntries"
                    :key="entry.id"
                    class="relative pb-3 last:pb-0"
                >
                    <span class="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-sky-400" />
                    <button
                        type="button"
                        class="w-full rounded-lg px-1 py-0.5 text-left hover:bg-white/5"
                        @click="toggleAuditEntry(entry.id)"
                    >
                        <div class="flex flex-wrap gap-x-3 gap-y-1">
                            <span class="text-portal-muted whitespace-nowrap">
                                {{ new Date(entry.occurred_at).toLocaleString() }}
                            </span>
                            <span class="text-portal-heading font-medium">
                                {{ auditActionLabel(entry.action) }}
                            </span>
                            <span class="text-portal-heading">{{ entry.actor?.name ?? '—' }}</span>
                        </div>
                        <p class="text-portal-muted mt-0.5 font-mono opacity-70">{{ entry.action }}</p>
                    </button>
                    <div
                        v-if="expandedAuditIds.has(entry.id)"
                        class="mt-2 space-y-1 rounded-lg border border-white/10 bg-black/20 p-2"
                    >
                        <p v-if="entry.subject_type" class="text-portal-muted">
                            {{ entry.subject_type_label ?? entry.subject_type }} #{{ entry.subject_id }}
                        </p>
                        <pre
                            v-if="entry.metadata && Object.keys(entry.metadata).length"
                            class="text-portal-heading overflow-x-auto whitespace-pre-wrap font-mono"
                        >{{ JSON.stringify(entry.metadata, null, 2) }}</pre>
                        <p v-else class="text-portal-muted">Sin metadata.</p>
                    </div>
                </li>
            </ol>
        </details>

        <div
            v-else-if="formSchema && Object.keys(formResponses).length"
            class="space-y-2"
        >
            <p class="text-portal-muted text-sm">Formulario capturado (solo lectura)</p>
            <DynamicFormRenderer
                v-model="formResponses"
                :schema="formSchema"
                :routine-id="routine.id"
                :form-settings="formDesignSettings"
                :option-catalogs="formOptionCatalogs"
                disabled
            />
        </div>

        <div v-if="routine.latest_execution" class="portal-form-panel space-y-3 p-4 text-sm">
            <p class="text-portal-heading font-medium">Última ejecución</p>
            <p v-if="routine.latest_execution.technician_comments">
                <span class="text-portal-muted">Comentario técnico</span><br />
                <span class="text-portal-heading">{{ routine.latest_execution.technician_comments }}</span>
            </p>
            <p v-if="routine.latest_execution.corrected_comments">
                <span class="text-portal-muted">Texto corregido</span><br />
                <span class="text-portal-heading">{{ routine.latest_execution.corrected_comments }}</span>
            </p>
            <ul
                v-if="routine.latest_execution.consumptions?.length"
                class="text-portal-heading list-inside list-disc"
            >
                <li v-for="c in routine.latest_execution.consumptions" :key="c.supply_item_id">
                    {{ c.supply_item?.name ?? 'Insumo' }} × {{ c.quantity }}
                </li>
            </ul>
        </div>

        <div
            v-if="isPendingValidation && canValidateReject"
            class="portal-callout portal-callout--warning"
        >
            Esta rutina espera tu validación como supervisor o administrador.
        </div>
        <div
            v-else-if="isPendingValidation"
            class="portal-callout portal-callout--info"
        >
            Enviada a validación. Un supervisor o administrador debe aprobarla (revisa Mailpit en
            <span class="font-mono text-xs opacity-80">http://localhost:8025</span> si eres supervisor).
        </div>

        <div
            v-if="isPendingValidation && canValidateReject && showRejectPanel"
            class="portal-form-panel space-y-3 border-red-500/30 text-sm"
        >
            <p class="portal-msg-danger">{{ rejectActionLabel }}</p>
            <label class="text-portal-heading block">
                Motivo (visible para el técnico)
                <textarea
                    v-model="rejectReason"
                    rows="3"
                    class="field-input mt-1 w-full"
                    placeholder="Ej. Falta evidencia fotográfica del filtro nuevo"
                />
            </label>
            <div class="flex flex-wrap gap-2">
                <AppButton type="button" variant="danger" :disabled="rejecting" @click="rejectRoutine">
                    Confirmar {{ rejectActionLabel.toLowerCase() }}
                </AppButton>
                <AppButton type="button" variant="ghost" @click="cancelReject">Cancelar</AppButton>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <AppButton
                v-if="isPendingValidation && canValidateReject && !showRejectPanel"
                type="button"
                @click="validateRoutine"
            >
                {{ approveActionLabel }}
            </AppButton>
            <AppButton
                v-if="isPendingValidation && canValidateReject && !showRejectPanel"
                type="button"
                variant="danger"
                @click="openRejectPanel"
            >
                {{ rejectActionLabel }}
            </AppButton>
            <AppButton
                v-for="r in routine.generated_reports?.filter((x) => x.status === 'ready')"
                :key="r.id"
                type="button"
                variant="secondary"
                @click="downloadReport(r.id)"
            >
                Descargar PDF
            </AppButton>
        </div>
        <p
            v-if="routine.generated_reports?.some((x) => ['queued', 'processing'].includes(x.status))"
            class="portal-msg-warning"
        >
            Generando PDF en segundo plano… se actualizará automáticamente.
        </p>
        <p
            v-for="r in routine.generated_reports?.filter((x) => x.status === 'failed')"
            :key="'err-' + r.id"
            class="portal-msg-danger"
        >
            Error al generar PDF: {{ r.error_message ?? 'desconocido' }}
        </p>
        <p v-if="routine.invoice" class="text-portal-heading text-sm">
            Factura borrador #{{ routine.invoice.id }} — {{ routine.invoice.status }} — ${{
                routine.invoice.total
            }}
        </p>
    </div>
</template>
