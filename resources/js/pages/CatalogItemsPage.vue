<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useCatalogTypeFormCapture } from '@/composables/useCatalogTypeFormCapture';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import DynamicFormRenderer from '@/components/domain/DynamicFormRenderer.vue';
import { catalogEquipmentSectionNav } from '@/lib/sectionNav';

type EquipmentTypeRef = { id: number; code: string; name: string };

type CatalogItem = {
    id: number;
    code: string;
    name: string;
    manufacturer?: string | null;
    equipment_type_id?: number | null;
    equipment_type?: EquipmentTypeRef | null;
    specifications?: Record<string, unknown> | null;
    source_system_catalog_item_id?: number | null;
    import_generation?: number;
};

type SystemArticle = {
    id: number;
    code: string;
    name: string;
    manufacturer?: string | null;
    equipment_type?: { name: string } | null;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('catalog_items'));

const catalogTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        { id: 'code', label: 'Código' },
        { id: 'name', label: 'Nombre' },
        { id: 'type', label: 'Tipo' },
        { id: 'manufacturer', label: 'Fabricante' },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

const { capture, loadForType, reset: resetCapture } = useCatalogTypeFormCapture(
    (typeId) => `/catalog/equipment-types/${typeId}/form-capture`,
);

const items = ref<CatalogItem[]>([]);
const equipmentTypes = ref<EquipmentTypeRef[]>([]);
const filterTypeId = ref('');
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref<number | null>(null);
const formResponses = ref<Record<string, unknown>>({});

const showImport = ref(false);
const systemArticles = ref<SystemArticle[]>([]);
const importLoading = ref(false);
const importingId = ref<number | null>(null);

const form = ref({ equipment_type_id: '', code: '', name: '', manufacturer: '' });

const filteredItems = computed(() => {
    if (!filterTypeId.value) {
        return items.value;
    }
    const id = Number(filterTypeId.value);
    return items.value.filter((i) => i.equipment_type_id === id);
});

const typeFilterOptions = computed(() => [
    { value: '', label: 'Todos los tipos' },
    ...equipmentTypes.value.map((t) => ({ value: String(t.id), label: t.name })),
]);

const equipmentFiltersActive = computed(() => Boolean(filterTypeId.value));

const typeFormOptions = computed(() =>
    equipmentTypes.value.map((t) => ({ value: String(t.id), label: `${t.name} (${t.code})` })),
);

function resetFormResponses() {
    formResponses.value = {};
}

function resetForm() {
    form.value = {
        equipment_type_id: equipmentTypes.value[0] ? String(equipmentTypes.value[0].id) : '',
        code: '',
        name: '',
        manufacturer: '',
    };
    editingId.value = null;
    resetFormResponses();
    resetCapture();
}

async function openCreate() {
    resetForm();
    showForm.value = true;
    if (form.value.equipment_type_id) {
        await loadForType(form.value.equipment_type_id, resetFormResponses);
    }
}

async function openEdit(item: CatalogItem) {
    editingId.value = item.id;
    form.value = {
        equipment_type_id: item.equipment_type_id ? String(item.equipment_type_id) : '',
        code: item.code,
        name: item.name,
        manufacturer: item.manufacturer ?? '',
    };
    formResponses.value = { ...(item.specifications ?? {}) };
    showForm.value = true;
    if (form.value.equipment_type_id) {
        await loadForType(form.value.equipment_type_id);
    }
}

watch(
    () => form.value.equipment_type_id,
    async (typeId, prev) => {
        if (!showForm.value || typeId === prev) {
            return;
        }
        await loadForType(typeId, resetFormResponses);
    },
);

