<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';

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
const toast = useToast();
const canWrite = computed(() => canWriteModule('assets'));

const assets = ref<Asset[]>([]);
const sites = ref<Site[]>([]);
const catalogItems = ref<CatalogItem[]>([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    site_id: '',
    catalog_item_id: '',
    tag: '',
    serial_number: '',
    location_label: '',
});

const siteOptions = computed(() =>
    sites.value.map((s) => ({ value: String(s.id), label: s.name })),
);
const catalogOptions = computed(() => [
    { value: '', label: '— Sin vínculo —' },
    ...catalogItems.value.map((c) => ({
        value: String(c.id),
        label: `${c.code} — ${c.name}`,
    })),
]);

function resetForm() {
    form.value = {
        site_id: sites.value[0] ? String(sites.value[0].id) : '',
        catalog_item_id: '',
        tag: '',
        serial_number: '',
        location_label: '',
    };
    editingId.value = null;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function openEdit(asset: Asset) {
    editingId.value = asset.id;
    form.value = {
        site_id: asset.site ? String(asset.site.id) : '',
        catalog_item_id: asset.catalog_item ? String(asset.catalog_item.id) : '',
        tag: asset.tag,
        serial_number: asset.serial_number ?? '',
        location_label: asset.location_label ?? '',
    };
    showForm.value = true;
}

async function load() {
    loading.value = true;
    try {
        const [assetsRes, sitesRes, catalogRes] = await Promise.all([
            api<{ data: Asset[] }>('/assets'),
            api<{ data: Site[] }>('/sites'),
            api<{ data: CatalogItem[] }>('/catalog/items'),
        ]);
        assets.value = assetsRes.data;
        sites.value = sitesRes.data;
        catalogItems.value = catalogRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        if (editingId.value) {
            await api(`/assets/${editingId.value}`, {
                method: 'PUT',
                body: JSON.stringify({
                    tag: form.value.tag,
                    location_label: form.value.location_label || null,
                }),
            });
        } else {
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
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Activo actualizado.' : 'Activo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar activo?')) {
        return;
    }
    try {
        await api(`/assets/${id}`, { method: 'DELETE' });
        toast.success('Activo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Activos" subtitle="Instancias físicas en sitio vinculadas al catálogo." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo activo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Activos" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div v-else class="portal-table-wrap">
            <table class="portal-data-table">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">Tag</th>
                        <th>Sitio</th>
                        <th>Catálogo</th>
                        <th>Ubicación</th>
                        <th v-if="canWrite" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="asset in assets" :key="asset.id" class="border-b">
                        <td class="text-portal-heading py-2 font-medium">{{ asset.tag }}</td>
                        <td class="text-portal-muted">{{ asset.site?.name ?? '—' }}</td>
                        <td class="text-portal-muted">{{ asset.catalog_item?.code ?? '—' }}</td>
                        <td class="text-portal-muted">{{ asset.location_label ?? '—' }}</td>
                        <td v-if="canWrite" class="space-x-2 text-xs">
                            <button type="button" class="text-portal-link underline" @click="openEdit(asset)">
                                Editar
                            </button>
                            <button type="button" class="text-red-400" @click="remove(asset.id)">Borrar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar activo' : 'Nuevo activo'"
            size="sm"
            @close="showForm = false"
        >
            <form id="asset-form" class="space-y-4" @submit.prevent="save">
                <template v-if="!editingId">
                    <MaterialSelect v-model="form.site_id" label="Sitio" :options="siteOptions" required />
                    <MaterialSelect
                        v-model="form.catalog_item_id"
                        label="Equipo de catálogo"
                        :options="catalogOptions"
                    />
                    <MaterialField v-model="form.serial_number" label="No. serie" />
                </template>
                <p v-else class="text-portal-muted text-xs">
                    Sitio: {{ assets.find((a) => a.id === editingId)?.site?.name ?? '—' }} · Catálogo:
                    {{ assets.find((a) => a.id === editingId)?.catalog_item?.code ?? '—' }}
                </p>
                <MaterialField v-model="form.tag" label="Etiqueta (tag)" required />
                <MaterialField v-model="form.location_label" label="Ubicación" />
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="asset-form" :disabled="saving || (!editingId && sites.length === 0)">
                    Guardar
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
