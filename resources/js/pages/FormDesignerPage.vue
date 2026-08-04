<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import { formUsageLabel } from '@/lib/formUsage';

type Field = {
    key: string;
    type: string;
    label: string;
    required?: boolean;
    option_catalog_id?: number | null;
    allow_multiple?: boolean;
    max_images?: number;
    caption_enabled?: boolean;
    caption_required?: boolean;
};
type Section = { title: string; fields: Field[] };
type OptionCatalog = {
    id: number;
    name: string;
    options: { value: string; label: string; description?: string }[];
};

type FormVersion = {
    id: number;
    version: number;
    status: string;
    schema: { sections: Section[] };
};

type FormDef = {
    id: number;
    name: string;
    usage?: string;
    usage_label?: string;
    versions: FormVersion[];
};

const fieldTypes = [
    { value: 'text', label: 'Texto corto' },
    { value: 'textarea', label: 'Texto largo' },
    { value: 'number', label: 'Número' },
    {
        value: 'select',
        label: 'Lista desplegable',
    },
    {
        value: 'options',
        label: 'Opciones visibles (botones)',
    },
    { value: 'photo', label: 'Imagen' },
];

const fieldTypeHelp: Record<string, string> = {
    select: 'Una sola opción del catálogo en menú desplegable.',
    options: 'Misma validación que el desplegable; el técnico elige con botones y ve la descripción de cada opción.',
};

const route = useRoute();
const router = useRouter();
const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_forms'));

const form = ref<FormDef | null>(null);
const sections = ref<Section[]>([]);
const optionCatalogs = ref<OptionCatalog[]>([]);
const loading = ref(true);
const saving = ref(false);
const initialSnapshot = ref('');

function schemaSnapshot(): string {
    return JSON.stringify(sections.value);
}

function isDirty(): boolean {
    return initialSnapshot.value !== '' && schemaSnapshot() !== initialSnapshot.value;
}

function confirmLeaveCatalogSettings(): boolean {
    if (!isDirty()) {
        return true;
    }

    return window.confirm(
        'Tienes cambios sin guardar en este formulario. Si sales ahora se perderán. ¿Continuar?',
    );
}

function goToCatalogSettings() {
    if (!confirmLeaveCatalogSettings()) {
        return;
    }
    void router.push('/app/design/forms/settings');
}

const draft = computed(() => form.value?.versions.find((v) => v.status === 'draft'));
const published = computed(() =>
    form.value?.versions
        .filter((v) => v.status === 'published')
        .sort((a, b) => b.version - a.version)[0],
);

const catalogSelectOptions = computed(() =>
    optionCatalogs.value.map((c) => ({ value: String(c.id), label: c.name })),
);

