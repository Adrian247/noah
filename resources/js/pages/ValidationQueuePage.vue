<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/composables/usePermissions';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import { routinesSectionNav } from '@/lib/sectionNav';

type Routine = {
    id: number;
    status: string;
    asset?: { tag: string };
    site?: { name: string };
    routine_type?: { name: string };
    assignee?: { name: string } | null;
    latest_execution?: {
        submitted_at?: string | null;
        technician_comments?: string | null;
        duration_minutes?: number | null;
    } | null;
};

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { can } = usePermissions();

const routines = ref<Routine[]>([]);
const loading = ref(true);
const validatingId = ref<number | null>(null);
const rejecting = ref(false);
const rejectTarget = ref<Routine | null>(null);
const rejectReason = ref('');

const canValidate = computed(() => can('routines.validate'));

const columns = computed((): TableColumnDef[] => [
    { id: 'routine', label: 'Servicio', cellClass: 'py-3' },
    { id: 'asset', label: 'Activo' },
    { id: 'technician', label: 'Técnico' },
    { id: 'submitted', label: 'Enviado' },
    tableActionsColumn({
        headerClass: 'portal-table-col-actions portal-table-col-actions--multi',
        cellClass: 'portal-table-col-actions portal-table-col-actions--multi py-3 text-right',
    }),
]);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Routine[] }>(
            '/routines?status=pending_validation&per_page=100',
        );
        routines.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openRoutine(id: number) {
    void router.push(`/app/services/${id}`);
}

function formatSubmittedAt(value?: string | null) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString();
}

async function quickValidate(row: Routine) {
    const accepted = await confirm(
        `¿Validar el servicio #${row.id} (${row.routine_type?.name ?? 'sin tipo'})? Se generará reporte y borrador de factura.`,
        { title: 'Validar ejecución', confirmLabel: 'Validar' },
    );
    if (!accepted) {
        return;
    }

    validatingId.value = row.id;
    try {
        await api(`/routines/${row.id}/validate`, { method: 'POST' });
        toast.success(`Servicio #${row.id} validado.`);
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        validatingId.value = null;
    }
}

function openReject(row: Routine) {
    rejectTarget.value = row;
    rejectReason.value = '';
}

function closeReject() {
    if (rejecting.value) {
        return;
    }
    rejectTarget.value = null;
    rejectReason.value = '';
}

async function confirmReject() {
    const row = rejectTarget.value;
    if (!row) {
        return;
    }
    const reason = rejectReason.value.trim();
    if (!reason) {
        toast.warning('Indica el motivo del rechazo.');
        return;
    }

    rejecting.value = true;
    try {
        await api(`/routines/${row.id}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });
        toast.success(`Servicio #${row.id} devuelto al técnico.`);
        rejectTarget.value = null;
        rejectReason.value = '';
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        rejecting.value = false;
    }
}

onMounted(() => {
    if (!canValidate.value) {
        void router.replace('/app/services');
        return;
    }
    void load();
});
</script>

<template>
    <div class="portal-page" data-tour="page-validation-queue">
        <SectionSubnav :items="routinesSectionNav" />
        <PageHeader
            title="Cola de validación"
            subtitle="Revisa capturas, insumos y comentarios antes de aprobar o devolver al técnico."
        />

        <p v-if="!canValidate" class="text-portal-muted text-sm">
            No tienes permiso para validar servicios.
        </p>

        <template v-else>
            <p v-if="loading" class="text-portal-muted">Cargando cola…</p>
            <div
                v-else-if="!routines.length"
                class="portal-form-panel text-portal-muted p-6 text-sm"
            >
                No hay servicios pendientes de validación. Los nuevos aparecerán aquí cuando un técnico
                envíe una ejecución desde Phoenix Campo.
            </div>
            <ConfigurableDataTable
                v-else
                table-id="validation-queue"
                :columns="columns"
                :rows="routines"
                row-key="id"
                empty-text="Sin pendientes."
            >
                <template #routine="{ row }">
                    <p class="text-portal-heading font-medium">
                        {{ (row as Routine).routine_type?.name ?? 'Servicio' }}
                    </p>
                    <p class="text-portal-muted text-xs">#{{ (row as Routine).id }}</p>
                    <StatusBadge class="mt-1" status="pending_validation" />
                    <p
                        v-if="(row as Routine).latest_execution?.technician_comments"
                        class="text-portal-muted mt-2 line-clamp-2 text-xs"
                    >
                        {{ (row as Routine).latest_execution?.technician_comments }}
                    </p>
                </template>
                <template #asset="{ row }">
                    <span class="text-portal-heading">{{ (row as Routine).asset?.tag ?? '—' }}</span>
                    <p class="text-portal-muted text-xs">{{ (row as Routine).site?.name ?? '' }}</p>
                </template>
                <template #technician="{ row }">
                    <span class="text-portal-muted">{{ (row as Routine).assignee?.name ?? '—' }}</span>
                </template>
                <template #submitted="{ row }">
                    <span class="text-portal-muted text-sm">
                        {{ formatSubmittedAt((row as Routine).latest_execution?.submitted_at) }}
                    </span>
                </template>
                <template #actions="{ row }">
                    <div class="table-row-actions">
                        <IconActionButton
                            icon="eye"
                            label="Revisar detalle"
                            :disabled="validatingId === (row as Routine).id || rejecting"
                            @click="openRoutine((row as Routine).id)"
                        />
                        <IconActionButton
                            icon="check"
                            :label="
                                validatingId === (row as Routine).id ? 'Validando…' : 'Validar'
                            "
                            :disabled="validatingId === (row as Routine).id || rejecting"
                            @click="quickValidate(row as Routine)"
                        />
                        <IconActionButton
                            icon="x"
                            variant="danger"
                            label="Rechazar"
                            :disabled="validatingId === (row as Routine).id || rejecting"
                            @click="openReject(row as Routine)"
                        />
                    </div>
                </template>
            </ConfigurableDataTable>
        </template>

        <AppModal
            :open="rejectTarget !== null"
            title="Rechazar ejecución"
            size="sm"
            tone="danger"
            @close="closeReject"
        >
            <div class="space-y-4 p-6">
                <p class="text-portal-muted text-sm">
                    El servicio #{{ rejectTarget?.id }}
                    <span v-if="rejectTarget?.routine_type?.name">
                        ({{ rejectTarget.routine_type.name }})
                    </span>
                    volverá al técnico con el motivo indicado.
                </p>
                <label class="text-portal-heading block text-sm">
                    Motivo (visible para el técnico)
                    <textarea
                        v-model="rejectReason"
                        rows="3"
                        class="field-input mt-1 w-full"
                        placeholder="Ej. Falta evidencia fotográfica del filtro nuevo"
                    />
                </label>
                <div class="flex flex-wrap justify-end gap-2">
                    <AppButton type="button" variant="ghost" :disabled="rejecting" @click="closeReject">
                        Cancelar
                    </AppButton>
                    <AppButton type="button" variant="danger" :disabled="rejecting" @click="confirmReject">
                        {{ rejecting ? 'Rechazando…' : 'Confirmar rechazo' }}
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>
