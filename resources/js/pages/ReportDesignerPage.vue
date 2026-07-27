<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api, getToken, getCompanyId } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import RichTextEditor from '@/components/ui/RichTextEditor.vue';
import AppButton from '@/components/ui/AppButton.vue';

type FormFieldOption = { key: string; label: string; form_name: string; field_type?: string };

type Component = {
    type: string;
    text?: string;
    field?: string;
    section_template_id?: number | null;
    align?: string;
    color?: string;
    size_pt?: number;
};

type SectionTemplateRow = { id: number; name: string; slug: string };

type PageSettings = {
    size?: string;
    font_family?: string;
    header?: { enabled?: boolean; text?: string };
    footer?: { enabled?: boolean; text?: string };
    page_number?: { enabled?: boolean; start_at?: number };
    cover_page?: {
        enabled?: boolean;
        title?: string;
        subtitle?: string;
        body?: string;
        show_date?: boolean;
        omit_header_footer?: boolean;
        image_path?: string;
        use_client_logo?: boolean;
        client_id?: number | null;
    };
    typography?: { title_pt?: number; subtitle_pt?: number; body_pt?: number };
};

type ReportVersion = {
    id: number;
    version: number;
    status: string;
    components: Component[];
    page_settings?: PageSettings;
};

type ReportTpl = {
    id: number;
    name: string;
    description?: string | null;
    versions: ReportVersion[];
};

type ClientRow = { id: number; legal_name: string; trade_name?: string | null; logo_url?: string | null };

const route = useRoute();
const toast = useToast();
const tpl = ref<ReportTpl | null>(null);
const templateName = ref('');
const templateDescription = ref('');
const components = ref<Component[]>([]);
const pageSettings = ref<PageSettings>({
    size: 'A4',
    font_family: 'roboto',
    header: { enabled: false, text: '{{company}} · Rutina #{{routine_id}}' },
    footer: { enabled: false, text: 'Documento generado por Noah' },
    page_number: { enabled: false, start_at: 1 },
    cover_page: {
        enabled: false,
        title: 'Informe de mantenimiento',
        subtitle: '{{company}}',
        body: '',
        show_date: true,
        omit_header_footer: true,
    },
    typography: { title_pt: 22, subtitle_pt: 16, body_pt: 11 },
});
const formFields = ref<FormFieldOption[]>([]);
const sectionTemplates = ref<SectionTemplateRow[]>([]);
const loading = ref(true);
const previewUrl = ref<string | null>(null);
const coverImageInput = ref<HTMLInputElement | null>(null);
const coverImageUploading = ref(false);
const coverImagePreview = ref<string | null>(null);
const clientsWithLogo = ref<ClientRow[]>([]);
let previewTimer: ReturnType<typeof setTimeout> | null = null;

function publicStorageUrl(path: string | undefined): string | null {
    if (!path) {
        return null;
    }

    return `/storage/${path}`;
}

const coverImageDisplayUrl = computed(() => {
    if (pageSettings.value.cover_page?.use_client_logo && pageSettings.value.cover_page?.client_id) {
        const client = clientsWithLogo.value.find((c) => c.id === pageSettings.value.cover_page?.client_id);
        if (client?.logo_url) {
            return client.logo_url;
        }
    }

    return coverImagePreview.value ?? publicStorageUrl(pageSettings.value.cover_page?.image_path) ?? null;
});

const clientLogoOptions = computed(() =>
    clientsWithLogo.value.map((c) => ({
        value: String(c.id),
        label: c.trade_name?.trim() || c.legal_name,
    })),
);

const draft = computed(() => tpl.value?.versions.find((v) => v.status === 'draft'));
const published = computed(() =>
    tpl.value?.versions
        .filter((v) => v.status === 'published')
        .sort((a, b) => b.version - a.version)[0],
);

const fontOptions = [
    { value: 'roboto', label: 'Roboto' },
    { value: 'source_sans', label: 'Source Sans 3' },
];