async function load() {
    loading.value = true;
    try {
        const res = await api<{
            data: FormDef;
            form_design: { option_catalogs: OptionCatalog[] };
        }>(`/design/forms/${route.params.id}`);
        form.value = res.data;
        optionCatalogs.value = res.form_design?.option_catalogs ?? [];
        const d =
            res.data.versions.find((v) => v.status === 'draft') ??
            [...res.data.versions]
                .filter((v) => v.status === 'published')
                .sort((a, b) => b.version - a.version)[0];
        sections.value = structuredClone(d?.schema?.sections ?? [{ title: 'Sección 1', fields: [] }]);
        initialSnapshot.value = schemaSnapshot();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function addSection() {
    sections.value.push({ title: `Sección ${sections.value.length + 1}`, fields: [] });
}

function addField(sectionIndex: number) {
    const key = `campo_${Date.now()}`;
    sections.value[sectionIndex].fields.push({
        key,
        type: 'text',
        label: 'Nuevo campo',
        required: false,
        option_catalog_id: null,
        allow_multiple: false,
        max_images: 4,
        caption_enabled: false,
        caption_required: false,
    });
}

function onFieldTypeChange(field: Field) {
    if (field.type === 'photo') {
        field.allow_multiple ??= false;
        field.max_images ??= 4;
        field.caption_enabled ??= false;
        field.caption_required ??= false;
    }
}

function removeField(sectionIndex: number, fieldIndex: number) {
    sections.value[sectionIndex].fields.splice(fieldIndex, 1);
}

async function saveDraft(options: { manageSaving?: boolean; silent?: boolean } = {}) {
    const manageSaving = options.manageSaving !== false;
    if (manageSaving) {
        saving.value = true;
    }
    try {
        await api(`/design/forms/${route.params.id}/schema`, {
            method: 'PUT',
            body: JSON.stringify({ schema: { sections: sections.value } }),
        });
        if (!options.silent) {
            toast.success('Borrador guardado.');
        }
        initialSnapshot.value = schemaSnapshot();
        if (manageSaving) {
            await load();
        }
    } catch (e) {
        if (manageSaving) {
            toast.error((e as Error).message);
            return;
        }
        throw e;
    } finally {
        if (manageSaving) {
            saving.value = false;
        }
    }
}

async function publish() {
    if (saving.value) {
        return;
    }
    saving.value = true;
    try {
        // Evita PUT innecesario y no re-habilita el botón a mitad del publish.
        if (isDirty()) {
            await saveDraft({ manageSaving: false, silent: true });
        }
        await api(`/design/forms/${route.params.id}/publish`, { method: 'POST' });
        await load();
        const pub = published.value;
        const d = draft.value;
        toast.success(
            pub
                ? `Versión v${pub.version} publicada.${d ? ` Nuevo borrador v${d.version}.` : ''}`
                : 'Versión publicada.',
        );
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-portal-muted">Cargando…</div>
    <div v-else-if="form" class="portal-page w-full max-w-none">
        <PageHeader
            :title="form.name"
            :subtitle="`Uso: ${form.usage_label ?? formUsageLabel(form.usage ?? 'routine')}. Borrador y publicación del esquema.`"
        />
        <p class="text-portal-muted text-sm">
            <span v-if="published">En producción: v{{ published.version }}.</span>
            <span v-else class="text-amber-500">Sin versión publicada.</span>
            Borrador: v{{ draft?.version ?? '—' }}.
            <button type="button" class="text-portal-link ml-2 underline" @click="goToCatalogSettings">
                Configuración de campos
            </button>
        </p>

        <div class="grid gap-4 xl:grid-cols-2">
            <div
                v-for="(section, si) in sections"
                :key="si"
                class="portal-form-panel"
            >
                <MaterialField v-model="section.title" label="Título de sección" />
                <div
                    v-for="(field, fi) in section.fields"
                    :key="field.key"
                    class="space-y-3 rounded-lg border border-white/10 p-3"
                >
                    <div class="grid gap-3 md:grid-cols-2">
                        <MaterialField v-model="field.label" label="Etiqueta" />
                        <MaterialField v-model="field.key" label="Clave" />
                        <MaterialSelect
                            v-model="field.type"
                            label="Tipo"
                            :options="fieldTypes"
                            @update:model-value="() => onFieldTypeChange(field)"
                        />
                        <p
                            v-if="fieldTypeHelp[field.type]"
                            class="text-portal-muted md:col-span-2 text-xs leading-snug"
                        >
                            {{ fieldTypeHelp[field.type] }}
                        </p>
                        <label class="text-portal-muted flex items-center gap-2 text-sm md:col-span-2">
                            <input v-model="field.required" type="checkbox" :disabled="!canWrite" />
                            Campo obligatorio
                        </label>
                        <MaterialSelect
                            v-if="field.type === 'select' || field.type === 'options'"
                            :model-value="field.option_catalog_id ? String(field.option_catalog_id) : ''"
                            label="Catálogo de opciones"
                            :options="[{ value: '', label: '— Seleccionar —' }, ...catalogSelectOptions]"
                            @update:model-value="
                                (v) => (field.option_catalog_id = v ? Number(v) : null)
                            "
                        />
                        <button
                            v-if="field.type === 'select' || field.type === 'options'"
                            type="button"
                            class="text-portal-link md:col-span-2 text-left text-xs underline"
                            @click="goToCatalogSettings"
                        >
                            Gestionar catálogos (nombre y descripción por opción)
                        </button>
                        <template v-if="field.type === 'photo'">
                            <label class="text-portal-muted flex items-center gap-2 text-sm md:col-span-2">
                                <input v-model="field.allow_multiple" type="checkbox" :disabled="!canWrite" />
                                Permitir varias imágenes
                            </label>
                            <MaterialField
                                v-if="field.allow_multiple"
                                v-model="field.max_images"
                                label="Máximo de imágenes"
                                type="number"
                                class="md:col-span-2"
                            />
                            <p class="text-portal-muted md:col-span-2 text-xs">
                                {{
                                    field.allow_multiple
                                        ? `El técnico puede cargar hasta ${field.max_images ?? 4} imagen(es).`
                                        : 'Una sola imagen por este campo.'
                                }}
                            </p>
                            <label class="text-portal-muted flex items-center gap-2 text-sm md:col-span-2">
                                <input v-model="field.caption_enabled" type="checkbox" :disabled="!canWrite" />
                                Añadir descripción
                            </label>
                            <label
                                v-if="field.caption_enabled"
                                class="text-portal-muted flex items-center gap-2 text-sm md:col-span-2"
                            >
                                <input v-model="field.caption_required" type="checkbox" :disabled="!canWrite" />
                                Descripción obligatoria
                            </label>
                        </template>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <IconActionButton
                            icon="trash"
                            label="Quitar campo"
                            variant="danger"
                            :disabled="!canWrite"
                            @click="removeField(si, fi)"
                        />
                    </div>
                </div>
                <button type="button" class="text-portal-link text-sm underline" :disabled="!canWrite" @click="addField(si)">
                    + Campo
                </button>
            </div>
        </div>

        <button type="button" class="text-portal-link text-sm underline" :disabled="!canWrite" @click="addSection">
            + Sección
        </button>

        <div class="flex flex-wrap gap-2">
            <AppButton type="button" variant="secondary" :disabled="!canWrite || saving" @click="saveDraft">
                Guardar borrador
            </AppButton>
            <AppButton type="button" :disabled="!canWrite || saving" @click="publish">Publicar versión</AppButton>
        </div>
        <p v-if="!canWrite" class="text-portal-muted text-sm">Solo lectura.</p>
    </div>
</template>
