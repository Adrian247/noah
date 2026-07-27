<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { useToast } from '@/composables/useToast';
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
const toast = useToast();
const invoices = ref<Invoice[]>([]);
const loading = ref(true);

const canManage = computed(
    () => company.current?.role === 'administrator' || company.current?.role === 'billing',
);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Invoice[] }>('/billing/invoices');
        invoices.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function issue(id: number) {
    if (!canManage.value) {
        toast.error('Solo facturación o administrador pueden emitir.');
        return;
    }
    try {
        await api(`/billing/invoices/${id}/issue`, { method: 'POST' });
        toast.success(`Factura #${id} emitida.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <PageHeader
            title="Facturación"
            subtitle="Borradores generados al validar rutinas. Revisa el desglose antes de emitir."
        />
        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink v-if="canManage" to="/app/settings#facturacion">
                <AppButton variant="secondary">IVA y mano de obra</AppButton>
            </RouterLink>
        </div>
        <AlertBanner v-if="!canManage" variant="info" class="mb-4">
            Estás en modo consulta. Para emitir facturas usa <strong>facturacion@noah.local</strong>.
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">
            <p class="text-portal-muted">Cargando facturas…</p>
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
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="invoice-list-title">Factura #{{ inv.id }}</h2>
                        <StatusBadge :status="inv.status" />
                    </div>
                    <p class="text-portal-muted text-sm">Rutina #{{ inv.routine_id ?? '—' }}</p>
                    <p class="text-portal-muted mt-1 text-sm">
                        Subtotal ${{ inv.subtotal }} · IVA ${{ inv.tax_total }} ·
                        <strong class="text-portal-heading">Total ${{ inv.total }}</strong>
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
            <p v-if="invoices.length === 0" class="text-portal-muted text-sm">No hay facturas aún.</p>
        </div>
    </div>
</template>
