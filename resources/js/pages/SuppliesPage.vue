<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { useCatalogTypeFormCapture } from '@/composables/useCatalogTypeFormCapture';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import SectionSubnav from '@/components/ui/SectionSubnav.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import DynamicFormRenderer from '@/components/domain/DynamicFormRenderer.vue';
import { inventorySectionNav } from '@/lib/sectionNav';

type SupplyTypeRef = { id: number; code: string; name: string };
type TaxonomyOption = { value: string; label: string; hint?: string };
type UnitOption = { value: string; label: string };

type SupplyItem = {
    id: number;
    sku: string;
    name: string;
    sector?: string;
    material_kind?: string;
    unit?: string | null;
    standard_cost?: string | number | null;
    quantity_on_hand?: string | number;
    min_stock?: string | number | null;
    storage_location?: string | null;
    notes?: string | null;
    is_active?: boolean;
    supply_type_id?: number | null;
    supply_type?: SupplyTypeRef | null;
    specifications?: Record<string, unknown> | null;
};

type Movement = {
    id: number;
    movement_type: string;
    quantity: string | number;
    reference?: string | null;
    notes?: string | null;
    occurred_at: string;
};

type FormSchema = {
    sections?: Array<{
        title?: string;
        fields?: Array<{ key: string; [k: string]: unknown }>;
    }>;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('inventory'));

const { capture, loadForType, reset: resetCapture } = useCatalogTypeFormCapture(
    (typeId) => `/catalog/supply-types/${typeId}/form-capture`,
);

const items = ref<SupplyItem[]>([]);
const supplyTypes = ref<SupplyTypeRef[]>([]);
const sectors = ref<TaxonomyOption[]>([]);
const materialKinds = ref<TaxonomyOption[]>([]);
const movementTypes = ref<TaxonomyOption[]>([]);
const unitOptions = ref<UnitOption[]>([]);

const search = ref('');
const sectorFilter = ref('');
const filterTypeId = ref('');
const lowStockOnly = ref(false);

const inventoryFiltersActive = computed(
    () => Boolean(sectorFilter.value || filterTypeId.value || lowStockOnly.value),
);

const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const showMovement = ref(false);
const editingId = ref<number | null>(null);
const movementItem = ref<SupplyItem | null>(null);
const recentMovements = ref<Movement[]>([]);
const formResponses = ref<Record<string, unknown>>({});

const form = ref({
    supply_type_id: '',
    sku: '',
    name: '',
    sector: 'mechanical',
    material_kind: 'consumable',
    unit: 'pza',
    standard_cost: '',
    min_stock: '',
    storage_location: '',
    notes: '',
    is_active: true,
    opening_quantity: '',
});

const movementForm = ref({
    movement_type: 'in',
    quantity: '',
    reference: '',
    notes: '',
});

const sectorLabelMap = computed(() => Object.fromEntries(sectors.value.map((s) => [s.value, s.label])));
const kindLabelMap = computed(() => Object.fromEntries(materialKinds.value.map((k) => [k.value, k.label])));

const sectorSelectOptions = computed(() => sectors.value.map((s) => ({ value: s.value, label: s.label })));
const kindSelectOptions = computed(() => materialKinds.value.map((k) => ({ value: k.value, label: k.label })));
const typeFilterOptions = computed(() => [
    { value: '', label: 'Todos los tipos' },
    ...supplyTypes.value.map((t) => ({ value: String(t.id), label: t.name })),
]);
const typeFormOptions = computed(() =>
    supplyTypes.value.map((t) => ({ value: String(t.id), label: `${t.name} (${t.code})` })),
);
const unitSelectOptions = computed(() =>
    unitOptions.value.map((o) => ({ value: o.value, label: `${o.label} (${o.value})` })),
);
const movementTypeOptions = computed(() =>
    movementTypes.value.map((m) => ({ value: m.value, label: m.label })),
);

const captureSchemaWithoutUnit = computed(() => {
    const schema = capture.value.schema as FormSchema | null;
    if (!schema?.sections) {
        return schema;
    }
    return {
        ...schema,
        sections: schema.sections.map((section) => ({
            ...section,
            fields: (section.fields ?? []).filter((f) => f.key !== 'unidad'),
        })),
    };
});

