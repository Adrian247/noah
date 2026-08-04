<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';
import PlateOcrControl from '@/components/insights/PlateOcrControl.vue';
import { clientDetailSectionNav } from '@/lib/sectionNav';

type Client = {
    id: number;
    legal_name: string;
    trade_name?: string | null;
    logo_url?: string | null;
    code?: string | null;
};

type Site = { id: number; name: string; address?: string | null };
type CatalogItem = { id: number; code: string; name: string };
type InventoryRow = {
    id: number;
    site_id: number;
    site?: Site;
    catalog_item_id: number;
    catalog_item?: CatalogItem;
    tag: string;
    serial_number?: string | null;
    location_label?: string | null;
    ocr_plate_text?: string | null;
    sync_mode: string;
    image_url?: string | null;
};

const route = useRoute();
const router = useRouter();
const clientId = computed(() => Number(route.params.id));
const { canWriteModule } = useModuleAccess();
const confirm = useConfirm();
const toast = useToast();
const canEdit = computed(() => canWriteModule('clients'));

const client = ref<Client | null>(null);
const tab = computed<'sites' | 'inventory'>(() =>
    route.meta.clientTab === 'inventory' || String(route.name) === 'client-inventory'
        ? 'inventory'
        : 'sites',
);
const sectionNav = computed(() => clientDetailSectionNav(clientId.value));
const sites = ref<Site[]>([]);
const inventory = ref<InventoryRow[]>([]);
const catalogItems = ref<CatalogItem[]>([]);
const loading = ref(true);

const showSiteModal = ref(false);
const siteForm = ref({ name: '', address: '' });
const editingSiteId = ref<number | null>(null);

const showInventoryModal = ref(false);
const inventoryForm = ref({
    site_id: '' as string | number,
    catalog_item_id: '' as string | number,
    tag: '',
    serial_number: '',
    location_label: '',
    ocr_plate_text: '',
});

async function loadClient() {
    const res = await api<{ data: Client }>(`/clients/${clientId.value}`);
    client.value = res.data;
}

async function loadSites() {
    const res = await api<{ data: Site[] }>(`/clients/${clientId.value}/sites`);
    sites.value = res.data;
}

async function loadInventory() {
    const res = await api<{ data: InventoryRow[] }>(`/clients/${clientId.value}/inventory`);
    inventory.value = res.data;
}

async function loadCatalogItems() {
    const res = await api<{ data: CatalogItem[] }>('/catalog/items');
    catalogItems.value = res.data;
}

