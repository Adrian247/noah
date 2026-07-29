<script setup lang="ts">
import { computed, ref } from 'vue';
import { getToken, getCompanyId } from '@/api/client';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import FormFieldPhotoThumb from '@/components/domain/FormFieldPhotoThumb.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';

export type FormField = {
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

export type PhotoItem = { path: string; caption?: string };

export type FormSection = {
    title?: string;
    fields: FormField[];
};

export type FormDesignSettings = {
    max_image_size_kb: number;
    allowed_image_mimes: string[];
};

export type OptionCatalog = {
    id: number;
    name: string;
    options: { value: string; label: string; description?: string }[];
};

const props = withDefaults(
    defineProps<{
        schema: { sections?: FormSection[] } | null | undefined;
        disabled?: boolean;
        routineId?: number;
        formSettings?: FormDesignSettings | null;
        optionCatalogs?: OptionCatalog[];
        highlightKeys?: string[];
        /** Una sección del esquema por fila (recomendado en modales y fichas largas). */
        stackSections?: boolean;
    }>(),
    { stackSections: true },
);

const model = defineModel<Record<string, unknown>>({ default: () => ({}) });

const sections = computed(() => props.schema?.sections ?? []);
const catalogMap = computed(() => {
    const map = new Map<number, OptionCatalog>();
    for (const c of props.optionCatalogs ?? []) {
        map.set(c.id, c);
    }
    return map;
});

const uploadError = ref<string | null>(null);
const uploadingKey = ref<string | null>(null);

function fieldValue(key: string): string {
    const v = model.value[key];
    return v === undefined || v === null ? '' : String(v);
}

function updateField(key: string, value: unknown) {
    model.value = { ...model.value, [key]: value };
}

function selectOptions(field: FormField) {
    const catalogId = field.option_catalog_id;
    if (!catalogId) {
        return [];
    }
    const catalog = catalogMap.value.get(catalogId);
    return (catalog?.options ?? []).map((o) => ({ value: o.value, label: o.label }));
}

function optionDescription(field: FormField, value: string): string {
    const catalogId = field.option_catalog_id;
    if (!catalogId) {
        return '';
    }
    const catalog = catalogMap.value.get(catalogId);
    const match = catalog?.options.find((o) => o.value === value);
    return match?.description?.trim() ?? '';
}

function acceptMimes(): string {
    return (props.formSettings?.allowed_image_mimes ?? ['image/jpeg', 'image/png', 'image/webp']).join(',');
}

function photoItems(field: FormField): PhotoItem[] {
    const raw = model.value[field.key];
    if (raw === undefined || raw === null || raw === '') {
        return [];
    }
    if (typeof raw === 'string') {
        return [{ path: raw }];
    }
    if (Array.isArray(raw)) {
        return raw.map((entry) => {
            if (typeof entry === 'string') {
                return { path: entry };
            }
            const obj = entry as PhotoItem;
            return { path: obj.path, caption: obj.caption };
        });
    }
    if (typeof raw === 'object' && raw !== null && 'path' in raw) {
        const obj = raw as PhotoItem;
        return [{ path: obj.path, caption: obj.caption }];
    }
    return [];
}

function setPhotoItems(field: FormField, items: PhotoItem[]) {
    const captionOn = field.caption_enabled;
    const multiple = field.allow_multiple;

    if (items.length === 0) {
        const next = { ...model.value };
        delete next[field.key];
        model.value = next;
        return;
    }

    if (multiple) {
        model.value = {
            ...model.value,
            [field.key]: items.map((i) => (captionOn ? { path: i.path, caption: i.caption ?? '' } : { path: i.path })),
        };
        return;
    }

    const one = items[0];
    if (captionOn) {
        model.value = { ...model.value, [field.key]: { path: one.path, caption: one.caption ?? '' } };
    } else {
        model.value = { ...model.value, [field.key]: one.path };
    }
}

function updateCaption(field: FormField, index: number, caption: string) {
    const items = photoItems(field);
    if (!items[index]) {
        return;
    }
    items[index] = { ...items[index], caption };
    setPhotoItems(field, items);
}

function removePhoto(field: FormField, index: number) {
    const items = photoItems(field);
    items.splice(index, 1);
    setPhotoItems(field, items);
}

function maxPhotos(field: FormField): number {
    return field.allow_multiple ? Math.max(1, field.max_images ?? 4) : 1;
}

function fieldSpansFullWidth(field: FormField): boolean {
    return field.type === 'photo' || field.type === 'textarea';
}

function fieldGridClass(field: FormField): string {
    if (fieldSpansFullWidth(field)) {
        return 'sm:col-span-2';
    }
    if (field.type === 'options') {
        return 'sm:col-span-2';
    }
    return '';
}

function fieldHighlightClass(field: FormField): string {
    if (!props.highlightKeys?.includes(field.key)) {
        return '';
    }
    return 'ring-2 ring-red-500/90 ring-offset-2 ring-offset-[rgb(10,12,18)] rounded-xl bg-red-500/5';
}

function fieldDomId(field: FormField): string {
    return `routine-field-${field.key}`;
}

async function onPhotosSelected(field: FormField, event: Event) {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    input.value = '';
    if (!files.length || !props.routineId) {
        return;
    }

    const maxKb = props.formSettings?.max_image_size_kb ?? 2048;
    const current = photoItems(field);
    const room = maxPhotos(field) - current.length;
    const batch = files.slice(0, room);

    for (const file of batch) {
        if (file.size > maxKb * 1024) {
            uploadError.value = `La imagen supera ${maxKb} KB.`;
            continue;
        }
        uploadError.value = null;
        uploadingKey.value = field.key;
        try {
            const body = new FormData();
            body.append('field_key', field.key);
            body.append('file', file);
            const headers: Record<string, string> = { Accept: 'application/json' };
            const token = getToken();
            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }
            const companyId = getCompanyId();
            if (companyId) {
                headers['X-Company-Id'] = companyId;
            }
            const res = await fetch(`/api/v1/routines/${props.routineId}/form-field-upload`, {
                method: 'POST',
                headers,
                body,
            });
            const text = await res.text();
            let data: { message?: string; data?: { path?: string } } | null = null;
            if (text.trim().startsWith('<')) {
                throw new Error('El servidor devolvió una página HTML en lugar de JSON.');
            }
            try {
                data = text ? JSON.parse(text) : null;
            } catch {
                throw new Error('Respuesta no válida al subir la imagen.');
            }
            if (!res.ok) {
                throw new Error(data?.message ?? res.statusText);
            }
            const path = data?.data?.path as string;
            current.push({ path, caption: '' });
            setPhotoItems(field, [...current]);
        } catch (e) {
            uploadError.value = (e as Error).message;
            break;
        } finally {
            uploadingKey.value = null;
        }
    }
}
</script>