const alignOptions = [
    { value: 'left', label: 'Izquierda' },
    { value: 'center', label: 'Centro' },
    { value: 'right', label: 'Derecha' },
];

const componentTypes = [
    { value: 'title', label: 'Título' },
    { value: 'subtitle', label: 'Subtítulo' },
    { value: 'section_template', label: 'Sección (plantilla)' },
    { value: 'text', label: 'Texto (markdown)' },
    { value: 'paragraph', label: 'Párrafo (campo)' },
    { value: 'image', label: 'Imagen (campo foto)' },
    { value: 'divider', label: 'Divisor' },
];

const sectionTemplateOptions = computed(() =>
    sectionTemplates.value.map((s) => ({ value: String(s.id), label: s.name })),
);

function defaultPhotoField(): string {
    const photo = formFields.value.find((f) => f.field_type === 'photo');
    return photo?.key ?? formFields.value[0]?.key ?? 'foto_equipo';
}

async function load() {
    loading.value = true;
    try {
        const res = await api<{
            data: ReportTpl;
            form_fields: FormFieldOption[];
            section_templates?: SectionTemplateRow[];
        }>(`/design/reports/${route.params.id}`);
        tpl.value = res.data;
        templateName.value = res.data.name;
        templateDescription.value = res.data.description ?? '';
        formFields.value = res.form_fields ?? [];
        sectionTemplates.value = res.section_templates ?? [];
        const d =
            res.data.versions.find((v) => v.status === 'draft') ??
            [...res.data.versions]
                .filter((v) => v.status === 'published')
                .sort((a, b) => b.version - a.version)[0];
        components.value = structuredClone(d?.components ?? []);
        const merged = { ...pageSettings.value, ...(d?.page_settings ?? {}) };
        merged.cover_page = { ...pageSettings.value.cover_page, ...(d?.page_settings?.cover_page ?? {}) };
        if (merged.cover_page.enabled === undefined) {
            merged.cover_page.enabled = false;
        }
        merged.typography = { ...pageSettings.value.typography, ...(d?.page_settings?.typography ?? {}) };
        pageSettings.value = merged;
        await loadClientsWithLogo();
        await refreshPreview();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function refreshPreview() {
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'text/html',
    };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    const res = await fetch(`/api/v1/design/reports/${route.params.id}/preview`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
            components: components.value ?? [],
            page_settings: pageSettings.value,
        }),
    });
    if (!res.ok) {
        const errText = await res.text();
        try {
            const json = JSON.parse(errText) as { message?: string };
            toast.error(json.message ?? 'No se pudo generar la vista previa.');
        } catch {
            toast.error('No se pudo generar la vista previa.');
        }
        return;
    }
    const html = await res.text();
    const blob = new Blob([html], { type: 'text/html' });
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = URL.createObjectURL(blob);
}

function schedulePreview() {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }
    previewTimer = setTimeout(() => {
        void refreshPreview();
    }, 500);
}

function addComponent(type: string) {
    if (type === 'title') {
        components.value.push({ type: 'title', text: 'Título del reporte', align: 'left' });
    } else if (type === 'subtitle') {
        components.value.push({ type: 'subtitle', text: 'Subtítulo', align: 'left' });
    } else if (type === 'text') {
        components.value.push({ type: 'text', text: 'Texto con **negritas**.', align: 'left' });
    } else if (type === 'paragraph') {
        const first = formFields.value.find((f) => f.field_type !== 'photo') ?? formFields.value[0];
        components.value.push({ type: 'paragraph', field: first?.key ?? 'technician_comments', align: 'left' });
    } else if (type === 'image') {
        components.value.push({ type: 'image', field: defaultPhotoField(), align: 'left' });
    } else if (type === 'divider') {
        components.value.push({ type: 'divider', style: 'solid', margin_pt: 16 });
    } else if (type === 'section_template') {
        const first = sectionTemplates.value[0];
        components.value.push({
            type: 'section_template',
            section_template_id: first?.id ?? null,
            align: 'left',
        });
    }
}

function removeComponent(index: number) {
    components.value.splice(index, 1);
}