async function loadAll() {
    if (!Number.isFinite(clientId.value) || clientId.value <= 0) {
        toast.error('Cliente no válido.');
        void router.push('/app/catalog/clients');
        return;
    }
    loading.value = true;
    try {
        await loadClient();
        await Promise.all([loadSites(), loadInventory(), loadCatalogItems()]);
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function openSiteForm(site?: Site) {
    editingSiteId.value = site?.id ?? null;
    siteForm.value = { name: site?.name ?? '', address: site?.address ?? '' };
    showSiteModal.value = true;
}

async function saveSite() {
    try {
        if (editingSiteId.value) {
            await api(`/clients/${clientId.value}/sites/${editingSiteId.value}`, {
                method: 'PUT',
                body: JSON.stringify(siteForm.value),
            });
        } else {
            await api(`/clients/${clientId.value}/sites`, {
                method: 'POST',
                body: JSON.stringify(siteForm.value),
            });
        }
        showSiteModal.value = false;
        await loadSites();
        toast.success('Sitio guardado.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function deleteSite(site: Site) {
    const ok = await confirm(`¿Eliminar sitio «${site.name}»?`, { danger: true, confirmLabel: 'Eliminar' });
    if (!ok) return;
    try {
        await api(`/clients/${clientId.value}/sites/${site.id}`, { method: 'DELETE' });
        await loadSites();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function openInventoryForm() {
    inventoryForm.value = {
        site_id: sites.value[0]?.id ?? '',
        catalog_item_id: catalogItems.value[0]?.id ?? '',
        tag: '',
        serial_number: '',
        location_label: '',
        ocr_plate_text: '',
    };
    showInventoryModal.value = true;
}

function applyOcr(text: string) {
    inventoryForm.value.ocr_plate_text = text;
    if (!inventoryForm.value.serial_number.trim()) {
        inventoryForm.value.serial_number = text;
    }
    if (!inventoryForm.value.tag.trim()) {
        inventoryForm.value.tag = text.slice(0, 64);
    }
}

async function saveInventory() {
    try {
        await api(`/clients/${clientId.value}/inventory`, {
            method: 'POST',
            body: JSON.stringify({
                site_id: Number(inventoryForm.value.site_id),
                catalog_item_id: Number(inventoryForm.value.catalog_item_id),
                tag: inventoryForm.value.tag,
                serial_number: inventoryForm.value.serial_number || null,
                location_label: inventoryForm.value.location_label || null,
                ocr_plate_text: inventoryForm.value.ocr_plate_text || null,
            }),
        });
        showInventoryModal.value = false;
        await loadInventory();
        toast.success('Artículo vinculado al inventario del cliente.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function detachRow(row: InventoryRow) {
    const ok = await confirm(
        'Se creará una copia personalizada del artículo de catálogo. Ya no se sincronizará automáticamente.',
        { title: 'Modificar información base', confirmLabel: 'Continuar' },
    );
    if (!ok) return;
    try {
        await api(`/clients/${clientId.value}/inventory/${row.id}/detach-catalog`, { method: 'POST' });
        await loadInventory();
        toast.success('Copia personalizada creada.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function resetRow(row: InventoryRow) {
    const ok = await confirm(
        'Se restablecerá la información desde el catálogo base y se perderán cambios personalizados.',
        { title: 'Restablecer catálogo', danger: true, confirmLabel: 'Restablecer' },
    );
    if (!ok) return;
    try {
        await api(`/clients/${clientId.value}/inventory/${row.id}/reset-catalog`, { method: 'POST' });
        await loadInventory();
        toast.success('Artículo restablecido.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

const siteOptions = computed(() => sites.value.map((s) => ({ value: s.id, label: s.name })));
const catalogOptions = computed(() =>
    catalogItems.value.map((c) => ({ value: c.id, label: `${c.code} · ${c.name}` })),
);

watch(clientId, () => loadAll(), { immediate: true });
onMounted(loadAll);
</script>

<template>
    <div class="portal-page">
        <SectionSubnav :items="sectionNav" />
        <PageHeader
            :title="client?.trade_name || client?.legal_name || 'Cliente'"
            :subtitle="tab === 'sites'
                ? 'Sitios del cliente: ubicaciones donde opera o se instalan artículos.'
                : 'Inventario del cliente: artículos del catálogo vinculados a un sitio.'"
        />

        <div v-if="client" class="mb-4 flex items-center gap-3">
            <UserAvatar :avatar-url="client.logo_url" :name="client.legal_name" size="lg" />
            <div>
                <p class="text-portal-heading font-medium">{{ client.legal_name }}</p>
                <p v-if="client.code" class="text-portal-muted font-mono text-xs">{{ client.code }}</p>
            </div>
        </div>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <div v-else-if="tab === 'sites'" class="portal-list-panel">
            <div class="flex items-center justify-between border-b border-[color:var(--portal-border)] px-4 py-3">
                <h2 class="text-portal-heading text-sm font-medium">Sitios</h2>
                <AppButton v-if="canEdit" @click="openSiteForm()">Nuevo sitio</AppButton>
            </div>
            <ul class="divide-y divide-[color:var(--portal-border)]">
                <li v-for="site in sites" :key="site.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div>
                        <p class="text-portal-heading font-medium">{{ site.name }}</p>
                        <p v-if="site.address" class="text-portal-muted text-xs">{{ site.address }}</p>
                    </div>
                    <div v-if="canEdit" class="table-row-actions">
                        <IconActionButton icon="pencil" label="Editar" @click="openSiteForm(site)" />
                        <IconActionButton icon="trash" label="Eliminar" variant="danger" @click="deleteSite(site)" />
                    </div>
                </li>
                <li v-if="sites.length === 0" class="text-portal-muted px-4 py-6 text-sm">Sin sitios registrados.</li>
            </ul>
        </div>

        <div v-else class="portal-list-panel">
            <div class="flex items-center justify-between border-b border-[color:var(--portal-border)] px-4 py-3">
                <h2 class="text-portal-heading text-sm font-medium">Inventario</h2>
                <AppButton v-if="canEdit" @click="openInventoryForm()">Vincular artículo</AppButton>
            </div>
            <ul class="divide-y divide-[color:var(--portal-border)]">
                <li v-for="row in inventory" :key="row.id" class="px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-portal-heading font-medium">{{ row.tag }}</p>
                            <p class="text-portal-muted text-xs">
                                {{ row.catalog_item?.code }} · {{ row.catalog_item?.name }}
                            </p>
                            <p class="text-portal-muted text-xs">
                                Sitio: {{ row.site?.name ?? '—' }}
                                <span v-if="row.serial_number"> · Serie {{ row.serial_number }}</span>
                            </p>
                            <span
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wide"
                                :class="row.sync_mode === 'linked' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600'"
                            >
                                {{ row.sync_mode === 'linked' ? 'Sincronizado' : 'Copia personalizada' }}
                            </span>
                        </div>
                        <div v-if="canEdit" class="table-row-actions">
                            <IconActionButton
                                v-if="row.sync_mode === 'linked'"
                                icon="pencil"
                                label="Modificar información"
                                @click="detachRow(row)"
                            />
                            <IconActionButton
                                v-else
                                icon="arrows-exchange"
                                label="Restablecer catálogo"
                                @click="resetRow(row)"
                            />
                        </div>
                    </div>
                </li>
                <li v-if="inventory.length === 0" class="text-portal-muted px-4 py-6 text-sm">
                    Sin artículos en inventario del cliente.
                </li>
            </ul>
        </div>

        <AppModal :open="showSiteModal" title="Sitio" @close="showSiteModal = false">
            <form class="space-y-4" @submit.prevent="saveSite">
                <MaterialField v-model="siteForm.name" label="Nombre" required />
                <MaterialField v-model="siteForm.address" label="Dirección" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="showSiteModal = false">Cancelar</AppButton>
                <AppButton @click="saveSite">Guardar</AppButton>
            </template>
        </AppModal>

        <AppModal :open="showInventoryModal" title="Vincular artículo" @close="showInventoryModal = false">
            <form class="space-y-4" @submit.prevent="saveInventory">
                <MaterialSelect v-model="inventoryForm.site_id" label="Sitio" :options="siteOptions" required />
                <MaterialSelect
                    v-model="inventoryForm.catalog_item_id"
                    label="Artículo de catálogo"
                    :options="catalogOptions"
                    required
                />
                <MaterialField v-model="inventoryForm.tag" label="Etiqueta (tag)" required />
                <MaterialField v-model="inventoryForm.serial_number" label="No. serie" />
                <MaterialField v-model="inventoryForm.location_label" label="Ubicación" />
                <MaterialField v-model="inventoryForm.ocr_plate_text" label="OCR placa / etiqueta" />
                <PlateOcrControl apply-label="serie / etiqueta" @apply="applyOcr" />
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="showInventoryModal = false">Cancelar</AppButton>
                <AppButton @click="saveInventory">Vincular</AppButton>
            </template>
        </AppModal>
    </div>
</template>