<template>
    <div
        v-if="sections.length"
        class="gap-4"
        :class="stackSections ? 'flex flex-col' : 'grid xl:grid-cols-2'"
    >
        <p v-if="uploadError" class="text-sm text-red-400" :class="{ 'xl:col-span-2': !stackSections }">
            {{ uploadError }}
        </p>
        <section
            v-for="(section, idx) in sections"
            :key="idx"
            class="portal-form-panel w-full min-w-0"
        >
            <h3 v-if="section.title" class="text-portal-heading text-sm font-medium">
                {{ section.title }}
            </h3>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <template v-for="field in section.fields" :key="field.key">
                    <div :id="fieldDomId(field)" :class="[fieldGridClass(field), fieldHighlightClass(field)]">
                    <MaterialSelect
                        v-if="field.type === 'select'"
                        :model-value="fieldValue(field.key)"
                        :label="field.label + (field.required ? ' *' : '')"
                        :options="selectOptions(field)"
                        :required="field.required"
                        :disabled="disabled || !selectOptions(field).length"
                        @update:model-value="(v) => updateField(field.key, String(v))"
                    />
                    <fieldset
                        v-else-if="field.type === 'options'"
                        class="portal-form-panel space-y-2 border-white/10 p-2"
                        :class="fieldHighlightClass(field) ? 'border-red-500/60' : ''"
                        :disabled="disabled || !selectOptions(field).length"
                    >
                        <legend class="text-portal-heading px-1 text-sm font-medium">
                            {{ field.label }}<span v-if="field.required" class="text-amber-500"> *</span>
                        </legend>
                        <p v-if="!selectOptions(field).length" class="text-portal-muted text-xs">
                            Sin opciones en el catálogo asignado.
                        </p>
                        <label
                            v-for="opt in selectOptions(field)"
                            :key="opt.value"
                            class="flex cursor-pointer gap-3 rounded-lg border border-white/10 p-3 transition hover:border-amber-500/30"
                            :class="fieldValue(field.key) === opt.value ? 'border-amber-500/40 bg-amber-500/5' : ''"
                        >
                            <input
                                type="radio"
                                class="mt-1"
                                :name="field.key"
                                :value="opt.value"
                                :checked="fieldValue(field.key) === opt.value"
                                :required="field.required"
                                @change="updateField(field.key, opt.value)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="text-portal-heading block text-sm font-medium">{{ opt.label }}</span>
                                <span
                                    v-if="optionDescription(field, opt.value)"
                                    class="text-portal-muted mt-1 block text-xs leading-snug"
                                >
                                    {{ optionDescription(field, opt.value) }}
                                </span>
                            </span>
                        </label>
                    </fieldset>
                    <div
                        v-else-if="field.type === 'photo'"
                        class="portal-media-upload space-y-3 rounded-xl p-2"
                        :class="fieldHighlightClass(field)"
                    >
                        <p class="text-portal-heading text-sm font-medium">
                            {{ field.label }}<span v-if="field.required" class="text-amber-500"> *</span>
                        </p>
                        <p class="text-portal-muted text-xs">
                            Máx. {{ formSettings?.max_image_size_kb ?? 2048 }} KB por imagen
                            <span v-if="field.allow_multiple">
                                · hasta {{ maxPhotos(field) }} fotos (puedes elegir varias a la vez)
                            </span>
                        </p>
                        <ul v-if="photoItems(field).length" class="space-y-3">
                            <li
                                v-for="(item, pi) in photoItems(field)"
                                :key="item.path + pi"
                                class="rounded-lg border border-white/10 p-3"
                            >
                                <FormFieldPhotoThumb
                                    v-if="routineId"
                                    :routine-id="routineId"
                                    :path="item.path"
                                />
                                <p v-else class="text-portal-muted truncate font-mono text-xs">{{ item.path }}</p>
                                <MaterialField
                                    v-if="field.caption_enabled"
                                    :model-value="item.caption ?? ''"
                                    :label="'Descripción' + (field.caption_required ? ' *' : '')"
                                    class="mt-2"
                                    :disabled="disabled"
                                    @update:model-value="(v) => updateCaption(field, pi, v)"
                                />
                                <IconActionButton
                                    v-if="!disabled"
                                    class="mt-2"
                                    icon="trash"
                                    label="Quitar foto"
                                    variant="danger"
                                    @click="removePhoto(field, pi)"
                                />
                            </li>
                        </ul>
                        <input
                            v-if="!disabled && photoItems(field).length < maxPhotos(field)"
                            type="file"
                            :accept="acceptMimes()"
                            :multiple="field.allow_multiple"
                            :disabled="uploadingKey === field.key"
                            class="text-portal-muted text-sm"
                            @change="onPhotosSelected(field, $event)"
                        />
                        <p v-if="uploadingKey === field.key" class="text-portal-muted text-xs">Subiendo…</p>
                    </div>
                    <MaterialField
                        v-else-if="field.type === 'textarea'"
                        :model-value="fieldValue(field.key)"
                        :label="field.label + (field.required ? ' *' : '')"
                        multiline
                        :required="field.required"
                        :disabled="disabled"
                        @update:model-value="(v) => updateField(field.key, v)"
                    />
                    <MaterialField
                        v-else-if="field.type === 'number'"
                        :model-value="fieldValue(field.key)"
                        :label="field.label + (field.required ? ' *' : '')"
                        type="number"
                        :required="field.required"
                        :disabled="disabled"
                        @update:model-value="(v) => updateField(field.key, v)"
                    />
                    <MaterialField
                        v-else
                        :model-value="fieldValue(field.key)"
                        :label="field.label + (field.required ? ' *' : '')"
                        :required="field.required"
                        :disabled="disabled"
                        @update:model-value="(v) => updateField(field.key, v)"
                    />
                    </div>
                </template>
            </div>
        </section>
    </div>
</template>