function moveUp(index: number) {
    if (index <= 0) {
        return;
    }
    const copy = [...components.value];
    [copy[index - 1], copy[index]] = [copy[index], copy[index - 1]];
    components.value = copy;
}

function moveDown(index: number) {
    if (index >= components.value.length - 1) {
        return;
    }
    const copy = [...components.value];
    [copy[index], copy[index + 1]] = [copy[index + 1], copy[index]];
    components.value = copy;
}

async function saveMeta() {
    if (!templateName.value.trim()) {
        return;
    }
    await api(`/design/reports/${route.params.id}`, {
        method: 'PUT',
        body: JSON.stringify({
            name: templateName.value.trim(),
            description: templateDescription.value.trim() || null,
        }),
    });
    if (tpl.value) {
        tpl.value.name = templateName.value.trim();
        tpl.value.description = templateDescription.value.trim() || null;
    }
}

async function save() {
    await saveMeta();
    await api(`/design/reports/${route.params.id}/components`, {
        method: 'PUT',
        body: JSON.stringify({ components: components.value, page_settings: pageSettings.value }),
    });
    toast.success('Borrador guardado.');
    await refreshPreview();
}

async function publish() {
    await save();
    await api(`/design/reports/${route.params.id}/publish`, { method: 'POST' });
    toast.success('Publicado.');
    await load();
}

async function loadClientsWithLogo() {
    try {
        const res = await api<{ data: ClientRow[] }>('/clients');
        clientsWithLogo.value = res.data.filter((c) => Boolean(c.logo_url));
    } catch {
        clientsWithLogo.value = [];
    }
}

function onToggleUseClientLogo() {
    const cover = pageSettings.value.cover_page;
    if (!cover) {
        return;
    }
    if (cover.use_client_logo) {
        cover.image_path = undefined;
        if (!cover.client_id && clientsWithLogo.value.length > 0) {
            cover.client_id = clientsWithLogo.value[0].id;
        }
    } else {
        cover.client_id = null;
    }
    schedulePreview();
}

function onClientLogoSelected(clientId: string) {
    if (!pageSettings.value.cover_page) {
        return;
    }
    pageSettings.value.cover_page.client_id = clientId ? Number(clientId) : null;
    pageSettings.value.cover_page.use_client_logo = true;
    pageSettings.value.cover_page.image_path = undefined;
    schedulePreview();
}

async function uploadCoverImage(file: File) {
    const form = new FormData();
    form.append('image', file);
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {};
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    const res = await fetch(`/api/v1/design/reports/${route.params.id}/cover-image`, {
        method: 'POST',
        headers,
        body: form,
    });
    if (!res.ok) {
        const errText = await res.text();
        throw new Error(errText || 'No se pudo subir la imagen de portada.');
    }
    const json = (await res.json()) as {
        data: { page_settings?: PageSettings; image_path?: string };
    };
    if (json.data.page_settings?.cover_page) {
        pageSettings.value.cover_page = {
            ...pageSettings.value.cover_page,
            ...json.data.page_settings.cover_page,
        };
    } else if (json.data.image_path) {
        pageSettings.value.cover_page = {
            ...pageSettings.value.cover_page,
            image_path: json.data.image_path,
            use_client_logo: false,
            client_id: null,
        };
    }
}

async function onCoverImagePicked(ev: Event) {
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }
    if (coverImagePreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(coverImagePreview.value);
    }
    coverImagePreview.value = URL.createObjectURL(file);
    coverImageUploading.value = true;
    try {
        await uploadCoverImage(file);
        if (coverImagePreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(coverImagePreview.value);
        }
        coverImagePreview.value = null;
        schedulePreview();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        coverImageUploading.value = false;
        input.value = '';
    }
}

