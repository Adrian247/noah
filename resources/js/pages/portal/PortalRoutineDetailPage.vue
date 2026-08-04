<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { api, getCompanyId, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { usePortalInvoiceDownload } from '@/composables/usePortalInvoiceDownload';
import {
    formatPortalDateTime,
    formatPortalMoney,
    reportStatusLabel,
} from '@/lib/clientPortal';
import PageHeader from '@/components/ui/PageHeader.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ClientPortalWorkflowTimeline from '@/components/portal/ClientPortalWorkflowTimeline.vue';

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

type GeneratedReportRow = {
    id: number;
    status: string;
    created_at?: string | null;
};

type RoutineDetail = {
    id: number;
    status: string;
    asset?: {
        tag: string;
        serial_number?: string | null;
        catalog_item?: { name?: string };
        site?: { name?: string };
    };
    routine_type?: { name: string };
    latest_execution?: Execution | null;
    executions?: Execution[];
    invoice?: {
        id: number;
        number?: string | null;
        custom_reference?: string | null;
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
    generated_reports?: GeneratedReportRow[];
};

const route = useRoute();
const toast = useToast();
const { downloadingId, downloadInvoicePackage } = usePortalInvoiceDownload();
const routine = ref<RoutineDetail | null>(null);
const loading = ref(true);
const downloadingReportId = ref<number | null>(null);

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

const pageTitle = computed(() => routine.value?.routine_type?.name ?? 'Detalle del servicio');

const pageSubtitle = computed(() => {
    if (!routine.value) {
        return '';
    }
    const parts = [`Servicio #${routine.value.id}`];
    if (routine.value.asset?.tag) {
        parts.push(`Activo ${routine.value.asset.tag}`);
    }
    if (routine.value.asset?.serial_number) {
        parts.push(`Serie ${routine.value.asset.serial_number}`);
    }
    return parts.join(' · ');
});

function invoiceDisplayLabel(inv: NonNullable<RoutineDetail['invoice']>): string {
    return inv.custom_reference?.trim() || inv.number?.trim() || `Factura #${inv.id}`;
}

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

async function downloadReport(reportId: number) {
    if (!routine.value || downloadingReportId.value !== null) {
        return;
    }
    downloadingReportId.value = reportId;
    try {
        const res = await fetch(
            `/api/v1/portal/routines/${routine.value.id}/reports/${reportId}/download`,
            {
                headers: {
                    Authorization: `Bearer ${getToken()}`,
                    'X-Company-Id': getCompanyId() ?? '',
                },
            },
        );
        if (!res.ok) {
            throw new Error('No se pudo descargar el informe.');
        }
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `informe-servicio-${routine.value.id}-${reportId}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success('Informe descargado.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        downloadingReportId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="client-portal-page">
        <RouterLink to="/portal/routines" class="client-portal-back">← Volver a servicios</RouterLink>

        <GlassCard v-if="loading" padding="lg" class="mt-4">
            <p class="text-portal-muted animate-pulse text-sm">Cargando detalle…</p>
        </GlassCard>

        <template v-else-if="routine">
            <PageHeader :title="pageTitle" :subtitle="pageSubtitle" />

            <div class="mb-6 flex flex-wrap items-center gap-3">
                <span class="client-portal-id-badge">ID servicio #{{ routine.id }}</span>
                <StatusBadge :status="routine.status" />
            </div>

            <div class="client-portal-detail-grid">
                <GlassCard padding="lg" class="client-portal-detail-grid__main">
                    <h2 class="text-portal-heading text-base font-semibold">Equipo atendido</h2>
                    <dl class="client-portal-dl mt-4">
                        <div>
                            <dt>Etiqueta</dt>
                            <dd>{{ routine.asset?.tag ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Número de serie</dt>
                            <dd>{{ routine.asset?.serial_number ?? '—' }}</dd>
                        </div>
                        <div v-if="routine.asset?.catalog_item?.name">
                            <dt>Modelo / catálogo</dt>
                            <dd>{{ routine.asset.catalog_item.name }}</dd>
                        </div>
                        <div v-if="routine.asset?.site?.name">
                            <dt>Sitio</dt>
                            <dd>{{ routine.asset.site.name }}</dd>
                        </div>
                    </dl>
                </GlassCard>

                <GlassCard
                    v-if="routine.workflow_instance?.transitions?.length"
                    padding="lg"
                    class="client-portal-detail-grid__side"
                >
                    <h2 class="text-portal-heading text-base font-semibold">Trazabilidad del servicio</h2>
                    <p class="text-portal-muted mt-1 text-xs">
                        Línea de tiempo auditada de cada paso del workflow.
                    </p>
                    <ClientPortalWorkflowTimeline
                        class="mt-4"
                        :current-step-key="routine.workflow_instance.current_step_key"
                        :transitions="routine.workflow_instance.transitions ?? []"
                    />
                </GlassCard>

                <GlassCard v-if="routine.invoice" padding="lg" class="client-portal-detail-grid__span">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-portal-heading text-base font-semibold">Facturación</h2>
                            <p class="text-portal-muted mt-1 text-sm">
                                {{ invoiceDisplayLabel(routine.invoice) }}
                            </p>
                            <p class="text-portal-muted text-xs">
                                ID factura #{{ routine.invoice.id }}
                                <span v-if="routine.invoice.number"> · Folio {{ routine.invoice.number }}</span>
                            </p>
                            <p
                                v-if="routine.invoice.total && routine.invoice.currency"
                                class="text-portal-heading mt-2 text-xl font-bold tabular-nums"
                            >
                                {{ formatPortalMoney(routine.invoice.total, routine.invoice.currency) }}
                            </p>
                            <p v-if="routine.invoice.issued_at" class="text-portal-muted mt-1 text-xs">
                                Emitida {{ formatPortalDateTime(routine.invoice.issued_at) }}
                            </p>
                        </div>
                        <AppButton
                            type="button"
                            :disabled="downloadingId === routine.invoice.id"
                            @click="downloadInvoicePackage(routine.invoice!.id)"
                        >
                            {{
                                downloadingId === routine.invoice.id
                                    ? 'Preparando ZIP…'
                                    : 'Descargar paquete ZIP'
                            }}
                        </AppButton>
                    </div>
                </GlassCard>

                <GlassCard
                    v-if="routine.generated_reports?.length"
                    padding="lg"
                    class="client-portal-detail-grid__span"
                >
                    <h2 class="text-portal-heading text-base font-semibold">Informes de inspección</h2>
                    <p class="text-portal-muted mt-1 text-sm">
                        PDF generados a partir de la ejecución validada en campo.
                    </p>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="rep in routine.generated_reports"
                            :key="rep.id"
                            class="client-portal-report-row"
                        >
                            <div>
                                <p class="text-portal-heading text-sm font-medium">Informe #{{ rep.id }}</p>
                                <p class="text-portal-muted text-xs">
                                    {{ reportStatusLabel(rep.status) }}
                                    <span v-if="rep.created_at">
                                        · {{ formatPortalDateTime(rep.created_at) }}
                                    </span>
                                </p>
                            </div>
                            <AppButton
                                v-if="rep.status === 'ready'"
                                type="button"
                                variant="secondary"
                                :disabled="downloadingReportId === rep.id"
                                @click="downloadReport(rep.id)"
                            >
                                {{ downloadingReportId === rep.id ? 'Descargando…' : 'PDF' }}
                            </AppButton>
                            <span v-else class="text-portal-muted text-xs">No disponible aún</span>
                        </li>
                    </ul>
                </GlassCard>

                <GlassCard padding="lg" class="client-portal-detail-grid__span">
                    <h2 class="text-portal-heading text-base font-semibold">Visitas y ejecuciones</h2>
                    <ul v-if="executionHistory.length" class="mt-4 divide-y divide-white/10">
                        <li v-for="ex in executionHistory" :key="ex.id" class="py-4 first:pt-0 last:pb-0">
                            <p class="text-portal-muted text-xs font-medium uppercase tracking-wide">
                                Ejecución #{{ ex.id }}
                                <span v-if="ex.status"> · {{ ex.status }}</span>
                            </p>
                            <p v-if="ex.submitted_at" class="text-portal-muted mt-2 text-sm">
                                Enviada {{ formatPortalDateTime(ex.submitted_at) }}
                            </p>
                            <p v-if="ex.validated_at" class="text-portal-muted text-sm">
                                Validada {{ formatPortalDateTime(ex.validated_at) }}
                            </p>
                            <blockquote
                                v-if="ex.technician_comments"
                                class="text-portal-heading mt-3 border-l-2 border-amber-500/40 pl-3 text-sm italic"
                            >
                                {{ ex.technician_comments }}
                            </blockquote>
                        </li>
                    </ul>
                    <p v-else class="text-portal-muted mt-3 text-sm">Sin ejecuciones registradas.</p>
                </GlassCard>
            </div>
        </template>

        <GlassCard v-else padding="lg" class="mt-4">
            <p class="text-portal-muted text-sm">Servicio no encontrado o sin acceso para tu cuenta.</p>
        </GlassCard>
    </div>
</template>