function stockNumber(row: SupplyItem): number {
    return Number(row.quantity_on_hand ?? 0);
}

function isLowStock(row: SupplyItem): boolean {
    if (row.min_stock === null || row.min_stock === undefined || row.min_stock === '') {
        return false;
    }
    return stockNumber(row) <= Number(row.min_stock);
}

function formatQty(row: SupplyItem): string {
    const n = stockNumber(row);
    const unit = row.unit || 'pza';
    return `${n.toLocaleString('es-MX', { maximumFractionDigits: 4 })} ${unit}`;
}

const supplyTableColumns = computed((): TableColumnDef[] => {
    const cols: TableColumnDef[] = [
        {
            id: 'sku',
            label: 'SKU',
            exportValue: (row) => (row as SupplyItem).sku,
        },
        {
            id: 'name',
            label: 'Nombre',
            exportValue: (row) => (row as SupplyItem).name,
        },
        {
            id: 'type',
            label: 'Tipo',
            exportValue: (row) => (row as SupplyItem).supply_type?.name ?? '',
        },
        {
            id: 'type_code',
            label: 'Código tipo',
            defaultVisible: false,
            exportValue: (row) => (row as SupplyItem).supply_type?.code ?? '',
        },
        {
            id: 'sector',
            label: 'Sector',
            exportValue: (row) =>
                sectorLabelMap.value[(row as SupplyItem).sector ?? ''] ?? (row as SupplyItem).sector ?? '',
        },
        {
            id: 'stock',
            label: 'Existencia',
            headerClass: 'portal-table-col-numeric',
            cellClass: 'portal-table-col-numeric text-portal-heading tabular-nums',
            exportValue: (row) => formatQty(row as SupplyItem),
        },
        {
            id: 'location',
            label: 'Ubicación',
            headerClass: 'portal-table-col-location',
            cellClass: 'portal-table-col-location text-portal-muted text-sm',
            exportValue: (row) => (row as SupplyItem).storage_location ?? '',
        },
        {
            id: 'min_stock',
            label: 'Mínimo',
            defaultVisible: false,
            headerClass: 'text-right',
            cellClass: 'text-right text-portal-muted text-sm tabular-nums',
            exportValue: (row) => {
                const v = (row as SupplyItem).min_stock;
                return v === null || v === undefined || v === '' ? '' : String(v);
            },
        },
        {
            id: 'status',
            label: 'Estado',
            exportValue: (row) => {
                const item = row as SupplyItem;
                if (item.is_active === false) {
                    return 'Inactivo';
                }
                return isLowStock(item) ? 'Bajo' : 'OK';
            },
        },
    ];
    if (canWrite.value) {
        cols.push(tableActionsColumn({ cellClass: 'table-row-actions' }));
    }
    return cols;
});

function unitLabel(code: string | null | undefined): string {
    if (!code) {
        return '—';
    }
    const match = unitOptions.value.find((o) => o.value === code);
    return match ? `${match.label} (${code})` : code;
}

function movementLabel(type: string): string {
    const row = movementTypes.value.find((m) => m.value === type);
    return row?.label ?? type;
}

function resetFormResponses() {
    formResponses.value = {};
}

function resetForm() {
    form.value = {
        supply_type_id: supplyTypes.value[0] ? String(supplyTypes.value[0].id) : '',
        sku: '',
        name: '',
        sector: 'mechanical',
        material_kind: 'consumable',
        unit: 'pza',
        standard_cost: '',
        min_stock: '',
        storage_location: '',
        notes: '',
        is_active: true,
        opening_quantity: '',
    };
    editingId.value = null;
    resetFormResponses();
    resetCapture();
}

async function openCreate() {
    resetForm();
    showForm.value = true;
    if (form.value.supply_type_id) {
        await loadForType(form.value.supply_type_id, resetFormResponses);
    }
}

