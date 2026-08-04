<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import { routinesSectionNav, SERVICE_CATEGORY_LABELS } from '@/lib/sectionNav';

type Routine = {
    id: number;
    status: string;
    is_demo?: boolean;
    scheduled_at?: string | null;
    asset?: { tag: string } | null;
    client?: { id: number; trade_name?: string; legal_name?: string } | null;
    site?: { name: string };
    routine_type?: { name: string; service_category?: string } | null;
    assignee?: { name: string } | null;
};

type Site = { id: number; name: string };
type Asset = { id: number; tag: string; site_id: number };
type Client = { id: number; trade_name?: string; legal_name?: string; code?: string };
type RoutineType = { id: number; name: string; service_category?: string };
type UserRow = { id: number; name: string; email: string };

const SERVICE_LINE_LABELS = SERVICE_CATEGORY_LABELS;

const route = useRoute();
const router = useRouter();
const company = useCompanyStore();
const toast = useToast();
const confirm = useConfirm();
const { canWriteModule } = useModuleAccess();
const routines = ref<Routine[]>([]);
const loading = ref(true);
const statusFilter = ref((route.query.status as string) ?? '');

function openRoutine(id: number) {
    void router.push(`/app/routines/${id}`);
}

const showCreate = ref(false);
const sites = ref<Site[]>([]);
const assets = ref<Asset[]>([]);
const clients = ref<Client[]>([]);
const routineTypes = ref<RoutineType[]>([]);
const technicians = ref<UserRow[]>([]);
const createForm = ref({
    site_id: '',
    asset_id: '',
    client_id: '',
    routine_type_id: '',
    assigned_to: '',
    scheduled_at: '',
});

const canCreate = computed(() => canWriteModule('routines'));
const isAdmin = computed(() => company.current?.role === 'administrator');

const selectedType = computed(() =>
    routineTypes.value.find((t) => String(t.id) === createForm.value.routine_type_id) ?? null,
);
const selectedCategory = computed(() => selectedType.value?.service_category ?? 'maintenance');
const requiresAsset = computed(() => selectedCategory.value === 'maintenance');
const requiresClient = computed(
    () => selectedCategory.value === 'manufacturing' || selectedCategory.value === 'installation',
);

const routineTableColumns = computed((): TableColumnDef[] => [
    { id: 'routine', label: 'Servicio', cellClass: 'py-3' },
    { id: 'subject', label: 'Sujeto' },
    { id: 'site', label: 'Sitio' },
    { id: 'assignee', label: 'Asignado' },
    { id: 'status', label: 'Estado' },
    tableActionsColumn({ cellClass: 'py-3 text-right' }),
]);

const statusChips = [
    { value: '', label: 'Todas' },
    { value: 'assigned', label: 'Asignadas' },
    { value: 'pending_validation', label: 'Pendientes' },
    { value: 'pending_billing', label: 'Facturación' },
    { value: 'validated', label: 'Validadas' },
];

const siteOptions = computed(() =>
    sites.value.map((s) => ({ value: String(s.id), label: s.name })),
);
const assetOptions = computed(() => [
    { value: '', label: requiresAsset.value ? 'Selecciona…' : 'Sin artículo (opcional)' },
    ...filteredAssets.value.map((a) => ({ value: String(a.id), label: a.tag })),
]);
const clientOptions = computed(() => [
    { value: '', label: requiresClient.value ? 'Selecciona…' : 'Sin cliente (opcional)' },
    ...clients.value.map((c) => ({
        value: String(c.id),
        label: c.trade_name || c.legal_name || c.code || `Cliente #${c.id}`,
    })),
]);
const routineTypeOptions = computed(() =>
    routineTypes.value.map((t) => ({
        value: String(t.id),
        label: `${t.name} · ${SERVICE_CATEGORY_LABELS[t.service_category ?? 'maintenance'] ?? 'Mantenimiento'}`,
    })),
);
const technicianOptions = computed(() => [
    { value: '', label: 'Sin asignar' },
    ...technicians.value.map((u) => ({ value: String(u.id), label: `${u.name} (${u.email})` })),
]);

