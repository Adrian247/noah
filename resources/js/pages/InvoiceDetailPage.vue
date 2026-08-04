<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api, getCompanyId, getToken } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';

type LineType = 'supply' | 'labor' | 'other';

type DraftLine = {
    line_type: LineType;
    description: string;
    quantity: number;
    unit_price: number;
    sort_order: number;
    source_routine_consumption_id?: number | null;
    metadata?: Record<string, unknown> | null;
};

type ClientOption = { id: number; legal_name: string; is_active: boolean };

type InvoiceEvidenceRow = {
    id: number;
    kind: 'supporting' | 'sat_cfdi' | 'routine_report';
    generated_report_id?: number | null;
    original_name: string;
    mime_type?: string | null;
    size_bytes: number;
    download_url: string;
};

type RoutineReportOption = {
    id: number;
    routine_id: number;
    routine_execution_id: number;
    status: string;
    created_at?: string | null;
    label: string;
};

type Invoice = {
    id: number;
    status: string;
    number?: string | null;
    custom_reference?: string | null;
    subtotal: string;
    tax_total: string;
    total: string;
    currency: string;
    tax_rate_snapshot?: string | null;
    routine_id?: number;
    client_id?: number | null;
    notify_client_on_issue?: boolean;
    client_portal_visible?: boolean;
    delivery_deferred?: boolean;
    delivered_to_client_at?: string | null;
    issued_at?: string | null;
    client?: { id: number; legal_name: string; billing_email?: string | null } | null;
    lines: {
        line_type: LineType;
        description: string;
        quantity: string;
        unit_price: string;
        line_total: string;
        sort_order: number;
        metadata?: Record<string, unknown> | null;
        source_routine_consumption_id?: number | null;
    }[];
};

const route = useRoute();
const toast = useToast();
const { canWriteModule } = useModuleAccess();
const canWriteBilling = computed(() => canWriteModule('billing'));
const canEdit = computed(() => canWriteBilling.value);
const canIssue = computed(() => canWriteBilling.value);

const invoice = ref<Invoice | null>(null);
const evidences = ref<InvoiceEvidenceRow[]>([]);
const routineReportsAvailable = ref<RoutineReportOption[]>([]);
const selectedRoutineReportId = ref<number | null>(null);
const clients = ref<ClientOption[]>([]);
const clientId = ref<number | null>(null);
const customReference = ref('');
const editLines = ref<DraftLine[]>([]);
const loading = ref(true);
const saving = ref(false);
const issuing = ref(false);
const delivering = ref(false);
const notifyClient = ref(false);
const portalVisible = ref(false);
const deliveryDeferred = ref(false);
const issueActionLabel = ref('Emitir factura');
const fiscalEnabled = ref(false);
const fiscalProvider = ref<'sandbox' | 'mexico_pac'>('sandbox');

const supportingInput = ref<HTMLInputElement | null>(null);
const satInput = ref<HTMLInputElement | null>(null);
const evidenceUploading = ref(false);

const supportingEvidences = computed(() => evidences.value.filter((e) => e.kind === 'supporting'));
const routineReportEvidences = computed(() => evidences.value.filter((e) => e.kind === 'routine_report'));
const satEvidence = computed(() => evidences.value.find((e) => e.kind === 'sat_cfdi') ?? null);

const fiscalProviderLabel = computed(() =>
    fiscalProvider.value === 'mexico_pac' ? 'PAC México' : 'Sandbox',
);

const showFiscalReplaceWarning = computed(
    () => isDraft.value && fiscalEnabled.value && satEvidence.value != null,
);

const showFiscalAutoStampNote = computed(
    () => isDraft.value && fiscalEnabled.value && !satEvidence.value,
);

const isDraft = computed(() => invoice.value?.status === 'draft');
const canDeliverToClient = computed(
    () => !isDraft.value && canIssue.value && invoice.value?.status === 'issued' && invoice.value?.client_id != null,
);
const deliverOptionsSelected = computed(() => notifyClient.value || portalVisible.value);

function lineImport(line: Invoice['lines'][0], index: number): DraftLine {
    return {
        line_type: line.line_type ?? 'other',
        description: line.description,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        sort_order: line.sort_order ?? index,
        source_routine_consumption_id: line.source_routine_consumption_id ?? null,
        metadata: line.metadata ?? null,
    };
}