async function openEdit(item: SupplyItem) {
    editingId.value = item.id;
    const specs = { ...(item.specifications ?? {}) };
    form.value = {
        supply_type_id: item.supply_type_id ? String(item.supply_type_id) : '',
        sku: item.sku,
        name: item.name,
        sector: item.sector || 'mechanical',
        material_kind: item.material_kind || 'consumable',
        unit: item.unit || (typeof specs.unidad === 'string' ? specs.unidad : 'pza'),
        standard_cost: item.standard_cost != null ? String(item.standard_cost) : '',
        min_stock: item.min_stock != null ? String(item.min_stock) : '',
        storage_location: item.storage_location ?? '',
        notes: item.notes ?? '',
        is_active: item.is_active !== false,
        opening_quantity: '',
    };
    formResponses.value = specs;
    showForm.value = true;
    if (form.value.supply_type_id) {
        await loadForType(form.value.supply_type_id);
    }
}

watch(
    () => form.value.supply_type_id,
    async (typeId, prev) => {
        if (!showForm.value || typeId === prev) {
            return;
        }
        await loadForType(typeId, resetFormResponses);
    },
);

watch(
    () => form.value.unit,
    (unit) => {
        if (!showForm.value || !unit) {
            return;
        }
        formResponses.value = { ...formResponses.value, unidad: unit };
    },
);

async function loadMeta() {
    const res = await api<{
        data: {
            sectors: TaxonomyOption[];
            material_kinds: TaxonomyOption[];
            movement_types: TaxonomyOption[];
            units: UnitOption[];
        };
    }>('/inventory/meta');
    sectors.value = res.data.sectors;
    materialKinds.value = res.data.material_kinds;
    movementTypes.value = res.data.movement_types;
    if (res.data.units?.length) {
        unitOptions.value = res.data.units.map((u) => ({ value: u.value, label: u.label }));
    }
}