const filteredAssets = computed(() =>
    createForm.value.site_id
        ? assets.value.filter((a) => String(a.site_id) === createForm.value.site_id)
        : assets.value,
);

async function load() {
    loading.value = true;
    try {
        const qs = statusFilter.value
            ? `?status=${encodeURIComponent(statusFilter.value)}&per_page=50`
            : '?per_page=50';
        const res = await api<{ data: Routine[] }>(`/routines${qs}`);
        routines.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function loadCreateData() {
    const [s, a, t, c] = await Promise.all([
        api<{ data: Site[] }>('/sites'),
        api<{ data: Asset[] }>('/assets'),
        api<{ data: RoutineType[] }>('/routine-types'),
        api<{ data: Client[] }>('/clients'),
    ]);
    sites.value = s.data;
    assets.value = a.data;
    routineTypes.value = t.data;
    clients.value = c.data;
    if (company.current?.role === 'administrator') {
        try {
            const u = await api<{ data: UserRow[] }>('/company/users');
            technicians.value = u.data.filter((x) => x.email.includes('tecnico') || true);
        } catch {
            technicians.value = [];
        }
    }
    if (sites.value[0]) {
        createForm.value.site_id = String(sites.value[0].id);
    }
    if (routineTypes.value[0]) {
        createForm.value.routine_type_id = String(routineTypes.value[0].id);
    }
}

function openCreate() {
    createForm.value = {
        site_id: sites.value[0] ? String(sites.value[0].id) : '',
        asset_id: '',
        client_id: '',
        routine_type_id: routineTypes.value[0] ? String(routineTypes.value[0].id) : '',
        assigned_to: '',
        scheduled_at: '',
    };
    showCreate.value = true;
}

async function createRoutine() {
    try {
        await api('/routines', {
            method: 'POST',
            body: JSON.stringify({
                site_id: Number(createForm.value.site_id),
                asset_id: createForm.value.asset_id ? Number(createForm.value.asset_id) : null,
                client_id: createForm.value.client_id ? Number(createForm.value.client_id) : null,
                routine_type_id: Number(createForm.value.routine_type_id),
                assigned_to: createForm.value.assigned_to
                    ? Number(createForm.value.assigned_to)
                    : null,
                scheduled_at: createForm.value.scheduled_at || null,
            }),
        });
        showCreate.value = false;
        toast.success('Servicio creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

const creatingDemo = ref(false);
const deletingId = ref<number | null>(null);

async function deleteRoutine(r: Routine) {
    const accepted = await confirm(
        `¿Eliminar el servicio #${r.id} (${r.routine_type?.name ?? 'Servicio'})? Esta acción no se puede deshacer.`,
        { title: 'Eliminar servicio', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    deletingId.value = r.id;
    try {
        await api(`/routines/${r.id}`, { method: 'DELETE' });
        toast.success('Servicio eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deletingId.value = null;
    }
}

async function createDemoRoutine() {
    creatingDemo.value = true;
    try {
        const res = await api<{ data: { id: number } }>('/routines/demo', { method: 'POST' });
        toast.success('Servicio demo creado con datos de prueba.');
        await load();
        if (res.data?.id) {
            await router.push(`/app/routines/${res.data.id}`);
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creatingDemo.value = false;
    }
}

watch(
    () => route.query.status,
    (v) => {
        statusFilter.value = (v as string) ?? '';
        void load();
    },
);

onMounted(async () => {
    await load();
    if (canCreate.value) {
        await loadCreateData();
    }
});
</script>

<template>
    <div class="portal-page" data-tour="page-routines">
        <SectionSubnav :items="routinesSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Servicios" subtitle="Filtra por estado y crea nuevas asignaciones." />
            <div class="flex shrink-0 flex-wrap gap-2">
                <AppButton
                    v-if="isAdmin"
                    type="button"
                    variant="secondary"
                    :disabled="creatingDemo"
                    @click="createDemoRoutine"
                >
                    Generar servicio demo
                </AppButton>
                <AppButton v-if="canCreate" type="button" @click="openCreate">Nuevo servicio</AppButton>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="chip in statusChips"
                :key="chip.value || 'all'"
                type="button"
                class="filter-chip"
                :class="{ 'filter-chip--active': statusFilter === chip.value }"
                @click="
                    statusFilter = chip.value;
                    load();
                "
            >
                {{ chip.label }}
            </button>
        </div>
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="routines"
            :columns="routineTableColumns"
            :rows="routines"
            row-key="id"
            clickable
            empty-text="Sin servicios."
            @row-click="(row) => openRoutine((row as Routine).id)"
        >
            <template #routine="{ row }">
                <p class="text-portal-heading font-medium">
                    {{ (row as Routine).routine_type?.name ?? 'Servicio' }}
                    <span
                        v-if="(row as Routine).is_demo"
                        class="ml-1.5 inline-flex rounded-full bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-200"
                    >
                        Demo
                    </span>
                </p>
                <p class="text-portal-muted text-xs">
                    #{{ (row as Routine).id }}
                    ·
                    {{
                        SERVICE_CATEGORY_LABELS[(row as Routine).routine_type?.service_category ?? 'maintenance']
                            ?? 'Mantenimiento'
                    }}
                </p>
            </template>
            <template #subject="{ row }">
                <span class="text-portal-heading">
                    {{
                        (row as Routine).asset?.tag
                            ?? (row as Routine).client?.trade_name
                            ?? (row as Routine).client?.legal_name
                            ?? '—'
                    }}
                </span>
                <p
                    v-if="(row as Routine).asset?.tag && ((row as Routine).client?.trade_name || (row as Routine).client?.legal_name)"
                    class="text-portal-muted text-xs"
                >
                    {{ (row as Routine).client?.trade_name || (row as Routine).client?.legal_name }}
                </p>
            </template>
            <template #site="{ row }">
                <span class="text-portal-muted">{{ (row as Routine).site?.name ?? '—' }}</span>
            </template>
            <template #assignee="{ row }">
                <span class="text-portal-muted">{{ (row as Routine).assignee?.name ?? 'Sin asignar' }}</span>
            </template>
            <template #status="{ row }">
                <StatusBadge :status="(row as Routine).status" />
            </template>
            <template #actions="{ row }">
                <div class="table-row-actions justify-end">
                    <IconActionButton
                        v-if="isAdmin"
                        icon="trash"
                        label="Eliminar servicio"
                        variant="danger"
                        :disabled="deletingId === (row as Routine).id"
                        @click="deleteRoutine(row as Routine)"
                    />
                    <IconActionButton
                        icon="chevron-right"
                        label="Abrir servicio"
                        @click="openRoutine((row as Routine).id)"
                    />
                </div>
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showCreate && canCreate"
            title="Nuevo servicio"
            size="sm"
            @close="showCreate = false"
        >
            <form id="routine-create-form" class="space-y-4" @submit.prevent="createRoutine">
                <MaterialSelect
                    v-model="createForm.site_id"
                    label="Sitio"
                    :options="siteOptions"
                    required
                />
                <MaterialSelect
                    v-model="createForm.routine_type_id"
                    label="Tipo de servicio"
                    :options="routineTypeOptions"
                    required
                />
                <MaterialSelect
                    v-if="requiresAsset || !requiresClient"
                    v-model="createForm.asset_id"
                    :label="requiresAsset ? 'Artículo inventario *' : 'Artículo inventario (opcional)'"
                    :options="assetOptions"
                    :required="requiresAsset"
                />
                <MaterialSelect
                    v-if="requiresClient || selectedCategory === 'maintenance'"
                    v-model="createForm.client_id"
                    :label="requiresClient ? 'Cliente *' : 'Cliente (opcional)'"
                    :options="clientOptions"
                    :required="requiresClient"
                />
                <MaterialSelect
                    v-if="technicians.length"
                    v-model="createForm.assigned_to"
                    label="Asignar a"
                    :options="technicianOptions"
                />
                <MaterialField
                    v-model="createForm.scheduled_at"
                    label="Programada (opcional)"
                    type="datetime-local"
                />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showCreate = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="routine-create-form">Crear servicio</AppButton>
            </template>
        </AppModal>
    </div>
</template>
