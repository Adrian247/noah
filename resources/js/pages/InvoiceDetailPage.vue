<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';

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

type Invoice = {
    id: number;
    status: string;
    number?: string | null;
    subtotal: string;
    tax_total: string;
    total: string;
    currency: string;
    tax_rate_snapshot?: string | null;
    routine_id?: number;
    client_id?: number | null;
    client?: { id: number; legal_name: string } | null;
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
const { canWriteModule } = useModuleAccess();
const canWriteBilling = computed(() => canWriteModule('billing'));
const canEdit = computed(() => canWriteBilling.value);
const canIssue = computed(() => canWriteBilling.value);

const invoice = ref<Invoice | null>(null);
const clients = ref<ClientOption[]>([]);
const clientId = ref<number | null>(null);
const editLines = ref<DraftLine[]>([]);
const loading = ref(true);
const saving = ref(false);
const issuing = ref(false);
const error = ref<string | null>(null);
const message = ref<string | null>(null);

const isDraft = computed(() => invoice.value?.status === 'draft');

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

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [invRes, clientsRes] = await Promise.all([
            api<{ data: Invoice }>(`/billing/invoices/${route.params.id}`),
            api<{ data: ClientOption[] }>('/clients').catch(() => ({ data: [] as ClientOption[] })),
        ]);
        invoice.value = invRes.data;
        clients.value = clientsRes.data.filter((c) => c.is_active);
        clientId.value = invRes.data.client_id ?? invRes.data.client?.id ?? null;
        editLines.value = invRes.data.lines.map(lineImport);
    } catch (e) {
        error.value = (e as Error).message;
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

async function saveDraft() {
    if (!invoice.value || !canEdit.value) {
        return;
    }
    saving.value = true;
    message.value = null;
    error.value = null;
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
                lines: editLines.value,
            }),
        });
        invoice.value = res.data;
        editLines.value = res.data.lines.map(lineImport);
        message.value = 'Prefactura guardada.';
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

async function issueInvoice() {
    if (!invoice.value || !canIssue.value) {
        return;
    }
    issuing.value = true;
    error.value = null;
    try {
        await saveDraft();
        const res = await api<{ data: Invoice }>(`/billing/invoices/${invoice.value!.id}/issue`, {
            method: 'POST',
        });
        invoice.value = res.data;
        message.value = 'Factura emitida.';
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        issuing.value = false;
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
            :title="isDraft ? `Prefactura #${invoice.id}` : `Factura #${invoice.id}`"
            :subtitle="invoice.number ? `Folio ${invoice.number}` : 'Sin folio fiscal'"
        />
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <AlertBanner v-else-if="error" variant="danger">{{ error }}</AlertBanner>
        <template v-else-if="invoice">
            <AlertBanner v-if="message" variant="success">{{ message }}</AlertBanner>
            <GlassCard padding="lg" class="max-w-4xl">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <StatusBadge :status="invoice.status" />
                    <span class="text-sm text-slate-600">Rutina #{{ invoice.routine_id }}</span>
                </div>

                <label v-if="isDraft && canEdit" class="mb-4 block max-w-md text-sm font-medium text-slate-700">
                    Cliente (requerido para emitir)
                    <select v-model="clientId" class="field-input mt-1 w-full">
                        <option :value="null">— Seleccionar —</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.legal_name }}
                        </option>
                    </select>
                </label>
                <p v-else-if="invoice.client" class="mb-4 text-sm text-slate-700">
                    Cliente: <strong>{{ invoice.client.legal_name }}</strong>
                </p>

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
                                    <td class="py-2">
                                        <button
                                            type="button"
                                            class="text-red-600 text-xs"
                                            @click="removeLine(i)"
                                        >
                                            Quitar
                                        </button>
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
                    <div class="flex flex-wrap gap-2 pt-2">
                        <AppButton type="button" :disabled="saving" @click="saveDraft">
                            {{ saving ? 'Guardando…' : 'Guardar prefactura' }}
                        </AppButton>
                        <AppButton
                            v-if="canIssue"
                            type="button"
                            variant="secondary"
                            :disabled="issuing || !clientId"
                            @click="issueInvoice"
                        >
                            {{ issuing ? 'Emitiendo…' : 'Emitir factura' }}
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
