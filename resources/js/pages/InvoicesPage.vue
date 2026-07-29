<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { hasCompanyAdministratorAccess } from '@/lib/sessionCompany';
import { useToast } from '@/composables/useToast';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
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
    custom_reference?: string | null;
    subtotal?: string;
    tax_total?: string;
    total: string;
    routine_id?: number;
    lines?: InvoiceLine[];
};

const company = useCompanyStore();
const toast = useToast();
const router = useRouter();
const invoices = ref<Invoice[]>([]);
const loading = ref(true);
const searchQuery = ref('');

const canManage = computed(() => {
    const role = company.current?.role;
    return hasCompanyAdministratorAccess(role) || role === 'billing';
});

const billingContactEmail = computed(
    () => company.current?.billing_contact_email?.trim() || null,
);

async function load() {
    loading.value = true;
    try {
        const q = searchQuery.value.trim();
        const path = q ? `/billing/invoices?search=${encodeURIComponent(q)}` : '/billing/invoices';
        const res = await api<{ data: Invoice[] }>(path);
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
    <div class="portal-page" data-tour="page-billing">
        <PageHeader
            title="Facturación"
            subtitle="Borradores generados al validar rutinas. Revisa el desglose antes de emitir."
        />
        <div class="mb-4 flex flex-wrap gap-2">
            <RouterLink v-if="canManage" to="/app/settings#facturacion">
                <AppButton variant="secondary">IVA y mano de obra</AppButton>
            </RouterLink>
        </div>
        <div class="mb-4 flex max-w-md flex-wrap items-end gap-2">
            <label class="block flex-1 text-sm">
                <span class="text-portal-muted text-xs">Buscar por ID, folio o nombre personalizado</span>
                <input
                    v-model="searchQuery"
                    type="search"
                    class="field-input mt-1 w-full"
                    placeholder="Ej. 42 o Proyecto Torre B"
                    @keydown.enter="load"
                />
            </label>
            <AppButton type="button" variant="secondary" @click="load">Buscar</AppButton>
        </div>
        <AlertBanner v-if="!canManage" variant="info" class="mb-4">
            Estás en modo consulta.
            <template v-if="billingContactEmail">
                Para emitir facturas usa <strong>{{ billingContactEmail }}</strong
                ><span v-if="company.current?.name"> ({{ company.current.name }})</span>.
            </template>
            <template v-else>
                Para emitir facturas solicita acceso al rol de facturación de tu empresa.
            </template>
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
                        <h2 class="invoice-list-title">
                            Factura #{{ inv.id }}
                            <span v-if="inv.custom_reference" class="text-portal-muted font-normal">
                                · {{ inv.custom_reference }}
                            </span>
                        </h2>
                        <StatusBadge :status="inv.status" />
                    </div>
                    <p class="text-portal-muted text-sm">Rutina #{{ inv.routine_id ?? '—' }}</p>
                    <p class="text-portal-muted mt-1 text-sm">
                        Subtotal ${{ inv.subtotal }} · IVA ${{ inv.tax_total }} ·
                        <strong class="text-portal-heading">Total ${{ inv.total }}</strong>
                    </p>
                </div>
                <div class="table-row-actions">
                    <IconActionButton
                        icon="eye"
                        label="Ver detalle de factura"
                        @click="router.push(`/app/billing/${inv.id}`)"
                    />
                    <IconActionButton
                        v-if="inv.status === 'draft' && canManage"
                        icon="send"
                        label="Emitir factura"
                        @click="issue(inv.id)"
                    />
                </div>
            </GlassCard>
            <p v-if="invoices.length === 0" class="text-portal-muted text-sm">No hay facturas aún.</p>
        </div>
    </div>
</template>
