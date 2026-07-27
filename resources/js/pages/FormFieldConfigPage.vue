<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { RouterLink } from 'vue-router';

type OptionRow = { value: string; label: string; description?: string };
type Catalog = { id: number; name: string; slug: string; options: OptionRow[] };
type FormSettings = { max_image_size_kb: number; allowed_image_mimes: string[] };

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_forms'));

const catalogs = ref<Catalog[]>([]);
const settings = ref<FormSettings>({ max_image_size_kb: 2048, allowed_image_mimes: ['image/jpeg', 'image/png', 'image/webp'] });
const loading = ref(true);
const savingCatalogId = ref<number | null>(null);

const newCatalogName = ref('');
const newCatalogRows = ref<OptionRow[]>([
    { value: '', label: '', description: '' },
    { value: '', label: '', description: '' },
]);

const editingId = ref<number | null>(null);
const editName = ref('');
const editRows = ref<OptionRow[]>([]);

const mimeOptions = [
    { value: 'image/jpeg', label: 'JPEG' },
    { value: 'image/png', label: 'PNG' },
    { value: 'image/webp', label: 'WebP' },
    { value: 'image/gif', label: 'GIF' },
];

const selectedMimes = ref<string[]>([]);

function emptyRow(): OptionRow {
    return { value: '', label: '', description: '' };
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

function startEdit(catalog: Catalog) {
    editingId.value = catalog.id;
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

function cancelEdit() {
    editingId.value = null;
    editName.value = '';
    editRows.value = [];
}

async function saveCatalogEdit() {
    if (editingId.value === null) {
        return;
    }
    const options = normalizeRows(editRows.value);
    if (!editName.value.trim() || options.length === 0) {
        toast.warning('Indica nombre del catálogo y al menos una opción con valor y nombre.');
        return;
    }
    savingCatalogId.value = editingId.value;
    try {
        await api(`/design/forms/option-catalogs/${editingId.value}`, {
            method: 'PUT',
            body: JSON.stringify({ name: editName.value.trim(), options }),
        });
        await load();
        cancelEdit();
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
        newCatalogName.value = '';
        newCatalogRows.value = [emptyRow(), emptyRow()];
        await load();
        toast.success('Catálogo creado.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function removeCatalog(id: number) {
    if (!window.confirm('¿Eliminar catálogo? Los campos que lo usen dejarán de validar opciones.')) {
        return;
    }
    await api(`/design/forms/option-catalogs/${id}`, { method: 'DELETE' });
    if (editingId.value === id) {
        cancelEdit();
    }
    await load();
}

onMounted(load);
</script>

<template>
    <div class="portal-page space-y-6">
        <PageHeader
            title="Configuración de campos"
            subtitle="Catálogos reutilizables (nombre y descripción por opción) y reglas de imágenes en rutinas."
        />
        <RouterLink to="/app/design/forms">
            <AppButton type="button" variant="secondary">← Volver a formularios</AppButton>
        </RouterLink>

        <p v-if="loading" class="text-portal-muted">Cargando…</p>

        <template v-else>
            <section class="portal-form-panel max-w-2xl space-y-4">
                <h3 class="text-portal-heading font-medium">Imágenes en formularios</h3>
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
            </section>

            <section class="space-y-4">
                <h3 class="text-portal-heading font-medium">Catálogos de opciones</h3>
                <p class="text-portal-muted max-w-3xl text-sm">
                    Cada opción tiene <strong class="text-portal-heading">valor</strong> (interno),
                    <strong class="text-portal-heading">nombre</strong> (visible) y
                    <strong class="text-portal-heading">descripción</strong> (ayuda al técnico en campo).
                </p>

                <div v-for="c in catalogs" :key="c.id" class="portal-form-panel space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-portal-heading font-medium">{{ c.name }}</p>
                            <p class="text-portal-muted font-mono text-xs">{{ c.slug }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton
                                v-if="canWrite && editingId !== c.id"
                                type="button"
                                variant="secondary"
                                @click="startEdit(c)"
                            >
                                Editar
                            </AppButton>
                            <button
                                v-if="canWrite"
                                type="button"
                                class="text-sm text-red-400"
                                @click="removeCatalog(c.id)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>

                    <div v-if="editingId === c.id" class="space-y-3 border-t border-white/10 pt-3">
                        <MaterialField v-model="editName" label="Nombre del catálogo" :disabled="!canWrite" />
                        <div class="overflow-x-auto">
                            <table class="portal-data-table w-full min-w-[36rem] text-sm">
                                <thead>
                                    <tr>
                                        <th>Valor</th>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, idx) in editRows" :key="idx">
                                        <td>
                                            <input v-model="row.value" class="field-input w-full font-mono text-xs" />
                                        </td>
                                        <td>
                                            <input v-model="row.label" class="field-input w-full" />
                                        </td>
                                        <td>
                                            <input v-model="row.description" class="field-input w-full" />
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                class="text-xs text-red-400"
                                                @click="editRows.splice(idx, 1)"
                                            >
                                                Quitar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton type="button" variant="secondary" @click="editRows.push(emptyRow())">
                                + Opción
                            </AppButton>
                            <AppButton
                                type="button"
                                :disabled="savingCatalogId === c.id"
                                @click="saveCatalogEdit"
                            >
                                {{ savingCatalogId === c.id ? 'Guardando…' : 'Guardar catálogo' }}
                            </AppButton>
                            <button type="button" class="text-portal-muted text-sm underline" @click="cancelEdit">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <ul v-else class="divide-y divide-white/5 rounded-lg border border-white/10">
                        <li
                            v-for="o in c.options"
                            :key="o.value"
                            class="px-3 py-2 text-sm"
                        >
                            <p class="text-portal-heading font-medium">
                                <span class="text-portal-muted font-mono text-xs">{{ o.value }}</span>
                                — {{ o.label }}
                            </p>
                            <p v-if="o.description" class="text-portal-muted mt-0.5 text-xs leading-snug">
                                {{ o.description }}
                            </p>
                        </li>
                    </ul>
                </div>

                <form v-if="canWrite" class="portal-form-panel max-w-4xl space-y-4" @submit.prevent="createCatalog">
                    <h4 class="text-portal-heading text-sm font-medium">Nuevo catálogo</h4>
                    <MaterialField v-model="newCatalogName" label="Nombre del catálogo" required />
                    <div class="overflow-x-auto">
                        <table class="portal-data-table w-full min-w-[36rem] text-sm">
                            <thead>
                                <tr>
                                    <th>Valor</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in newCatalogRows" :key="idx">
                                    <td>
                                        <input v-model="row.value" class="field-input w-full font-mono text-xs" placeholder="operativo" />
                                    </td>
                                    <td>
                                        <input v-model="row.label" class="field-input w-full" placeholder="Operativo" />
                                    </td>
                                    <td>
                                        <input
                                            v-model="row.description"
                                            class="field-input w-full"
                                            placeholder="Texto de ayuda para el técnico"
                                        />
                                    </td>
                                    <td>
                                        <button
                                            v-if="newCatalogRows.length > 1"
                                            type="button"
                                            class="text-xs text-red-400"
                                            @click="newCatalogRows.splice(idx, 1)"
                                        >
                                            Quitar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <AppButton type="button" variant="secondary" @click="newCatalogRows.push(emptyRow())">
                        + Fila
                    </AppButton>
                    <AppButton type="submit">Crear catálogo</AppButton>
                </form>
            </section>
        </template>

    </div>
</template>
