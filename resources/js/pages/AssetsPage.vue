<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';

type Site = { id: number; name: string };
type CatalogItem = { id: number; code: string; name: string };
type Asset = {
    id: number;
    tag: string;
    serial_number?: string | null;
    location_label?: string | null;
    site?: Site;
    catalog_item?: CatalogItem;
};

const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('assets'));

const assets = ref<Asset[]>([]);
const sites = ref<Site[]>([]);
const catalogItems = ref<CatalogItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const saving = ref(false);

const form = ref({
    site_id: '',
    catalog_item_id: '',
    tag: '',
    serial_number: '',
    location_label: '',
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [assetsRes, sitesRes, catalogRes] = await Promise.all([
            api<{ data: Asset[] }>('/assets'),
            api<{ data: Site[] }>('/sites'),
            api<{ data: CatalogItem[] }>('/catalog/items'),
        ]);
        assets.value = assetsRes.data;
        sites.value = sitesRes.data;
        catalogItems.value = catalogRes.data;
        if (!form.value.site_id && sites.value[0]) {
            form.value.site_id = String(sites.value[0].id);
        }
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function submit() {
    saving.value = true;
    error.value = null;
    try {
        await api('/assets', {
            method: 'POST',
            body: JSON.stringify({
                site_id: Number(form.value.site_id),
                catalog_item_id: form.value.catalog_item_id
                    ? Number(form.value.catalog_item_id)
                    : null,
                tag: form.value.tag,
                serial_number: form.value.serial_number || null,
                location_label: form.value.location_label || null,
            }),
        });
        form.value.tag = '';
        form.value.serial_number = '';
        form.value.location_label = '';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

const editingId = ref<number | null>(null);
const editForm = ref({ tag: '', location_label: '' });

function startEdit(asset: Asset) {
    editingId.value = asset.id;
    editForm.value = {
        tag: asset.tag,
        location_label: asset.location_label ?? '',
    };
}

async function saveEdit(id: number) {
    await api(`/assets/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
            tag: editForm.value.tag,
            location_label: editForm.value.location_label || null,
        }),
    });
    editingId.value = null;
    await load();
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar activo?')) return;
    try {
        await api(`/assets/${id}`, { method: 'DELETE' });
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <h2 class="text-xl font-semibold">Activos</h2>
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <form
            v-if="canWrite"
            class="max-w-lg space-y-3 rounded-lg border border-slate-200 bg-white p-4"
            @submit.prevent="submit"
        >
            <p class="text-sm font-medium text-slate-700">Nuevo activo</p>
            <label class="block text-sm">
                Sitio
                <select
                    v-model="form.site_id"
                    required
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                >
                    <option v-for="s in sites" :key="s.id" :value="String(s.id)">
                        {{ s.name }}
                    </option>
                </select>
            </label>
            <label class="block text-sm">
                Equipo de catálogo
                <select
                    v-model="form.catalog_item_id"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                >
                    <option value="">— Sin vínculo —</option>
                    <option
                        v-for="c in catalogItems"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.code }} — {{ c.name }}
                    </option>
                </select>
            </label>
            <label class="block text-sm">
                Etiqueta (tag)
                <input
                    v-model="form.tag"
                    required
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <label class="block text-sm">
                No. serie
                <input
                    v-model="form.serial_number"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <label class="block text-sm">
                Ubicación
                <input
                    v-model="form.location_label"
                    class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5"
                />
            </label>
            <button
                type="submit"
                class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
                :disabled="saving || sites.length === 0"
            >
                Guardar
            </button>
        </form>
        <ReadOnlyNotice v-else module-label="Activos" />

        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b text-slate-500">
                    <th class="py-2">Tag</th>
                    <th>Sitio</th>
                    <th>Catálogo</th>
                    <th>Ubicación</th>
                    <th v-if="canWrite"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="asset in assets" :key="asset.id" class="border-b border-slate-100">
                    <template v-if="editingId === asset.id">
                        <td><input v-model="editForm.tag" class="rounded border px-1" /></td>
                        <td>{{ asset.site?.name ?? '—' }}</td>
                        <td>{{ asset.catalog_item?.code ?? '—' }}</td>
                        <td><input v-model="editForm.location_label" class="rounded border px-1" /></td>
                        <td class="text-xs space-x-1">
                            <button type="button" class="underline" @click="saveEdit(asset.id)">OK</button>
                            <button type="button" @click="editingId = null">×</button>
                        </td>
                    </template>
                    <template v-else>
                        <td class="py-2 font-medium">{{ asset.tag }}</td>
                        <td>{{ asset.site?.name ?? '—' }}</td>
                        <td>{{ asset.catalog_item?.code ?? '—' }}</td>
                        <td>{{ asset.location_label ?? '—' }}</td>
                        <td v-if="canWrite" class="text-xs space-x-2">
                            <button type="button" class="underline" @click="startEdit(asset)">Editar</button>
                            <button type="button" class="text-red-700" @click="remove(asset.id)">Borrar</button>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>
</template>