async function removeCoverImage() {
    coverImageUploading.value = true;
    try {
        await api(`/design/reports/${route.params.id}/cover-image`, { method: 'DELETE' });
        if (pageSettings.value.cover_page) {
            const { image_path: _removed, ...rest } = pageSettings.value.cover_page;
            pageSettings.value.cover_page = { ...rest, image_path: undefined };
        }
        if (coverImagePreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(coverImagePreview.value);
        }
        coverImagePreview.value = null;
        schedulePreview();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        coverImageUploading.value = false;
    }
}

watch(
    () => [components.value, pageSettings.value],
    () => {
        if (loading.value) {
            return;
        }
        schedulePreview();
    },
    { deep: true },
);

onMounted(load);
onUnmounted(() => {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});
</script>

<template>
    <div v-if="loading" class="text-portal-muted">Cargando…</div>
    <div v-else-if="tpl" class="portal-page w-full max-w-none">
        <PageHeader title="Diseñador de reporte" :subtitle="`Plantilla: ${tpl.name}`" />
        <p class="text-portal-muted text-sm">
            <span v-if="published">En producción: v{{ published.version }}.</span>
            Borrador: v{{ draft?.version }}.
        </p>

        <div class="grid gap-6 xl:grid-cols-[1fr_minmax(320px,42%)]">
            <div class="space-y-4">
                <div class="portal-form-panel grid gap-4 md:grid-cols-2">
                    <MaterialField v-model="templateName" label="Nombre de plantilla" @blur="saveMeta" />
                    <MaterialSelect v-model="pageSettings.font_family" label="Tipografía" :options="fontOptions" />
                    <MaterialField
                        v-model="templateDescription"
                        class="md:col-span-2"
                        label="Descripción (solo interna, no aparece en el PDF)"
                        multiline
                        :rows="2"
                        @blur="saveMeta"
                    />
                </div>

                <div class="portal-form-panel grid gap-3 md:grid-cols-3">
                    <p class="text-portal-heading md:col-span-3 text-sm font-medium">Tamaños de texto (pt)</p>
                    <MaterialField v-model="pageSettings.typography!.title_pt" label="Título" type="number" />
                    <MaterialField v-model="pageSettings.typography!.subtitle_pt" label="Subtítulo" type="number" />
                    <MaterialField v-model="pageSettings.typography!.body_pt" label="Párrafo" type="number" />
                </div>

                <div class="portal-form-panel space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-portal-heading text-sm font-medium">Página de presentación (portada)</h3>
                        <label class="text-portal-muted flex cursor-pointer items-center gap-2 text-sm">
                            <input v-model="pageSettings.cover_page!.enabled" type="checkbox" class="size-4 rounded" />
                            <span class="text-portal-heading font-medium">Incluir portada</span>
                        </label>
                    </div>
                    <p v-if="!pageSettings.cover_page?.enabled" class="text-portal-muted text-sm leading-snug">
                        Activa <strong class="text-portal-heading font-medium">Incluir portada</strong> para configurar
                        imagen, título y texto de la primera página del PDF.
                    </p>
                    <template v-if="pageSettings.cover_page?.enabled">
                        <div
                            class="border-portal-border/50 hover:border-primary-500/40 rounded-xl border-2 border-dashed bg-white/5 p-4 transition-colors"
                        >
                            <p class="text-portal-heading mb-2 text-sm font-medium">Imagen de portada</p>
                            <p class="text-portal-muted mb-3 text-xs">
                                Se muestra centrada <strong>encima del título</strong> en la primera página (JPG, PNG o
                                WebP).
                            </p>
                            <label class="text-portal-muted flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    v-model="pageSettings.cover_page!.use_client_logo"
                                    type="checkbox"
                                    class="size-4 rounded"
                                    @change="onToggleUseClientLogo"
                                />
                                Usar logo de cliente
                            </label>
                            <MaterialSelect
                                v-if="pageSettings.cover_page?.use_client_logo"
                                :model-value="
                                    pageSettings.cover_page?.client_id ? String(pageSettings.cover_page.client_id) : ''
                                "
                                label="Cliente"
                                :options="[
                                    { value: '', label: clientsWithLogo.length ? '— Seleccionar —' : 'Sin logos' },
                                    ...clientLogoOptions,
                                ]"
                                @update:model-value="onClientLogoSelected"
                            />
                            <p
                                v-if="pageSettings.cover_page?.use_client_logo && !clientsWithLogo.length"
                                class="text-portal-muted text-xs"
                            >
                                No hay clientes con logo. Sube uno en
                                <RouterLink to="/app/clients" class="text-portal-link underline">Clientes</RouterLink>.
                            </p>
                            <div
                                v-if="pageSettings.cover_page?.use_client_logo && coverImageDisplayUrl"
                                class="border-portal-border/40 flex min-h-[5.5rem] items-center justify-center rounded-lg border bg-white/80 p-3"
                            >
                                <img
                                    :src="coverImageDisplayUrl"
                                    alt="Logo de cliente"
                                    class="max-h-20 max-w-[12rem] object-contain"
                                />
                            </div>
                            <template v-if="!pageSettings.cover_page?.use_client_logo">
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="border-portal-border/40 flex min-h-[5.5rem] min-w-[8rem] items-center justify-center rounded-lg border bg-white/80 p-2 transition hover:bg-white"
                                    :disabled="coverImageUploading"
                                    @click="coverImageInput?.click()"
                                >
                                    <img
                                        v-if="coverImageDisplayUrl"
                                        :src="coverImageDisplayUrl"
                                        alt="Vista previa portada"
                                        class="max-h-20 max-w-[10rem] object-contain"
                                    />
                                    <span v-else class="text-portal-muted px-2 text-center text-xs">
                                        Clic para elegir imagen
                                    </span>
                                </button>
                                <input
                                    ref="coverImageInput"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    :disabled="coverImageUploading"
                                    @change="onCoverImagePicked"
                                />
                                <div class="flex flex-col gap-2">
                                    <AppButton
                                        type="button"
                                        :disabled="coverImageUploading"
                                        @click="coverImageInput?.click()"
                                    >
                                        {{
                                            coverImageUploading
                                                ? 'Subiendo…'
                                                : coverImageDisplayUrl
                                                  ? 'Cambiar imagen'
                                                  : 'Subir imagen de portada'
                                        }}
                                    </AppButton>
                                    <button
                                        v-if="coverImageDisplayUrl"
                                        type="button"
                                        class="text-portal-muted text-left text-sm underline"
                                        :disabled="coverImageUploading"
                                        @click="removeCoverImage"
                                    >
                                        Quitar imagen
                                    </button>
                                </div>
                            </div>
                            </template>
                        </div>
                        <MaterialField v-model="pageSettings.cover_page!.title" label="Título portada" />
                        <MaterialField v-model="pageSettings.cover_page!.subtitle" label="Subtítulo portada" />
                        <RichTextEditor v-model="pageSettings.cover_page!.body" label="Cuerpo portada" />
                        <label class="text-portal-muted flex items-center gap-2 text-sm">
                            <input v-model="pageSettings.cover_page!.show_date" type="checkbox" />
                            Mostrar fecha
                        </label>
                        <label class="text-portal-muted flex items-center gap-2 text-sm">
                            <input v-model="pageSettings.cover_page!.omit_header_footer" type="checkbox" />
                            Omitir cabecera y pie en la portada
                        </label>
                    </template>
                </div>

                <div class="portal-form-panel space-y-3">
                    <h3 class="text-portal-heading text-sm font-medium">Encabezado y pie</h3>
                    <label class="text-portal-muted flex items-center gap-2 text-sm">
                        <input v-model="pageSettings.header!.enabled" type="checkbox" />
                        Mostrar encabezado
                    </label>
                    <MaterialField
                        v-if="pageSettings.header?.enabled"
                        v-model="pageSettings.header!.text"
                        label="Texto encabezado"
                        multiline
                        :rows="2"
                    />
                    <label class="text-portal-muted flex items-center gap-2 text-sm">
                        <input v-model="pageSettings.footer!.enabled" type="checkbox" />
                        Mostrar pie
                    </label>
                    <MaterialField
                        v-if="pageSettings.footer?.enabled"
                        v-model="pageSettings.footer!.text"
                        label="Texto pie"
                        multiline
                        :rows="2"
                    />
                    <label class="text-portal-muted flex items-center gap-2 text-sm">
                        <input v-model="pageSettings.page_number!.enabled" type="checkbox" />
                        Numeración de páginas
                    </label>
                    <MaterialField
                        v-if="pageSettings.page_number?.enabled"
                        v-model="pageSettings.page_number!.start_at"
                        label="Comenzar conteo en página"
                        type="number"
                    />
                </div>

                <ul class="space-y-2">
                    <li v-for="(c, i) in components" :key="i" class="portal-form-panel space-y-2 text-sm">
                        <div class="flex flex-wrap justify-between gap-2">
                            <span class="text-portal-muted font-mono text-xs">{{ c.type }} #{{ i + 1 }}</span>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="text-portal-link underline" :disabled="i === 0" @click="moveUp(i)">
                                    ↑
                                </button>
                                <button
                                    type="button"
                                    class="text-portal-link underline"
                                    :disabled="i === components.length - 1"
                                    @click="moveDown(i)"
                                >
                                    ↓
                                </button>
                                <button type="button" class="text-red-400 underline" @click="removeComponent(i)">Eliminar</button>
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <MaterialSelect v-model="c.align" label="Alineación" :options="alignOptions" />
                            <MaterialField v-model="c.color" label="Color (#RRGGBB)" placeholder="#111111" />
                            <MaterialField v-model="c.size_pt" label="Tamaño (pt)" type="number" />
                        </div>
                        <MaterialField
                            v-if="c.type === 'title' || c.type === 'subtitle'"
                            v-model="c.text"
                            :label="c.type === 'title' ? 'Título' : 'Subtítulo'"
                        />
                        <RichTextEditor v-else-if="c.type === 'text'" v-model="c.text" label="Texto enriquecido" />
                        <MaterialSelect
                            v-else-if="c.type === 'paragraph' || c.type === 'image'"
                            v-model="c.field"
                            label="Campo del formulario"
                            :options="formFields.map((f) => ({ value: f.key, label: f.label }))"
                        />
                        <MaterialSelect
                            v-else-if="c.type === 'section_template'"
                            :model-value="c.section_template_id ? String(c.section_template_id) : ''"
                            label="Sección reutilizable"
                            :options="[
                                { value: '', label: sectionTemplates.length ? '— Seleccionar —' : 'Sin secciones' },
                                ...sectionTemplateOptions,
                            ]"
                            @update:model-value="(v) => (c.section_template_id = v ? Number(v) : null)"
                        />
                        <RouterLink
                            v-if="c.type === 'section_template'"
                            to="/app/design/reports/settings"
                            class="text-portal-link text-xs underline"
                        >
                            Gestionar secciones en configuración de reportes
                        </RouterLink>
                    </li>
                </ul>

                <div class="flex flex-wrap gap-2">
                    <AppButton
                        v-for="t in componentTypes"
                        :key="t.value"
                        type="button"
                        variant="secondary"
                        @click="addComponent(t.value)"
                    >
                        + {{ t.label }}
                    </AppButton>
                </div>

                <div class="flex gap-2">
                    <AppButton type="button" variant="secondary" @click="save">Guardar</AppButton>
                    <AppButton type="button" @click="publish">Publicar</AppButton>
                </div>
            </div>

            <div class="portal-form-panel sticky top-4 flex h-[min(80vh,720px)] flex-col overflow-hidden">
                <p class="text-portal-heading mb-2 shrink-0 text-sm font-medium">Vista previa en vivo</p>
                <div class="report-designer-preview min-h-0 flex-1 overflow-x-hidden overflow-y-auto rounded-lg border border-white/10 bg-slate-200">
                    <iframe
                        v-if="previewUrl"
                        :src="previewUrl"
                        class="block h-full min-h-[640px] w-full border-0 bg-transparent"
                        title="Vista previa del reporte"
                        scrolling="yes"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