async function load() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value.trim()) {
            params.set('q', search.value.trim());
        }
        if (sectorFilter.value) {
            params.set('sector', sectorFilter.value);
        }
        if (filterTypeId.value) {
            params.set('supply_type_id', filterTypeId.value);
        }
        if (lowStockOnly.value) {
            params.set('low_stock', '1');
        }
        const qs = params.toString();

        const [itemsRes, typesRes] = await Promise.all([
            api<{ data: SupplyItem[] }>(`/inventory/supplies${qs ? `?${qs}` : ''}`),
            api<{ data: SupplyTypeRef[] }>('/catalog/supply-types'),
        ]);
        items.value = itemsRes.data;
        supplyTypes.value = typesRes.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    if (!form.value.supply_type_id) {
        toast.warning('Selecciona un tipo de insumo.');
        return;
    }
    saving.value = true;
    const unit = form.value.unit || 'pza';
    const specs = { ...formResponses.value, unidad: unit };
    const body: Record<string, unknown> = {
        supply_type_id: Number(form.value.supply_type_id),
        sku: form.value.sku,
        name: form.value.name,
        sector: form.value.sector,
        material_kind: form.value.material_kind,
        unit,
        standard_cost: form.value.standard_cost ? Number(form.value.standard_cost) : null,
        min_stock: form.value.min_stock ? Number(form.value.min_stock) : null,
        storage_location: form.value.storage_location.trim() || null,
        notes: form.value.notes.trim() || null,
        is_active: form.value.is_active,
    };
    if (capture.value.configured || editingId.value) {
        body.specifications = specs;
    }
    if (!editingId.value && form.value.opening_quantity) {
        body.opening_quantity = Number(form.value.opening_quantity);
    }
    try {
        if (editingId.value) {
            await api(`/inventory/supplies/${editingId.value}`, { method: 'PUT', body: JSON.stringify(body) });
        } else {
            await api('/inventory/supplies', { method: 'POST', body: JSON.stringify(body) });
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

async function remove(row: SupplyItem) {
    const accepted = await confirm(`¿Eliminar «${row.name}»? Esta acción no se puede deshacer.`, {
        title: 'Eliminar insumo',
        confirmLabel: 'Eliminar',
        danger: true,
    });
    if (!accepted) {
        return;
    }
    try {
        await api(`/inventory/supplies/${row.id}`, { method: 'DELETE' });
        toast.success('Artículo eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function openMovement(row: SupplyItem) {
    movementItem.value = row;
    movementForm.value = { movement_type: 'in', quantity: '', reference: '', notes: '' };
    showMovement.value = true;
    try {
        const res = await api<{ data: Movement[] }>(`/inventory/supplies/${row.id}/movements`);
        recentMovements.value = res.data;
    } catch {
        recentMovements.value = [];
    }
}

async function saveMovement() {
    if (!movementItem.value) {
        return;
    }
    saving.value = true;
    try {
        const res = await api<{ data: Movement; supply_item: SupplyItem }>(
            `/inventory/supplies/${movementItem.value.id}/movements`,
            {
                method: 'POST',
                body: JSON.stringify({
                    movement_type: movementForm.value.movement_type,
                    quantity: Number(movementForm.value.quantity),
                    reference: movementForm.value.reference.trim() || null,
                    notes: movementForm.value.notes.trim() || null,
                }),
            },
        );
        movementItem.value = res.supply_item;
        const idx = items.value.findIndex((m) => m.id === res.supply_item.id);
        if (idx >= 0) {
            items.value[idx] = res.supply_item;
        }
        recentMovements.value = [res.data, ...recentMovements.value].slice(0, 50);
        movementForm.value.quantity = '';
        movementForm.value.reference = '';
        movementForm.value.notes = '';
        toast.success('Movimiento registrado.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

function pickImportCell(row: Record<string, string>, ...keys: string[]): string {
    for (const key of keys) {
        const match = Object.entries(row).find(([header]) => header.trim().toLowerCase() === key.toLowerCase());
        if (match?.[1]?.trim()) {
            return match[1].trim();
        }
    }
    return '';
}

function parseImportQuantity(raw: string): number | undefined {
    if (!raw.trim()) {
        return undefined;
    }
    const normalized = raw.replace(/[^\d.,-]/g, '').replace(',', '.');
    const value = Number.parseFloat(normalized);
    return Number.isNaN(value) ? undefined : value;
}

async function onImportRows(sheetRows: Record<string, string>[]) {
    const rows = sheetRows
        .map((row) => {
            const sku = pickImportCell(row, 'SKU', 'sku');
            const name = pickImportCell(row, 'Nombre', 'name');
            if (!sku || !name) {
                return null;
            }
            const minRaw = pickImportCell(row, 'Mínimo', 'min_stock', 'Minimo');
            const activeRaw = pickImportCell(row, 'Estado', 'is_active', 'Activo');
            let is_active: boolean | undefined;
            if (activeRaw) {
                const lower = activeRaw.toLowerCase();
                is_active = !['inactivo', '0', 'no', 'false'].includes(lower);
            }
            return {
                sku,
                name,
                supply_type_code:
                    pickImportCell(row, 'Código tipo', 'Tipo código', 'supply_type_code', 'tipo_codigo') || undefined,
                sector: pickImportCell(row, 'Sector', 'sector') || undefined,
                unit: pickImportCell(row, 'Unidad', 'unit') || undefined,
                quantity_on_hand: parseImportQuantity(pickImportCell(row, 'Existencia', 'quantity_on_hand')),
                min_stock: minRaw ? parseImportQuantity(minRaw) : undefined,
                storage_location: pickImportCell(row, 'Ubicación', 'Ubicacion', 'storage_location') || undefined,
                is_active,
            };
        })
        .filter((row): row is NonNullable<typeof row> => row !== null);

    if (rows.length === 0) {
        toast.warning('No se encontraron filas con SKU y Nombre.');
        return;
    }

    try {
        const res = await api<{
            data: { created: number; updated: number; errors: Array<{ row: number; message: string }> };
        }>('/inventory/supplies/import', {
            method: 'POST',
            body: JSON.stringify({ rows }),
        });
        const { created, updated, errors } = res.data;
        if (errors.length > 0) {
            toast.warning(`Importación parcial: ${created} nuevos, ${updated} actualizados. ${errors.length} error(es).`);
        } else {
            toast.success(`Importación lista: ${created} nuevos, ${updated} actualizados.`);
        }
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch([search, sectorFilter, filterTypeId, lowStockOnly], () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(() => void load(), 280);
});

onMounted(async () => {
    try {
        await loadMeta();
        await load();
    } catch (e) {
        toast.error((e as Error).message);
        loading.value = false;
    }
});
</script>

<template>
    <div class="portal-page" data-tour="page-inventory">
        <SectionSubnav :items="inventorySectionNav" />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Inventario"
                subtitle="Artículos, existencias, ubicación y movimientos (entradas, salidas, consigna y bajas)."
            />
            <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                Nuevo artículo
            </AppButton>
        </div>

        <ReadOnlyNotice v-if="!canWrite" module-label="Inventario" class="mb-4" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ConfigurableDataTable
            v-else
            v-model:search="search"
            table-id="inventory-supplies"
            :columns="supplyTableColumns"
            :rows="items"
            row-key="id"
            :client-search="false"
            :filters-active="inventoryFiltersActive"
            filters-title="Filtros de inventario"
            search-placeholder="SKU, nombre, ubicación…"
            export-file-name="inventario"
            :allow-excel-import="canWrite"
            table-class="portal-data-table--inventory"
            empty-text="No hay artículos en inventario."
            @import-rows="onImportRows"
        >
            <template #filters>
                <div class="table-filters-popover__fields">
                    <MaterialSelect
                        v-model="sectorFilter"
                        label="Giro / sector"
                        :options="[{ value: '', label: 'Todos' }, ...sectorSelectOptions]"
                    />
                    <MaterialSelect
                        v-model="filterTypeId"
                        label="Tipo de insumo"
                        :options="typeFilterOptions"
                    />
                    <label class="table-filters-popover__check">
                        <input v-model="lowStockOnly" type="checkbox" class="rounded border-white/20" />
                        Solo bajo
                    </label>
                </div>
            </template>
            <template #sku="{ row }">
                <span class="text-portal-heading font-mono text-xs">{{ (row as SupplyItem).sku }}</span>
            </template>
            <template #name="{ row }">
                <span class="text-portal-heading">{{ (row as SupplyItem).name }}</span>
            </template>
            <template #type="{ row }">
                <span class="text-portal-muted text-sm">{{ (row as SupplyItem).supply_type?.name ?? '—' }}</span>
            </template>
            <template #sector="{ row }">
                <span class="text-portal-muted text-sm">{{
                    sectorLabelMap[(row as SupplyItem).sector ?? ''] ?? (row as SupplyItem).sector ?? '—'
                }}</span>
            </template>
            <template #stock="{ row }">
                <span :class="isLowStock(row as SupplyItem) ? 'text-amber-300' : 'text-portal-heading'">
                    {{ formatQty(row as SupplyItem) }}
                </span>
            </template>
            <template #min_stock="{ row }">
                <span class="text-portal-muted text-sm">
                    {{
                        (row as SupplyItem).min_stock != null && (row as SupplyItem).min_stock !== ''
                            ? (row as SupplyItem).min_stock
                            : '—'
                    }}
                </span>
            </template>
            <template #location="{ row }">
                <span class="text-portal-muted text-sm">{{ (row as SupplyItem).storage_location || '—' }}</span>
            </template>
            <template #status="{ row }">
                <span
                    v-if="(row as SupplyItem).is_active === false"
                    class="inline-flex rounded-full bg-white/10 px-2 py-0.5 text-xs font-medium text-slate-400"
                >
                    Inactivo
                </span>
                <span
                    v-else-if="isLowStock(row as SupplyItem)"
                    class="inline-flex rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-medium text-amber-200"
                >
                    Bajo
                </span>
                <span
                    v-else
                    class="inline-flex rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs font-medium text-emerald-200"
                >
                    OK
                </span>
            </template>
            <template #actions="{ row }">
                <IconActionButton
                    icon="arrows-exchange"
                    label="Registrar movimiento"
                    @click="openMovement(row as SupplyItem)"
                />
                <IconActionButton icon="pencil" label="Editar artículo" @click="openEdit(row as SupplyItem)" />
                <IconActionButton
                    icon="trash"
                    label="Borrar artículo"
                    variant="danger"
                    @click="remove(row as SupplyItem)"
                />
            </template>
        </ConfigurableDataTable>

        <AppModal
            :open="showForm && canWrite"
            :title="editingId ? 'Editar artículo' : 'Nuevo artículo'"
            size="xl"
            @close="showForm = false"
        >
            <form id="supply-form" class="space-y-4" @submit.prevent="save">
                <MaterialSelect
                    v-model="form.supply_type_id"
                    label="Tipo de insumo"
                    required
                    :options="typeFormOptions"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <MaterialField v-model="form.sku" label="SKU" required />
                    <MaterialField v-model="form.name" label="Nombre" required />
                    <MaterialSelect v-model="form.sector" label="Giro / sector" required :options="sectorSelectOptions" />
                    <MaterialSelect v-model="form.material_kind" label="Tipo de material" :options="kindSelectOptions" />
                    <MaterialSelect v-model="form.unit" label="Unidad" required :options="unitSelectOptions" />
                    <MaterialField v-model="form.standard_cost" label="Costo estándar" type="number" />
                    <MaterialField v-model="form.min_stock" label="Stock mínimo" type="number" />
                    <MaterialField v-model="form.storage_location" label="Ubicación" />
                    <MaterialField
                        v-if="!editingId"
                        v-model="form.opening_quantity"
                        label="Existencia inicial"
                        type="number"
                    />
                </div>
                <MaterialField v-model="form.notes" label="Notas" multiline />
                <label class="flex items-center gap-2 text-sm text-portal-muted">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-white/20" />
                    Activo en inventario
                </label>

                <p v-if="capture.loading" class="text-portal-muted text-sm">Cargando formulario del tipo…</p>
                <div
                    v-else-if="!capture.configured"
                    class="portal-callout portal-callout--warning"
                    role="alert"
                >
                    {{ capture.message || 'Este tipo no tiene formulario asignado o publicado.' }}
                </div>
                <div v-else class="space-y-3 border-t border-white/10 pt-4">
                    <p class="text-portal-heading text-sm font-medium">Ficha: {{ capture.formName }}</p>
                    <DynamicFormRenderer
                        v-model="formResponses"
                        :schema="captureSchemaWithoutUnit"
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
                <AppButton type="submit" form="supply-form" :disabled="saving || capture.loading">Guardar</AppButton>
            </template>
        </AppModal>

        <AppModal
            :open="showMovement && !!movementItem"
            :title="movementItem ? `Movimiento — ${movementItem.name}` : 'Movimiento'"
            size="lg"
            @close="showMovement = false"
        >
            <p v-if="movementItem" class="text-portal-muted mb-4 text-sm">
                Existencia actual:
                <strong class="text-portal-heading">{{ formatQty(movementItem) }}</strong>
            </p>
            <form id="inventory-movement-form" class="mb-6 space-y-4" @submit.prevent="saveMovement">
                <div class="grid gap-4 sm:grid-cols-2">
                    <MaterialSelect v-model="movementForm.movement_type" label="Tipo" :options="movementTypeOptions" />
                    <MaterialField v-model="movementForm.quantity" label="Cantidad" type="number" required />
                    <p v-if="movementForm.movement_type === 'adjustment'" class="text-portal-muted text-xs sm:col-span-2">
                        En ajuste use cantidad con signo (ej. −2.5).
                    </p>
                    <MaterialField
                        v-model="movementForm.reference"
                        label="Referencia"
                        class="sm:col-span-2"
                    />
                </div>
                <MaterialField v-model="movementForm.notes" label="Notas" multiline />
            </form>
            <template #footer>
                <AppButton type="submit" form="inventory-movement-form" :disabled="saving">Registrar</AppButton>
            </template>
            <h3 class="text-portal-heading mb-2 text-sm font-semibold">Últimos movimientos</h3>
            <ul v-if="recentMovements.length" class="portal-list-panel max-h-48 divide-y overflow-y-auto rounded-xl text-sm">
                <li v-for="mv in recentMovements" :key="mv.id" class="px-3 py-2">
                    <span class="text-portal-heading font-medium">{{ movementLabel(mv.movement_type) }}</span>
                    · {{ mv.quantity }} · {{ new Date(mv.occurred_at).toLocaleString('es-MX') }}
                    <span v-if="mv.reference" class="text-portal-muted"> — {{ mv.reference }}</span>
                </li>
            </ul>
            <p v-else class="text-portal-muted text-sm">Sin movimientos aún.</p>
        </AppModal>
    </div>
</template>
