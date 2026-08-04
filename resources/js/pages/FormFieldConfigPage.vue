<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import ConfigurableDataTable from '@/components/ui/ConfigurableDataTable.vue';
import { tableActionsColumn, type TableColumnDef } from '@/lib/tableColumns';
import { RouterLink } from 'vue-router';

type OptionRow = { value: string; label: string; description?: string };
type Catalog = { id: number; name: string; slug: string; options: OptionRow[] };
type FormSettings = { max_image_size_kb: number; allowed_image_mimes: string[] };

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('design_forms'));

const catalogs = ref<Catalog[]>([]);
const settings = ref<FormSettings>({ max_image_size_kb: 2048, allowed_image_mimes: ['image/jpeg', 'image/png', 'image/webp'] });
const loading = ref(true);
const savingCatalogId = ref<number | null>(null);
const expandedCatalogId = ref<number | null>(null);
const showCreateModal = ref(false);

const newCatalogName = ref('');
const newCatalogRows = ref<OptionRow[]>([
    { value: '', label: '', description: '' },
    { value: '', label: '', description: '' },
]);

const editName = ref('');
const editRows = ref<OptionRow[]>([]);

const mimeOptions = [
    { value: 'image/jpeg', label: 'JPEG' },
    { value: 'image/png', label: 'PNG' },
    { value: 'image/webp', label: 'WebP' },
    { value: 'image/gif', label: 'GIF' },
];

const selectedMimes = ref<string[]>([]);

const catalogOptionColumns: TableColumnDef[] = [
    { id: 'value', label: 'Valor' },
    { id: 'label', label: 'Nombre' },
    { id: 'description', label: 'Descripción' },
    tableActionsColumn({ cellClass: 'table-row-actions' }),
];

function emptyRow(): OptionRow {
    return { value: '', label: '', description: '' };
}

function moveRow<T>(rows: T[], index: number, delta: -1 | 1): T[] {
    const target = index + delta;
    if (target < 0 || target >= rows.length) {
        return rows;
    }
    const copy = [...rows];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    return copy;
}

function moveEditRow(index: number, delta: -1 | 1) {
    editRows.value = moveRow(editRows.value, index, delta);
}

function moveNewRow(index: number, delta: -1 | 1) {
    newCatalogRows.value = moveRow(newCatalogRows.value, index, delta);
}

function normalizeRows(rows: OptionRow[]): OptionRow[] {
    return rows
        .map((r) => ({
            value: r.value.trim(),
            label: r.label.trim(),
            description: (r.description ?? '').trim(),
        }))
        .filter((r) => r.value !== '' && r.label !== '');
}

