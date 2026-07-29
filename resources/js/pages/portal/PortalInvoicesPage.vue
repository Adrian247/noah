<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { usePortalInvoiceDownload } from '@/composables/usePortalInvoiceDownload';
import { formatPortalDateTime, formatPortalMoney } from '@/lib/clientPortal';
import PageHeader from '@/components/ui/PageHeader.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ClientPortalEmptyState from '@/components/portal/ClientPortalEmptyState.vue';

type InvoiceRow = {
    id: number;
    number?: string | null;
    custom_reference?: string | null;
    total: string;
    currency: string;
    issued_at?: string | null;
    routine_id?: number | null;
};

const toast = useToast();
const { downloadingId, downloadInvoicePackage } = usePortalInvoiceDownload();
const items = ref<InvoiceRow[]>([]);
const loading = ref(true);

const totalCount = computed(() => items.value.length);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: InvoiceRow[] }>('/portal/invoices');
        items.value = res.data ?? [];
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function invoiceTitle(inv: InvoiceRow): string {
    return inv.custom_reference?.trim() || inv.number?.trim() || `Factura #${inv.id}`;
}

onMounted(load);
</script>

<template>
    <div class="client-portal-page">
        <PageHeader
            title="Mis facturas"
            subtitle="Documentos emitidos y habilitados para tu organización. Cada paquete ZIP incluye el PDF de factura, evidencias y reportes que tu proveedor haya adjuntado."
        />

        <div v-if="!loading && totalCount > 0" class="client-portal-kpi-strip mb-6">
            <GlassCard padding="sm" class="client-portal-kpi">
                <p class="client-portal-kpi__value">{{ totalCount }}</p>
                <p class="client-portal-kpi__label">Facturas disponibles</p>
            </GlassCard>
        </div>

        <GlassCard v-if="loading" padding="lg">
            <p class="text-portal-muted animate-pulse text-sm">Cargando facturas…</p>
        </GlassCard>

        <div v-else-if="items.length" class="client-portal-invoice-grid">
            <GlassCard
                v-for="inv in items"
                :key="inv.id"
                padding="lg"
                hover
                class="client-portal-invoice-card"
            >
                <div class="client-portal-invoice-card__head">
                    <span class="client-portal-id-badge">ID #{{ inv.id }}</span>
                    <p class="client-portal-invoice-card__title">{{ invoiceTitle(inv) }}</p>
                    <p v-if="inv.number && inv.custom_reference" class="text-portal-muted text-xs">
                        Folio {{ inv.number }}
                    </p>
                </div>
                <dl class="client-portal-invoice-card__meta">
                    <div>
                        <dt>Emitida</dt>
                        <dd>{{ formatPortalDateTime(inv.issued_at) }}</dd>
                    </div>
                    <div v-if="inv.routine_id">
                        <dt>Rutina</dt>
                        <dd>
                            <RouterLink
                                class="text-portal-link underline"
                                :to="`/portal/routines/${inv.routine_id}`"
                            >
                                #{{ inv.routine_id }}
                            </RouterLink>
                        </dd>
                    </div>
                    <div>
                        <dt>Total</dt>
                        <dd class="client-portal-invoice-card__total">
                            {{ formatPortalMoney(inv.total, inv.currency) }}
                        </dd>
                    </div>
                </dl>
                <p class="text-portal-muted mt-3 text-xs leading-relaxed">
                    Paquete ZIP: PDF + evidencias + CFDI y reportes cuando apliquen.
                </p>
                <AppButton
                    type="button"
                    class="mt-4 w-full sm:w-auto"
                    :disabled="downloadingId === inv.id"
                    @click="downloadInvoicePackage(inv.id)"
                >
                    {{
                        downloadingId === inv.id ? 'Preparando descarga…' : 'Descargar paquete completo'
                    }}
                </AppButton>
            </GlassCard>
        </div>

        <GlassCard v-else padding="lg">
            <ClientPortalEmptyState
                title="Aún no hay facturas visibles"
                description="Cuando tu proveedor emita una factura y la habilite para tu cuenta, aparecerá aquí con su paquete de documentación listo para descargar."
            />
        </GlassCard>
    </div>
</template>
