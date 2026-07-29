<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
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
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
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
        toast.warning('Selecciona un tipo de equipo.');
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
        toast.success(wasEdit ? 'Equipo actualizado.' : 'Equipo creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

async function remove(id: number) {
    if (!window.confirm('¿Eliminar este ítem del catálogo?')) {
        return;
    }
    try {
        await api(`/catalog/items/${id}`, { method: 'DELETE' });
        toast.success('Equipo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="portal-page" data-tour="page-catalog-items">
        <SectionSubnav :items="catalogEquipmentSectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader class="flex-1" title="Equipos" subtitle="Catálogo maestro de equipos y fabricantes." />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo equipo
            </AppButton>
        </div>
        <ReadOnlyNotice v-if="!canWrite" module-label="Equipos" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            table-id="catalog-items"
            :columns="catalogTableColumns"
            :rows="filteredItems"
            row-key="id"
            :filters-active="equipmentFiltersActive"
            filters-title="Filtros de equipos"
            export-file-name="equipos"
        >
            <template #filters>
                <MaterialSelect
                    v-model="filterTypeId"
                    label="Tipo de equipo"
                    :options="typeFilterOptions"
                />
            </template>
            <template #code="{ row }">
                <span class="text-portal-heading font-mono text-xs">{{ (row as CatalogItem).code }}</span>
            </template>
            <template #name="{ row }">
                <span class="text-portal-heading">{{ (row as CatalogItem).name }}</span>
            </template>
            <template #type="{ row }">
                <span class="text-portal-muted text-sm">{{ (row as CatalogItem).equipment_type?.name ?? '—' }}</span>
            </template>
            <template #manufacturer="{ row }">
                <span class="text-portal-muted">{{ (row as CatalogItem).manufacturer ?? '—' }}</span>
            </template>
            <template #actions="{ row }">
                <IconActionButton icon="pencil" label="Editar equipo" @click="openEdit(row as CatalogItem)" />
                <IconActionButton
                    icon="trash"
                    label="Borrar equipo"
                    variant="danger"
                    @click="remove((row as CatalogItem).id)"
                />
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar equipo' : 'Nuevo equipo'"
            size="xl"
            @close="showForm = false"
        >
            <form id="catalog-item-form" class="space-y-4" @submit.prevent="save">
                <MaterialSelect
                    v-model="form.equipment_type_id"
                    label="Tipo de equipo"
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
