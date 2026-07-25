<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { api } from '@/api/client';
import { useCompanyStore } from '@/stores/company';
import { useModuleAccess } from '@/composables/useModuleAccess';

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
const { canWriteModule } = useModuleAccess();
const routines = ref<Routine[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
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

const filteredAssets = computed(() =>
    createForm.value.site_id
        ? assets.value.filter((a) => String(a.site_id) === createForm.value.site_id)
        : assets.value,
);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const qs = statusFilter.value
            ? `?status=${encodeURIComponent(statusFilter.value)}&per_page=50`
            : '?per_page=50';
        const res = await api<{ data: Routine[] }>(`/routines${qs}`);
        routines.value = res.data;
    } catch (e) {
        error.value = (e as Error).message;
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

async function createRoutine() {
    error.value = null;
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
        await load();
    } catch (e) {
        error.value = (e as Error).message;
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
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-semibold">Rutinas</h2>
            <button
                v-if="canCreate"
                type="button"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white"
                @click="showCreate = !showCreate"
            >
                Nueva rutina
            </button>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <button
                type="button"
                class="rounded-md border px-2 py-1"
                :class="!statusFilter ? 'bg-slate-200' : ''"
                @click="statusFilter = ''; load()"
            >
                Todas
            </button>
            <button
                v-for="s in ['assigned', 'pending_validation', 'validated']"
                :key="s"
                type="button"
                class="rounded-md border px-2 py-1"
                :class="statusFilter === s ? 'bg-slate-200' : ''"
                @click="statusFilter = s; load()"
            >
                {{ s }}
            </button>
        </div>
        <form
            v-if="showCreate && canCreate"
            class="max-w-xl space-y-3 rounded-lg border bg-white p-4 text-sm"
            @submit.prevent="createRoutine"
        >
            <label class="block">
                Sitio
                <select v-model="createForm.site_id" required class="mt-1 w-full rounded-md border px-2 py-1.5">
                    <option v-for="s in sites" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </label>
            <label class="block">
                Activo
                <select v-model="createForm.asset_id" required class="mt-1 w-full rounded-md border px-2 py-1.5">
                    <option value="" disabled>Selecciona…</option>
                    <option v-for="a in filteredAssets" :key="a.id" :value="String(a.id)">{{ a.tag }}</option>
                </select>
            </label>
            <label class="block">
                Tipo de rutina
                <select v-model="createForm.routine_type_id" required class="mt-1 w-full rounded-md border px-2 py-1.5">
                    <option v-for="t in routineTypes" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </label>
            <label v-if="technicians.length" class="block">
                Asignar a
                <select v-model="createForm.assigned_to" class="mt-1 w-full rounded-md border px-2 py-1.5">
                    <option value="">Sin asignar</option>
                    <option v-for="u in technicians" :key="u.id" :value="String(u.id)">
                        {{ u.name }} ({{ u.email }})
                    </option>
                </select>
            </label>
            <button type="submit" class="rounded-md bg-emerald-700 px-3 py-2 text-white">Crear</button>
        </form>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <p v-else-if="routines.length === 0" class="text-slate-500">Sin rutinas.</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">ID</th>
                    <th>Tipo</th>
                    <th>Activo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="r in routines"
                    :key="r.id"
                    class="border-b border-slate-100 hover:bg-slate-50"
                >
                    <td class="py-2">
                        <RouterLink class="text-slate-900 underline" :to="`/app/routines/${r.id}`">
                            {{ r.id }}
                        </RouterLink>
                    </td>
                    <td>{{ r.routine_type?.name ?? '—' }}</td>
                    <td>{{ r.asset?.tag ?? '—' }}</td>
                    <td>{{ r.status }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
