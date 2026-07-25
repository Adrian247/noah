<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

type InvoiceLine = {
    description: string;
    quantity: string | number;
    unit_price: string | number;
    line_total: string | number;
};

type Invoice = {
    id: number;
    status: string;
    subtotal?: string;
    tax_total?: string;
    total: string;
    routine_id?: number;
    lines?: InvoiceLine[];
};

const company = useCompanyStore();
const invoices = ref<Invoice[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const canManage = computed(
    () => company.current?.role === 'administrator' || company.current?.role === 'billing',
);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await api<{ data: Invoice[] }>('/billing/invoices');
        invoices.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function issue(id: number) {
    error.value = null;
    success.value = null;
    if (!canManage.value) {
        error.value = 'Solo facturación o administrador pueden emitir.';
        return;
    }
    try {
        await api(`/billing/invoices/${id}/issue`, { method: 'POST' });
        success.value = `Factura #${id} emitida.`;
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PageHeader
            title="Facturación"
            subtitle="Borradores generados al validar rutinas. Revisa el desglose antes de emitir."
        />
        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink v-if="canManage" to="/app/billing/settings">
                <AppButton variant="secondary">Configuración (IVA y mano de obra)</AppButton>
            </RouterLink>
        </div>
        <AlertBanner v-if="error" variant="danger" class="mb-4">{{ error }}</AlertBanner>
        <AlertBanner v-if="success" variant="success" class="mb-4">{{ success }}</AlertBanner>
        <AlertBanner v-if="!canManage" variant="info" class="mb-4">
            Estás en modo consulta. Para emitir facturas usa <strong>facturacion@noah.local</strong>.
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">
            <p class="text-slate-500">Cargando facturas…</p>
        </GlassCard>
        <div v-else class="space-y-3">
            <GlassCard
                v-for="inv in invoices"
                :key="inv.id"
                padding="md"
                hover
                class="flex flex-wrap items-center justify-between gap-4"
            >
                <div>
                    <p class="font-medium text-slate-900">
                        Factura #{{ inv.id }}
                        <StatusBadge :status="inv.status" class="ml-2" />
                    </p>
                    <p class="text-sm text-slate-600">Rutina #{{ inv.routine_id ?? '—' }}</p>
                    <p class="mt-1 text-sm">
                        Subtotal ${{ inv.subtotal }} · IVA ${{ inv.tax_total }} ·
                        <strong>Total ${{ inv.total }}</strong>
                    </p>
                </div>
                <div class="flex gap-2">
                    <RouterLink :to="`/app/billing/${inv.id}`">
                        <AppButton variant="secondary">Ver detalle</AppButton>
                    </RouterLink>
                    <AppButton
                        v-if="inv.status === 'draft' && canManage"
                        @click="issue(inv.id)"
                    >
                        Emitir
                    </AppButton>
                </div>
            </GlassCard>
            <p v-if="invoices.length === 0" class="text-sm text-slate-600">No hay facturas aún.</p>
        </div>
    </div>
</template>
