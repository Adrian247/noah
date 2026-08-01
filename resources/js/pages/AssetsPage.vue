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
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import AssetAiAssistCard from '@/components/insights/AssetAiAssistCard.vue';
import PlateOcrControl from '@/components/insights/PlateOcrControl.vue';
import { useAiCapabilities } from '@/composables/useAiCapabilities';

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
const { canUseAi } = useAiCapabilities();
const canWrite = computed(() => canWriteModule('assets'));

const assetTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'tag', label: 'Tag' },
        { id: 'site', label: 'Sitio' },
        { id: 'catalog', label: 'Catálogo' },
        { id: 'location', label: 'Ubicación' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

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

type Client = { id: number; legal_name: string };

const clients = ref<Client[]>([]);
const linkAsset = ref<Asset | null>(null);
const linkClientId = ref('');
const linkSerial = ref('');
const linking = ref(false);

const clientOptions = computed(() =>
    clients.value.map((c) => ({ value: String(c.id), label: c.legal_name })),
);

async function openLinkClient(asset: Asset) {
    linkAsset.value = asset;
    linkClientId.value = '';
    linkSerial.value = asset.serial_number ?? '';
    if (clients.value.length === 0) {
        try {
            const res = await api<{ data: Client[] }>('/clients');
            clients.value = res.data;
        } catch (e) {
            toast.error((e as Error).message);
        }
    }
}

async function submitClientLink() {
    if (!linkAsset.value || !linkClientId.value) {
        toast.warning('Selecciona un cliente.');
        return;
    }
    linking.value = true;
    try {
        await api(`/assets/${linkAsset.value.id}/client-assignments`, {
            method: 'POST',
            body: JSON.stringify({
                client_id: Number(linkClientId.value),
                serial_number: linkSerial.value,
            }),
        });
        toast.success('Cliente vinculado al activo.');
        linkAsset.value = null;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        linking.value = false;
    }
}

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
    <div class="portal-page" data-tour="page-assets">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Activos" subtitle="Instancias físicas en sitio vinculadas al catálogo." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo activo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Activos" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="assets"
            :columns="assetTableColumns"
            :rows="assets"
            row-key="id"
        >
            <template #tag="{ row }">
                <span class="text-portal-heading font-medium">{{ (row as Asset).tag }}</span>
            </template>
            <template #site="{ row }">
                <span class="text-portal-muted">{{ (row as Asset).site?.name ?? '—' }}</span>
            </template>
            <template #catalog="{ row }">
                <span class="text-portal-muted">{{ (row as Asset).catalog_item?.code ?? '—' }}</span>
            </template>
            <template #location="{ row }">
                <span class="text-portal-muted">{{ (row as Asset).location_label ?? '—' }}</span>
            </template>
            <template #actions="{ row }">
                <IconActionButton icon="building" label="Vincular cliente" @click="openLinkClient(row as Asset)" />
                <IconActionButton icon="pencil" label="Editar activo" @click="openEdit(row as Asset)" />
                <IconActionButton
                    icon="trash"
                    label="Borrar activo"
                    variant="danger"
                    @click="remove((row as Asset).id)"
                />
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar activo' : 'Nuevo activo'"
            size="md"
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
                <PlateOcrControl
                    v-if="canUseAi && !editingId"
                    apply-label="No. serie"
                    @apply="form.serial_number = $event"
                />
                <AssetAiAssistCard
                    v-if="canUseAi && editingId"
                    :asset-id="editingId"
                    show-ocr
                    @apply-ocr="(text) => { form.tag = text || form.tag }"
                />
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

        <AppModal
            :open="linkAsset !== null && canWrite"
            title="Vincular cliente por serie"
            size="sm"
            @close="linkAsset = null"
        >
            <form id="link-client-form" class="space-y-4" @submit.prevent="submitClientLink">
                <p class="text-portal-muted text-sm">
                    Activo <strong class="text-portal-heading">{{ linkAsset?.tag }}</strong> — el número de serie debe
                    coincidir con el registrado en el activo.
                </p>
                <MaterialSelect v-model="linkClientId" label="Cliente" :options="clientOptions" required />
                <MaterialField v-model="linkSerial" label="Confirmar número de serie" required />
            </form>
            <template #footer>
                <button type="button" class="text-portal-muted text-sm" @click="linkAsset = null">Cancelar</button>
                <AppButton type="submit" form="link-client-form" :disabled="linking">Vincular</AppButton>
            </template>
        </AppModal>
    </div>
</template>
