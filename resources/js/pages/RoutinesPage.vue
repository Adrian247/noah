<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import { routinesSectionNav } from '@/lib/sectionNav';

type Routine = {
    id: number;
    status: string;
    asset?: { tag: string };
    routine_type?: { name: string };
};

type Site = { id: number; name: string };
type Asset = { id: number; tag: string; site_id: number };
type RoutineType = { id: number; name: string };
type UserRow = { id: number; name: string; email: string };

const route = useRoute();
const company = useCompanyStore();
const toast = useToast();
const { canWriteModule } = useModuleAccess();
const routines = ref<Routine[]>([]);
const loading = ref(true);
const statusFilter = ref((route.query.status as string) ?? '');

const showCreate = ref(false);
const sites = ref<Site[]>([]);
const assets = ref<Asset[]>([]);
const routineTypes = ref<RoutineType[]>([]);
const technicians = ref<UserRow[]>([]);
const createForm = ref({
    site_id: '',
    asset_id: '',
    routine_type_id: '',
    assigned_to: '',
    scheduled_at: '',
});

const canCreate = computed(() => canWriteModule('routines'));
const isAdmin = computed(() => company.current?.role === 'administrator');

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
    { value: '', label: 'Selecciona…' },
    ...filteredAssets.value.map((a) => ({ value: String(a.id), label: a.tag })),
]);
const routineTypeOptions = computed(() =>
    routineTypes.value.map((t) => ({ value: String(t.id), label: t.name })),
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
    const [s, a, t] = await Promise.all([
        api<{ data: Site[] }>('/sites'),
        api<{ data: Asset[] }>('/assets'),
        api<{ data: RoutineType[] }>('/routine-types'),
    ]);
    sites.value = s.data;
    assets.value = a.data;
    routineTypes.value = t.data;
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
                asset_id: Number(createForm.value.asset_id),
                routine_type_id: Number(createForm.value.routine_type_id),
                assigned_to: createForm.value.assigned_to
                    ? Number(createForm.value.assigned_to)
                    : null,
                scheduled_at: createForm.value.scheduled_at || null,
            }),
        });
        showCreate.value = false;
        toast.success('Rutina creada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

const creatingDemo = ref(false);

async function createDemoRoutine() {
    creatingDemo.value = true;
    try {
        const res = await api<{ data: { id: number } }>('/routines/demo', { method: 'POST' });
        toast.success('Rutina demo creada con datos de prueba.');
        await load();
        if (res.data?.id) {
            window.location.href = `/app/routines/${res.data.id}`;
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
            <PageHeader class="flex-1" title="Rutinas" subtitle="Filtra por estado y crea nuevas asignaciones." />
            <div class="flex shrink-0 flex-wrap gap-2">
                <AppButton
                    v-if="isAdmin"
                    type="button"
                    variant="secondary"
                    :disabled="creatingDemo"
                    @click="createDemoRoutine"
                >
                    Generar rutina demo
                </AppButton>
                <AppButton v-if="canCreate" type="button" @click="openCreate">Nueva rutina</AppButton>
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
        <p v-else-if="routines.length === 0" class="text-portal-muted">Sin rutinas.</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
            <thead>
                <tr class="border-b">
                    <th class="py-2">ID</th>
                    <th>Tipo</th>
                    <th>Activo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in routines" :key="r.id" class="border-b">
                    <td class="py-2">
                        <RouterLink class="text-portal-link underline" :to="`/app/routines/${r.id}`">
                            {{ r.id }}
                        </RouterLink>
                    </td>
                    <td class="text-portal-heading">{{ r.routine_type?.name ?? '—' }}</td>
                    <td class="text-portal-muted">{{ r.asset?.tag ?? '—' }}</td>
                    <td class="text-portal-muted">{{ r.status }}</td>
                </tr>
            </tbody>
        </table>
        </div>

        <AppModal
            :open="showCreate && canCreate"
            title="Nueva rutina"
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
                    v-model="createForm.asset_id"
                    label="Activo"
                    :options="assetOptions"
                    required
                />
                <MaterialSelect
                    v-model="createForm.routine_type_id"
                    label="Tipo de rutina"
                    :options="routineTypeOptions"
                    required
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
                <AppButton type="submit" form="routine-create-form">Crear rutina</AppButton>
            </template>
        </AppModal>
    </div>
</template>