async function load() {
    loading.value = true;
    try {
        const [catRes, setRes] = await Promise.all([
            api<{ data: Catalog[] }>('/design/forms/option-catalogs'),
            api<{ data: FormSettings }>('/design/forms/settings'),
        ]);
        catalogs.value = catRes.data;
        settings.value = setRes.data;
        selectedMimes.value = [...setRes.data.allowed_image_mimes];
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function saveSettings() {
    try {
        const res = await api<{ data: FormSettings }>('/design/forms/settings', {
            method: 'PUT',
            body: JSON.stringify({
                max_image_size_kb: settings.value.max_image_size_kb,
                allowed_image_mimes: selectedMimes.value,
            }),
        });
        settings.value = res.data;
        toast.success('Configuración de imágenes guardada.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function loadEditState(catalog: Catalog) {
    editName.value = catalog.name;
    editRows.value = catalog.options.map((o) => ({
        value: o.value,
        label: o.label,
        description: o.description ?? '',
    }));
    if (editRows.value.length === 0) {
        editRows.value.push(emptyRow());
    }
}

function toggleCatalog(catalog: Catalog) {
    if (expandedCatalogId.value === catalog.id) {
        expandedCatalogId.value = null;
        return;
    }
    expandedCatalogId.value = catalog.id;
    loadEditState(catalog);
}

function openCreate() {
    newCatalogName.value = '';
    newCatalogRows.value = [emptyRow(), emptyRow()];
    showCreateModal.value = true;
}

async function saveCatalogEdit(catalogId: number) {
    const options = normalizeRows(editRows.value);
    if (!editName.value.trim() || options.length === 0) {
        toast.warning('Indica nombre del catálogo y al menos una opción con valor y nombre.');
        return;
    }
    savingCatalogId.value = catalogId;
    try {
        await api(`/design/forms/option-catalogs/${catalogId}`, {
            method: 'PUT',
            body: JSON.stringify({ name: editName.value.trim(), options }),
        });
        await load();
        expandedCatalogId.value = catalogId;
        const updated = catalogs.value.find((c) => c.id === catalogId);
        if (updated) {
            loadEditState(updated);
        }
        toast.success('Catálogo actualizado.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingCatalogId.value = null;
    }
}

async function createCatalog() {
    const options = normalizeRows(newCatalogRows.value);
    if (!newCatalogName.value.trim() || options.length === 0) {
        toast.warning('Indica nombre y al menos una fila con valor y nombre.');
        return;
    }
    try {
        await api('/design/forms/option-catalogs', {
            method: 'POST',
            body: JSON.stringify({ name: newCatalogName.value.trim(), options }),
        });
        showCreateModal.value = false;
        await load();
        toast.success('Catálogo creado.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function removeCatalog(id: number) {
    const accepted = await confirm(
        '¿Eliminar catálogo? Los campos que lo usen dejarán de validar opciones.',
        { title: 'Eliminar catálogo de opciones', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    await api(`/design/forms/option-catalogs/${id}`, { method: 'DELETE' });
    if (expandedCatalogId.value === id) {
        expandedCatalogId.value = null;
    }
    await load();
}

onMounted(load);
</script>

<template>
    <div class="portal-page space-y-8">
        <PageHeader
            title="Configuración de campos"
            subtitle="Reglas transversales de captura: imágenes y catálogos reutilizables en listas desplegables."
        />
        <RouterLink to="/app/design/forms">
            <AppButton type="button" variant="secondary">← Volver a formularios</AppButton>
        </RouterLink>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <template v-else>
            <section class="space-y-4">
                <h2 class="text-portal-heading text-lg font-semibold">Imágenes</h2>
                <div class="portal-form-panel max-w-2xl space-y-4">
                    <MaterialField
                        v-model="settings.max_image_size_kb"
                        label="Tamaño máximo (KB)"
                        type="number"
                        :disabled="!canWrite"
                    />
                    <p class="text-portal-muted text-xs">Tipos permitidos:</p>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="m in mimeOptions"
                            :key="m.value"
                            class="text-portal-muted flex items-center gap-1 text-sm"
                        >
                            <input v-model="selectedMimes" type="checkbox" :value="m.value" :disabled="!canWrite" />
                            {{ m.label }}
                        </label>
                    </div>
                    <AppButton v-if="canWrite" type="button" @click="saveSettings">Guardar reglas de imagen</AppButton>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex-1">
                        <h2 class="text-portal-heading text-lg font-semibold">Catálogo de opciones</h2>
                        <p class="text-portal-muted mt-1 max-w-3xl text-sm">
                            Cada opción tiene valor (interno), nombre (visible) y descripción (ayuda en campo).
                            Usa las flechas para cambiar el orden de la lista.
                        </p>
                    </div>
                    <AppButton v-if="canWrite" type="button" class="shrink-0" @click="openCreate">
                        Nuevo catálogo
                    </AppButton>
                </div>

                <p v-if="catalogs.length === 0" class="text-portal-muted text-sm">Aún no hay catálogos.</p>

                <div v-else class="space-y-2">
                    <div
                        v-for="c in catalogs"
                        :key="c.id"
                        class="portal-form-panel overflow-hidden p-0"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-white/5"
                            @click="toggleCatalog(c)"
                        >
                            <span
                                class="text-portal-muted shrink-0 text-xs transition-transform"
                                :class="expandedCatalogId === c.id ? 'rotate-90' : ''"
                                aria-hidden="true"
                            >
                                ▶
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-portal-heading font-medium">{{ c.name }}</p>
                                <p class="text-portal-muted font-mono text-xs">{{ c.slug }}</p>
                            </div>
                            <span class="text-portal-muted shrink-0 text-xs">
                                {{ c.options.length }} opción{{ c.options.length === 1 ? '' : 'es' }}
                            </span>
                            <div v-if="canWrite" class="table-row-actions shrink-0" @click.stop>
                                <IconActionButton
                                    icon="trash"
                                    label="Eliminar catálogo"
                                    variant="danger"
                                    @click="removeCatalog(c.id)"
                                />
                            </div>
                        </button>

                        <div
                            v-if="expandedCatalogId === c.id"
                            class="space-y-3 border-t border-white/10 px-4 py-4"
                        >
                            <MaterialField v-model="editName" label="Nombre del catálogo" :disabled="!canWrite" />
                            <div class="overflow-x-auto">
                                <ConfigurableDataTable
                                    table-id="form-field-catalog-options-edit"
                                    :columns="catalogOptionColumns"
                                    :rows="editRows"
                                    :row-key="(_row, index) => index"
                                    table-class="w-full min-w-[36rem] text-sm"
                                >
                                    <template #value="{ row }">
                                        <input
                                            v-model="(row as OptionRow).value"
                                            class="field-input w-full font-mono text-xs"
                                        />
                                    </template>
                                    <template #label="{ row }">
                                        <input v-model="(row as OptionRow).label" class="field-input w-full" />
                                    </template>
                                    <template #description="{ row }">
                                        <input v-model="(row as OptionRow).description" class="field-input w-full" />
                                    </template>
                                    <template #actions="{ index }">
                                        <div class="table-row-actions">
                                            <IconActionButton
                                                v-if="canWrite"
                                                icon="chevron-up"
                                                label="Subir opción"
                                                :disabled="index === 0"
                                                @click="moveEditRow(index, -1)"
                                            />
                                            <IconActionButton
                                                v-if="canWrite"
                                                icon="chevron-down"
                                                label="Bajar opción"
                                                :disabled="index === editRows.length - 1"
                                                @click="moveEditRow(index, 1)"
                                            />
                                            <IconActionButton
                                                icon="trash"
                                                label="Quitar opción"
                                                variant="danger"
                                                :disabled="!canWrite"
                                                @click="editRows.splice(index, 1)"
                                            />
                                        </div>
                                    </template>
                                </ConfigurableDataTable>
                            </div>
                            <div v-if="canWrite" class="flex flex-wrap gap-2">
                                <AppButton type="button" variant="secondary" @click="editRows.push(emptyRow())">
                                    + Opción
                                </AppButton>
                                <AppButton
                                    type="button"
                                    :disabled="savingCatalogId === c.id"
                                    @click="saveCatalogEdit(c.id)"
                                >
                                    {{ savingCatalogId === c.id ? 'Guardando…' : 'Guardar catálogo' }}
                                </AppButton>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <AppModal :open="showCreateModal && canWrite" title="Nuevo catálogo" size="lg" @close="showCreateModal = false">
            <form id="new-catalog-form" class="space-y-4" @submit.prevent="createCatalog">
                <MaterialField v-model="newCatalogName" label="Nombre del catálogo" required />
                <div class="overflow-x-auto">
                    <ConfigurableDataTable
                        table-id="form-field-catalog-options-new"
                        :columns="catalogOptionColumns"
                        :rows="newCatalogRows"
                        :row-key="(_row, index) => index"
                        table-class="w-full min-w-[36rem] text-sm"
                    >
                        <template #value="{ row }">
                            <input
                                v-model="(row as OptionRow).value"
                                class="field-input w-full font-mono text-xs"
                                placeholder="operativo"
                            />
                        </template>
                        <template #label="{ row }">
                            <input
                                v-model="(row as OptionRow).label"
                                class="field-input w-full"
                                placeholder="Operativo"
                            />
                        </template>
                        <template #description="{ row }">
                            <input
                                v-model="(row as OptionRow).description"
                                class="field-input w-full"
                                placeholder="Ayuda para el técnico"
                            />
                        </template>
                        <template #actions="{ index }">
                            <div class="table-row-actions">
                                <IconActionButton
                                    icon="chevron-up"
                                    label="Subir opción"
                                    :disabled="index === 0"
                                    @click="moveNewRow(index, -1)"
                                />
                                <IconActionButton
                                    icon="chevron-down"
                                    label="Bajar opción"
                                    :disabled="index === newCatalogRows.length - 1"
                                    @click="moveNewRow(index, 1)"
                                />
                                <IconActionButton
                                    v-if="newCatalogRows.length > 1"
                                    icon="trash"
                                    label="Quitar opción"
                                    variant="danger"
                                    @click="newCatalogRows.splice(index, 1)"
                                />
                            </div>
                        </template>
                    </ConfigurableDataTable>
                </div>
                <AppButton type="button" variant="secondary" @click="newCatalogRows.push(emptyRow())">
                    + Fila
                </AppButton>
            </form>
            <template #footer>
                <button
                    type="button"
                    class="text-portal-muted rounded-xl px-4 py-2 text-sm hover:bg-white/5"
                    @click="showCreateModal = false"
                >
                    Cancelar
                </button>
                <AppButton type="submit" form="new-catalog-form">Crear catálogo</AppButton>
            </template>
        </AppModal>
    </div>
</template>