async function load() {
    loading.value = true;
    try {
        const [itemsRes, typesRes] = await Promise.all([
            api<{ data: CatalogItem[] }>('/catalog/items'),
            api<{ data: EquipmentTypeRef[] }>('/catalog/equipment-types'),
        ]);
        items.value = itemsRes.data;
        equipmentTypes.value = typesRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.value.equipment_type_id) {
        toast.warning('Selecciona un tipo de artículo.');
        return;
    }
    saving.value = true;
    try {
        const body: Record<string, unknown> = {
            equipment_type_id: Number(form.value.equipment_type_id),
            code: form.value.code,
            name: form.value.name,
            manufacturer: form.value.manufacturer || null,
        };
        if (capture.value.configured) {
            body.specifications = formResponses.value;
        } else if (editingId.value) {
            body.specifications = formResponses.value;
        }

        if (editingId.value) {
            await api(`/catalog/items/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/catalog/items', { method: 'POST', body: JSON.stringify(body) });
        }
        const wasEdit = Boolean(editingId.value);
        showForm.value = false;
        resetForm();
        toast.success(wasEdit ? 'Artículo actualizado.' : 'Artículo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    const accepted = await confirm('¿Eliminar este artículo del catálogo? Esta acción no se puede deshacer.', {
        title: 'Eliminar artículo',
        confirmLabel: 'Eliminar',
        danger: true,
    });
    if (!accepted) {
        return;
    }
    try {
        await api(`/catalog/items/${id}`, { method: 'DELETE' });
        toast.success('Artículo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function openImport() {
    showImport.value = true;
    importLoading.value = true;
    try {
        const res = await api<{ data: SystemArticle[] }>('/catalog/import/system');
        systemArticles.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        importLoading.value = false;
    }
}

function previousImport(sourceId: number): CatalogItem | undefined {
    return items.value.find((i) => i.source_system_catalog_item_id === sourceId);
}

async function importArticle(source: SystemArticle, mode: 'new' | 'overwrite' | 'force_new') {
    const prev = previousImport(source.id);
    if (prev && mode === 'new') {
        const generation = prev.import_generation ?? 1;
        if (generation > 1) {
            toast.warning(
                `Se detectaron ${generation} importaciones previas de «${source.code}». Revise posibles inconsistencias.`,
            );
        } else {
            toast.warning(`«${source.code}» ya fue importado. Elija sobrescribir o clonación nueva.`);
        }
        return;
    }
    if (prev && mode === 'force_new' && (prev.import_generation ?? 1) > 1) {
        toast.warning('Múltiples importaciones previas: el nuevo clon puede divergir del original y de copias locales.');
    }
    await runImport(source.id, mode === 'overwrite', mode === 'force_new');
}

async function runImport(sourceId: number, overwrite: boolean, forceNew = false) {
    importingId.value = sourceId;
    try {
        const res = await api<{
            data: CatalogItem;
            meta?: { action?: string; warnings?: string[]; generation?: number };
        }>('/catalog/import', {
            method: 'POST',
            body: JSON.stringify({
                source_catalog_item_id: sourceId,
                overwrite,
                force_new: forceNew,
            }),
        });
        const warnings = res.meta?.warnings ?? [];
        for (const w of warnings) {
            toast.warning(w);
        }
        if (res.meta?.action === 'skipped') {
            toast.warning('Importación omitida: ya existe una copia local.');
        } else if (res.meta?.action === 'overwrite') {
            toast.success('Artículo sobrescrito desde el catálogo de sistema.');
        } else {
            toast.success('Artículo importado (clon).');
        }
        await load();
        if (res.meta?.action !== 'skipped') {
            showImport.value = false;
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        importingId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-catalog-items">
        <SectionSubnav :items="catalogEquipmentSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Artículos" subtitle="Catálogo maestro de artículos para venta, fabricación e inventario de clientes." />
            <div class="flex shrink-0 flex-wrap gap-2">
                <AppButton v-if="canWrite" type="button" variant="secondary" @click="openImport">
                    Importar de sistema
                </AppButton>
                <AppButton v-if="canWrite" type="button" @click="openCreate">
                    Nuevo artículo
                </AppButton>
            </div>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Artículos" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="catalog-items"
            :columns="catalogTableColumns"
            :rows="filteredItems"
            row-key="id"
            :filters-active="equipmentFiltersActive"
            filters-title="Filtros de artículos"
            export-file-name="articulos"
        >
            <template #filters>
                <MaterialSelect
                    v-model="filterTypeId"
                    label="Tipo de artículo"
                    :options="typeFilterOptions"
                />
            </template>
            <template #code="{ row }">
                <span class="text-portal-heading font-mono text-xs">{{ (row as CatalogItem).code }}</span>
            </template>
            <template #name="{ row }">
                <span class="text-portal-heading">{{ (row as CatalogItem).name }}</span>
                <p
                    v-if="(row as CatalogItem).source_system_catalog_item_id"
                    class="text-portal-muted text-[10px]"
                >
                    Importado · gen. {{ (row as CatalogItem).import_generation ?? 1 }}
                </p>
            </template>
            <template #type="{ row }">
                <span class="text-portal-muted text-sm">{{ (row as CatalogItem).equipment_type?.name ?? '—' }}</span>
            </template>
            <template #manufacturer="{ row }">
                <span class="text-portal-muted">{{ (row as CatalogItem).manufacturer ?? '—' }}</span>
            </template>
            <template #actions="{ row }">
                <IconActionButton icon="pencil" label="Editar artículo" @click="openEdit(row as CatalogItem)" />
                <IconActionButton
                    icon="trash"
                    label="Borrar artículo"
                    variant="danger"
                    @click="remove((row as CatalogItem).id)"
                />
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showImport && canWrite"
            title="Importar artículos de sistema"
            size="lg"
            @close="showImport = false"
        >
            <p class="text-portal-muted mb-3 text-sm">
                Se crea un clon en tu catálogo (tipo + formulario). Editar el clon no afecta el original de sistema.
            </p>
            <p v-if="importLoading" class="text-portal-muted">Cargando catálogo de sistema…</p>
            <ul v-else class="portal-list-panel max-h-[24rem] divide-y overflow-y-auto">
                <li
                    v-for="row in systemArticles"
                    :key="row.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 text-sm"
                >
                    <div class="min-w-0">
                        <p class="text-portal-heading font-medium">{{ row.code }} · {{ row.name }}</p>
                        <p class="text-portal-muted text-xs">
                            {{ row.equipment_type?.name ?? 'Sin tipo' }}
                            <span v-if="previousImport(row.id)" class="text-amber-600">
                                · ya importado (gen. {{ previousImport(row.id)?.import_generation ?? 1 }})
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AppButton
                            v-if="!previousImport(row.id)"
                            :disabled="importingId === row.id"
                            @click="importArticle(row, 'new')"
                        >
                            Importar
                        </AppButton>
                        <template v-else>
                            <AppButton
                                variant="secondary"
                                :disabled="importingId === row.id"
                                @click="importArticle(row, 'overwrite')"
                            >
                                Sobrescribir
                            </AppButton>
                            <AppButton
                                :disabled="importingId === row.id"
                                @click="importArticle(row, 'force_new')"
                            >
                                Clon nuevo
                            </AppButton>
                        </template>
                    </div>
                </li>
                <li v-if="systemArticles.length === 0" class="text-portal-muted px-3 py-6">
                    No hay artículos de sistema disponibles.
                </li>
            </ul>
        </AppModal>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar artículo' : 'Nuevo artículo'"
            size="xl"
            @close="showForm = false"
        >
            <form id="catalog-item-form" class="space-y-4" @submit.prevent="save">
                <MaterialSelect
                    v-model="form.equipment_type_id"
                    label="Tipo de artículo"
                    required
                    :options="typeFormOptions"
                />
                <MaterialField v-model="form.code" label="Código" required />
                <MaterialField v-model="form.name" label="Nombre" required />
                <MaterialField v-model="form.manufacturer" label="Fabricante" />

                <p v-if="capture.loading" class="text-portal-muted text-sm">Cargando formulario del tipo…</p>
                <div
                    v-else-if="!capture.configured"
                    class="portal-callout portal-callout--warning"
                    role="alert"
                >
                    {{ capture.message || 'Este tipo no tiene formulario asignado o publicado.' }}
                </div>
                <div v-else class="space-y-3 border-t border-white/10 pt-4">
                    <p class="text-portal-heading text-sm font-medium">
                        Ficha: {{ capture.formName }}
                    </p>
                    <DynamicFormRenderer
                        v-model="formResponses"
                        :schema="capture.schema"
                        :form-settings="capture.formSettings"
                        :option-catalogs="capture.optionCatalogs"
                        :disabled="!canWrite"
                    />
                </div>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showForm = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="catalog-item-form" :disabled="saving || capture.loading">
                    Guardar
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