function syncLaborFromMeta(line: DraftLine) {
    if (line.line_type !== 'labor' || !line.metadata) {
        return;
    }
    const hours = Number(line.metadata.hours ?? 0);
    const rate = Number(line.metadata.rate_per_hour ?? 0);
    const workers = Number(line.metadata.workers ?? 1);
    if (hours > 0 && rate > 0) {
        line.unit_price = Math.round(hours * rate * workers * 100) / 100;
        line.quantity = 1;
    }
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function downloadEvidence(row: InvoiceEvidenceRow) {
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {};
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    try {
        const res = await fetch(row.download_url, { headers });
        if (!res.ok) {
            throw new Error('No se pudo descargar el archivo.');
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = row.original_name;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function attachRoutineReport() {
    if (!invoice.value || !canEdit.value || selectedRoutineReportId.value === null) {
        return;
    }
    evidenceUploading.value = true;
    try {
        const res = await api<{ data: InvoiceEvidenceRow }>(`/billing/invoices/${invoice.value.id}/evidences`, {
            method: 'POST',
            body: JSON.stringify({
                kind: 'routine_report',
                generated_report_id: selectedRoutineReportId.value,
            }),
        });
        evidences.value = [...evidences.value, res.data];
        routineReportsAvailable.value = routineReportsAvailable.value.filter(
            (r) => r.id !== selectedRoutineReportId.value,
        );
        selectedRoutineReportId.value = routineReportsAvailable.value[0]?.id ?? null;
        toast.success('Reporte de inspección adjunto.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        evidenceUploading.value = false;
    }
}

async function uploadEvidence(kind: 'supporting' | 'sat_cfdi', file: File) {
    if (!invoice.value || !canEdit.value) {
        return;
    }
    evidenceUploading.value = true;
    const body = new FormData();
    body.append('kind', kind);
    body.append('file', file);
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {};
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    try {
        const res = await fetch(`/api/v1/billing/invoices/${invoice.value.id}/evidences`, {
            method: 'POST',
            headers,
            body,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error((json as { message?: string }).message ?? 'Error al subir el archivo.');
        }
        const row = (json as { data: InvoiceEvidenceRow }).data;
        if (kind === 'sat_cfdi') {
            evidences.value = [...evidences.value.filter((e) => e.kind !== 'sat_cfdi'), row];
        } else {
            evidences.value = [...evidences.value, row];
        }
        toast.success(kind === 'sat_cfdi' ? 'Factura SAT actualizada.' : 'Evidencia agregada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        evidenceUploading.value = false;
    }
}

function onSupportingPicked(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (file) {
        void uploadEvidence('supporting', file);
    }
}

function onSatPicked(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (file) {
        void uploadEvidence('sat_cfdi', file);
    }
}

async function removeEvidence(row: InvoiceEvidenceRow) {
    if (!invoice.value || !canEdit.value) {
        return;
    }
    if (!window.confirm(`¿Quitar «${row.original_name}»?`)) {
        return;
    }
    try {
        await api(`/billing/invoices/${invoice.value.id}/evidences/${row.id}`, { method: 'DELETE' });
        evidences.value = evidences.value.filter((e) => e.id !== row.id);
        if (row.kind === 'routine_report' && row.generated_report_id) {
            routineReportsAvailable.value = [
                {
                    id: row.generated_report_id,
                    routine_id: invoice.value.routine_id ?? 0,
                    routine_execution_id: 0,
                    status: 'ready',
                    label: `Reporte #${row.generated_report_id}`,
                },
                ...routineReportsAvailable.value,
            ];
        }
        toast.success('Evidencia eliminada.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function downloadDeliveryPackage() {
    if (!invoice.value) {
        return;
    }
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {};
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    try {
        const res = await fetch(`/api/v1/billing/invoices/${invoice.value.id}/package`, { headers });
        if (!res.ok) {
            throw new Error('No se pudo descargar el paquete.');
        }
        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') ?? '';
        const match = disposition.match(/filename="?([^";]+)"?/);
        const filename = match?.[1] ?? `factura-${invoice.value.id}-paquete.zip`;
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function load() {
    loading.value = true;
    try {
        const [invRes, clientsRes, billingRes] = await Promise.all([
            api<{
                data: Invoice;
                evidences?: InvoiceEvidenceRow[];
                routine_reports_available?: RoutineReportOption[];
                workflow_action_labels?: { invoice_issued?: string };
            }>(`/billing/invoices/${route.params.id}`),
            api<{ data: ClientOption[] }>('/clients').catch(() => ({ data: [] as ClientOption[] })),
            api<{ data: { fiscal_enabled?: boolean; fiscal_provider?: string } }>('/billing/settings').catch(
                () => ({ data: {} }),
            ),
        ]);
        invoice.value = invRes.data;
        evidences.value = invRes.evidences ?? [];
        routineReportsAvailable.value = invRes.routine_reports_available ?? [];
        selectedRoutineReportId.value = routineReportsAvailable.value[0]?.id ?? null;
        issueActionLabel.value = invRes.workflow_action_labels?.invoice_issued?.trim() || 'Emitir factura';
        fiscalEnabled.value = Boolean(billingRes.data.fiscal_enabled);
        fiscalProvider.value =
            billingRes.data.fiscal_provider === 'mexico_pac' ? 'mexico_pac' : 'sandbox';
        clients.value = clientsRes.data.filter((c) => c.is_active);
        clientId.value = invRes.data.client_id ?? invRes.data.client?.id ?? null;
        customReference.value = invRes.data.custom_reference ?? '';
        notifyClient.value = Boolean(invRes.data.notify_client_on_issue);
        portalVisible.value = Boolean(invRes.data.client_portal_visible);
        deliveryDeferred.value = Boolean(invRes.data.delivery_deferred);
        editLines.value = invRes.data.lines.map(lineImport);
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function addLaborLine() {
    editLines.value.push({
        line_type: 'labor',
        description: 'Mano de obra',
        quantity: 1,
        unit_price: 0,
        sort_order: editLines.value.length,
        metadata: { workers: 1, hours: 1, rate_per_hour: 0 },
    });
}

function addOtherLine() {
    editLines.value.push({
        line_type: 'other',
        description: 'Concepto adicional',
        quantity: 1,
        unit_price: 0,
        sort_order: editLines.value.length,
    });
}

function removeLine(index: number) {
    editLines.value.splice(index, 1);
}

async function saveDraft(options: { quiet?: boolean } = {}) {
    if (!invoice.value || !canEdit.value) {
        return;
    }
    saving.value = true;
    editLines.value.forEach((line, i) => {
        line.sort_order = i;
        if (line.line_type === 'labor') {
            syncLaborFromMeta(line);
        }
    });
    try {
        const res = await api<{ data: Invoice }>(`/billing/invoices/${invoice.value.id}/draft`, {
            method: 'PUT',
            body: JSON.stringify({
                client_id: clientId.value,
                custom_reference: customReference.value.trim() || null,
                notify_client_on_issue: notifyClient.value,
                client_portal_visible: portalVisible.value,
                delivery_deferred: deliveryDeferred.value,
                lines: editLines.value,
            }),
        });
        invoice.value = res.data;
        editLines.value = res.data.lines.map(lineImport);
        if (!options.quiet) {
            toast.success('Prefactura guardada.');
        }
    } catch (e) {
        toast.error((e as Error).message);
        throw e;
    } finally {
        saving.value = false;
    }
}

async function issueInvoice() {
    if (!invoice.value || !canIssue.value) {
        return;
    }
    issuing.value = true;
    try {
        // Persistir borrador sin toast: el usuario pulsó emitir, no «guardar prefactura».
        await saveDraft({ quiet: true });
        const res = await api<{ data: Invoice }>(`/billing/invoices/${invoice.value!.id}/issue`, {
            method: 'POST',
            body: JSON.stringify({
                notify_client_on_issue: notifyClient.value,
                client_portal_visible: portalVisible.value,
                delivery_deferred: deliveryDeferred.value,
            }),
        });
        invoice.value = res.data;
        toast.success('Factura emitida.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        issuing.value = false;
    }
}

async function deliverToClient() {
    if (!invoice.value || !canDeliverToClient.value || !deliverOptionsSelected.value) {
        return;
    }
    delivering.value = true;
    try {
        const res = await api<{ data: Invoice }>(`/billing/invoices/${invoice.value.id}/deliver`, {
            method: 'POST',
            body: JSON.stringify({
                notify_client: notifyClient.value,
                client_portal_visible: portalVisible.value,
            }),
        });
        invoice.value = res.data;
        notifyClient.value = Boolean(res.data.notify_client_on_issue);
        portalVisible.value = Boolean(res.data.client_portal_visible);
        deliveryDeferred.value = Boolean(res.data.delivery_deferred);
        toast.success('Documentación enviada al cliente.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        delivering.value = false;
    }
}

const lineTypeLabel: Record<LineType, string> = {
    supply: 'Insumo',
    labor: 'Mano de obra',
    other: 'Otro',
};

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <RouterLink to="/app/billing" class="text-sm text-primary-700 underline">← Facturas</RouterLink>
        <PageHeader
            v-if="invoice"
            :title="
                isDraft
                    ? `Prefactura${invoice.custom_reference ? ` · ${invoice.custom_reference}` : ''}`
                    : `Factura${invoice.custom_reference ? ` · ${invoice.custom_reference}` : ''}`
            "
            :subtitle="`ID interno #${invoice.id}${invoice.number ? ` · Folio ${invoice.number}` : ''}`"
        />
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="!invoice" class="text-portal-muted text-sm">No se pudo cargar esta prefactura.</p>
        <template v-else>
            <GlassCard padding="lg" class="max-w-4xl">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <StatusBadge :status="invoice.status" />
                    <span class="text-sm text-slate-600">Servicio #{{ invoice.routine_id }}</span>
                </div>

                <section class="portal-form-panel mb-6 space-y-3 p-4">
                    <div>
                        <p class="text-portal-heading font-medium">Identificación</p>
                        <p class="text-portal-muted mt-1 text-xs">
                            El <strong class="text-portal-heading">ID #{{ invoice.id }}</strong> es fijo para búsquedas y
                            soporte. El nombre personalizado es opcional y aparece en listados, PDF y paquete ZIP.
                        </p>
                    </div>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-portal-muted text-xs">ID interno</dt>
                            <dd class="text-portal-heading font-semibold">#{{ invoice.id }}</dd>
                        </div>
                        <div v-if="invoice.number">
                            <dt class="text-portal-muted text-xs">Folio al emitir</dt>
                            <dd class="text-portal-heading">{{ invoice.number }}</dd>
                        </div>
                    </dl>
                    <MaterialField
                        v-if="isDraft && canEdit"
                        v-model="customReference"
                        label="Nombre o referencia personalizada"
                        placeholder="Ej. Proyecto Torre B · OC-4421"
                    />
                    <p v-if="isDraft && canEdit" class="text-portal-muted text-xs">
                        Se guarda con «Guardar prefactura». Puedes localizar esta factura por ID o por este nombre.
                    </p>
                    <p v-else-if="invoice.custom_reference" class="text-sm">
                        Nombre personalizado:
                        <strong class="text-portal-heading">{{ invoice.custom_reference }}</strong>
                    </p>
                    <p v-else-if="!isDraft" class="text-portal-muted text-sm">Sin nombre personalizado.</p>
                </section>

                <label v-if="isDraft && canEdit" class="mb-4 block max-w-md text-sm font-medium text-slate-700">
                    Cliente (requerido para emitir)
                    <select v-model="clientId" class="field-input mt-1 w-full">
                        <option :value="null">— Seleccionar —</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.legal_name }}
                        </option>
                    </select>
                </label>
                <p
                    v-if="invoice.client && !(isDraft && canEdit)"
                    class="mb-4 text-sm text-slate-700"
                >
                    Cliente: <strong>{{ invoice.client.legal_name }}</strong>
                </p>

                <section class="portal-form-panel mb-6 space-y-4 p-4">
                    <div>
                        <p class="text-portal-heading font-medium">Evidencias de respaldo</p>
                        <p class="text-portal-muted mt-1 text-xs">
                            Imágenes o documentos (PDF, Word, Excel). Máx. 10 MB por archivo.
                        </p>
                    </div>
                    <ul v-if="supportingEvidences.length" class="space-y-2 text-sm">
                        <li
                            v-for="ev in supportingEvidences"
                            :key="ev.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-white/10 px-3 py-2"
                        >
                            <button
                                type="button"
                                class="text-portal-link text-left underline"
                                @click="downloadEvidence(ev)"
                            >
                                {{ ev.original_name }}
                            </button>
                            <span class="text-portal-muted text-xs">{{ formatBytes(ev.size_bytes) }}</span>
                            <IconActionButton
                                v-if="isDraft && canEdit"
                                icon="trash"
                                label="Quitar evidencia"
                                variant="danger"
                                @click="removeEvidence(ev)"
                            />
                        </li>
                    </ul>
                    <p v-else class="text-portal-muted text-sm">Sin evidencias de respaldo.</p>
                    <div v-if="isDraft && canEdit" class="flex flex-wrap items-center gap-2">
                        <input
                            ref="supportingInput"
                            type="file"
                            class="hidden"
                            accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx"
                            :disabled="evidenceUploading"
                            @change="onSupportingPicked"
                        />
                        <AppButton
                            type="button"
                            variant="secondary"
                            :disabled="evidenceUploading"
                            @click="supportingInput?.click()"
                        >
                            {{ evidenceUploading ? 'Subiendo…' : 'Agregar evidencia' }}
                        </AppButton>
                    </div>

                    <div class="border-portal-border/30 border-t pt-4">
                        <p class="text-portal-heading font-medium">Reporte de inspección (servicio)</p>
                        <p class="text-portal-muted mt-1 text-xs">
                            Incluye el PDF generado al validar el servicio en el paquete ZIP (carpeta
                            <code class="text-xs">reportes/</code>).
                        </p>
                        <ul v-if="routineReportEvidences.length" class="mt-3 space-y-2 text-sm">
                            <li
                                v-for="ev in routineReportEvidences"
                                :key="ev.id"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-white/10 px-3 py-2"
                            >
                                <button
                                    type="button"
                                    class="text-portal-link text-left underline"
                                    @click="downloadEvidence(ev)"
                                >
                                    {{ ev.original_name }}
                                </button>
                                <span class="text-portal-muted text-xs">{{ formatBytes(ev.size_bytes) }}</span>
                                <IconActionButton
                                    v-if="isDraft && canEdit"
                                    icon="trash"
                                    label="Quitar reporte"
                                    variant="danger"
                                    @click="removeEvidence(ev)"
                                />
                            </li>
                        </ul>
                        <p v-else class="text-portal-muted mt-2 text-sm">Sin reporte de servicio adjunto.</p>
                        <div
                            v-if="isDraft && canEdit && routineReportsAvailable.length"
                            class="mt-3 flex flex-wrap items-end gap-2"
                        >
                            <label class="block min-w-[14rem] flex-1 text-sm">
                                <span class="text-portal-muted text-xs">Reportes listos en este servicio</span>
                                <select v-model="selectedRoutineReportId" class="field-input mt-1 w-full">
                                    <option
                                        v-for="opt in routineReportsAvailable"
                                        :key="opt.id"
                                        :value="opt.id"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </label>
                            <AppButton
                                type="button"
                                variant="secondary"
                                :disabled="evidenceUploading || selectedRoutineReportId === null"
                                @click="attachRoutineReport"
                            >
                                Adjuntar reporte
                            </AppButton>
                        </div>
                        <p
                            v-else-if="isDraft && canEdit && !routineReportsAvailable.length && !routineReportEvidences.length"
                            class="text-portal-muted mt-2 text-xs"
                        >
                            No hay reportes PDF listos para este servicio. Validal servicio o espera a que termine la
                            generación del informe.
                        </p>
                    </div>

                    <div class="border-portal-border/30 border-t pt-4">
                        <p class="text-portal-heading font-medium">Factura SAT (CFDI)</p>
                        <p class="text-portal-muted mt-1 text-xs">
                            Un solo archivo por prefactura: PDF o XML del comprobante fiscal timbrado.
                        </p>
                        <AlertBanner v-if="showFiscalReplaceWarning" variant="warning" class="mt-3">
                            El timbrado fiscal (<strong>{{ fiscalProviderLabel }}</strong>) está activo. Al emitir,
                            Phoenix generará un CFDI nuevo y <strong>reemplazará</strong> el archivo SAT que subiste
                            manualmente.
                        </AlertBanner>
                        <AlertBanner v-else-if="showFiscalAutoStampNote" variant="info" class="mt-3">
                            El timbrado fiscal (<strong>{{ fiscalProviderLabel }}</strong>) está activo: al emitir se
                            adjuntará el CFDI automáticamente. Si timbras fuera de Phoenix, desactiva el timbrado en
                            Configuración → Facturación.
                        </AlertBanner>
                        <div
                            v-if="satEvidence"
                            class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm"
                        >
                            <button
                                type="button"
                                class="text-portal-heading font-medium underline"
                                @click="downloadEvidence(satEvidence)"
                            >
                                {{ satEvidence.original_name }}
                            </button>
                            <span class="text-portal-muted text-xs">{{ formatBytes(satEvidence.size_bytes) }}</span>
                            <IconActionButton
                                v-if="isDraft && canEdit"
                                icon="trash"
                                label="Quitar factura SAT"
                                variant="danger"
                                @click="removeEvidence(satEvidence)"
                            />
                        </div>
                        <p v-else class="text-portal-muted mt-2 text-sm">Sin CFDI adjunto.</p>
                        <div v-if="isDraft && canEdit" class="mt-3">
                            <input
                                ref="satInput"
                                type="file"
                                class="hidden"
                                accept=".pdf,.xml,application/pdf,application/xml,text/xml"
                                :disabled="evidenceUploading"
                                @change="onSatPicked"
                            />
                            <AppButton
                                type="button"
                                variant="secondary"
                                :disabled="evidenceUploading"
                                @click="satInput?.click()"
                            >
                                {{ satEvidence ? 'Reemplazar factura SAT' : 'Subir factura SAT' }}
                            </AppButton>
                        </div>
                    </div>
                </section>

                <div v-if="isDraft && canEdit" class="space-y-3">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead>
                                <tr class="border-b text-slate-500">
                                    <th class="py-2 pr-2">Tipo</th>
                                    <th class="py-2 pr-2">Concepto</th>
                                    <th class="py-2 pr-2">Cant.</th>
                                    <th class="py-2 pr-2">P. unit.</th>
                                    <th class="py-2 pr-2" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(line, i) in editLines"
                                    :key="i"
                                    class="border-b border-slate-100 align-top"
                                >
                                    <td class="py-2 pr-2 text-xs text-slate-500">
                                        {{ lineTypeLabel[line.line_type] }}
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input v-model="line.description" class="field-input w-full min-w-[10rem]" />
                                        <div
                                            v-if="line.line_type === 'labor'"
                                            class="mt-2 grid grid-cols-3 gap-1 text-xs"
                                        >
                                            <input
                                                v-model.number="(line.metadata as any).workers"
                                                type="number"
                                                min="1"
                                                placeholder="Pers."
                                                class="field-input"
                                                title="Trabajadores"
                                            />
                                            <input
                                                v-model.number="(line.metadata as any).hours"
                                                type="number"
                                                min="0"
                                                step="0.25"
                                                placeholder="Horas"
                                                class="field-input"
                                            />
                                            <input
                                                v-model.number="(line.metadata as any).rate_per_hour"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="$/h"
                                                class="field-input"
                                            />
                                        </div>
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input
                                            v-model.number="line.quantity"
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            class="field-input w-20"
                                            :disabled="line.line_type === 'labor'"
                                        />
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input
                                            v-model.number="line.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="field-input w-28"
                                        />
                                    </td>
                                    <td class="table-row-actions py-2">
                                        <IconActionButton
                                            icon="trash"
                                            label="Quitar línea"
                                            variant="danger"
                                            @click="removeLine(i)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="addLaborLine">
                            + Mano de obra
                        </button>
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="addOtherLine">
                            + Concepto
                        </button>
                    </div>
                    <div v-if="isDraft && canEdit" class="portal-form-panel space-y-3 p-4 text-sm">
                        <p class="text-portal-heading font-medium">Entrega al cliente</p>
                        <label class="flex items-start gap-2">
                            <input v-model="notifyClient" type="checkbox" class="mt-1" />
                            <span>Notificar por email al emitir</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input v-model="portalVisible" type="checkbox" class="mt-1" />
                            <span>Visible en portal del cliente (descarga ZIP con PDF y evidencias)</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input v-model="deliveryDeferred" type="checkbox" class="mt-1" />
                            <span>Diferir envío (guardar intención para después)</span>
                        </label>
                    </div>
                    <AlertBanner v-if="showFiscalReplaceWarning" variant="warning" class="mb-2">
                        Al emitir se timbrará con {{ fiscalProviderLabel }} y se sustituirá el CFDI manual adjunto.
                    </AlertBanner>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <AppButton type="button" :disabled="saving" @click="saveDraft()">
                            {{ saving ? 'Guardando…' : 'Guardar prefactura' }}
                        </AppButton>
                        <AppButton
                            v-if="canIssue"
                            type="button"
                            variant="secondary"
                            :disabled="issuing || !clientId"
                            @click="issueInvoice"
                        >
                            {{ issuing ? 'Emitiendo…' : issueActionLabel }}
                        </AppButton>
                    </div>
                </div>

                <table v-else class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-slate-500">
                            <th class="py-2">Concepto</th>
                            <th>Cant.</th>
                            <th>P. unit.</th>
                            <th>Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, i) in invoice.lines" :key="i" class="border-b border-slate-100">
                            <td class="py-2">
                                <span class="text-xs text-slate-400">{{ lineTypeLabel[line.line_type] }} · </span>
                                {{ line.description }}
                            </td>
                            <td>{{ line.quantity }}</td>
                            <td>${{ line.unit_price }}</td>
                            <td>${{ line.line_total }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="!isDraft" class="mt-4 space-y-4">
                    <AppButton type="button" variant="secondary" @click="downloadDeliveryPackage">
                        Descargar paquete ZIP (PDF + evidencias)
                    </AppButton>

                    <div v-if="canDeliverToClient" class="portal-form-panel space-y-3 p-4 text-sm">
                        <p class="text-portal-heading font-medium">Entrega de documentación al cliente</p>
                        <p v-if="invoice.delivered_to_client_at" class="text-portal-muted text-xs">
                            Última entrega registrada:
                            {{ new Date(invoice.delivered_to_client_at).toLocaleString('es-MX') }}
                            <span v-if="invoice.delivery_deferred"> (emisión con envío diferido)</span>
                        </p>
                        <p v-else-if="invoice.delivery_deferred" class="text-portal-muted text-xs">
                            Emitiste con envío diferido; configura las opciones y envía cuando el cliente esté listo.
                        </p>
                        <p v-else class="text-portal-muted text-xs">
                            Si al emitir no notificaste al cliente ni habilitaste el portal, puedes hacerlo ahora.
                        </p>
                        <label class="flex items-start gap-2">
                            <input v-model="notifyClient" type="checkbox" class="mt-1" />
                            <span>Notificar por email (paquete y enlace al portal si aplica)</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <input v-model="portalVisible" type="checkbox" class="mt-1" />
                            <span>Visible en portal del cliente (descarga ZIP con PDF y evidencias)</span>
                        </label>
                        <AppButton
                            type="button"
                            :disabled="delivering || !deliverOptionsSelected"
                            @click="deliverToClient"
                        >
                            {{ delivering ? 'Enviando…' : 'Enviar documentación al cliente' }}
                        </AppButton>
                    </div>
                </div>

                <dl class="mt-6 space-y-1 text-sm text-right">
                    <div class="flex justify-end gap-8">
                        <dt class="text-slate-500">Subtotal</dt>
                        <dd>${{ invoice.subtotal }} {{ invoice.currency }}</dd>
                    </div>
                    <div class="flex justify-end gap-8">
                        <dt class="text-slate-500">
                            IVA
                            <span v-if="invoice.tax_rate_snapshot" class="text-xs">
                                ({{ (Number(invoice.tax_rate_snapshot) * 100).toFixed(1) }}%)
                            </span>
                        </dt>
                        <dd>${{ invoice.tax_total }}</dd>
                    </div>
                    <div class="flex justify-end gap-8 text-base font-semibold">
                        <dt>Total</dt>
                        <dd>${{ invoice.total }}</dd>
                    </div>
                </dl>
            </GlassCard>
        </template>
    </div>
</template>
