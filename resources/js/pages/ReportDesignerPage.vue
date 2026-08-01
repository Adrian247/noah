<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { api, getToken, getCompanyId } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { useModuleAccess } from '@/composables/useModuleAccess';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import ColorPickerField from '@/components/ui/ColorPickerField.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import RichTextEditor from '@/components/ui/RichTextEditor.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import ReportDesignNav from '@/components/reports/ReportDesignNav.vue';

type FormFieldOption = { key: string; label: string; form_name: string; field_type?: string };

type Component = {
    type: string;
    text?: string;
    field?: string;
    label?: string;
    show_field_key?: boolean;
    section_template_id?: number | null;
    align?: string;
    color?: string;
    size_pt?: number;
    style?: string;
    margin_pt?: number;
};

type SectionTemplateRow = { id: number; name: string; slug: string };

type PageSettings = {
    size?: string;
    font_family?: string;
    theme?: {
        preset_id?: string;
        section_style?: 'card' | 'line' | 'minimal';
        colors?: {
            primary?: string;
            secondary?: string;
            accent?: string;
            text?: string;
            muted?: string;
            border?: string;
            cover_bg?: string;
            cover_text?: string;
            header_bg?: string;
        };
    };
    header?: { enabled?: boolean; text?: string };
    footer?: { enabled?: boolean; text?: string };
    page_number?: { enabled?: boolean; start_at?: number };
    cover_page?: {
        enabled?: boolean;
        title?: string;
        subtitle?: string;
        body?: string;
        show_date?: boolean;
        /** YYYY-MM-DD; vacío = fecha de la rutina al generar el informe */
        date_fixed?: string;
        omit_header_footer?: boolean;
        image_path?: string;
        logo_source?: 'none' | 'company' | 'client' | 'custom';
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

type DesignPreset = {
    id: string;
    label: string;
    description: string;
    layout: string;
    swatch: { primary: string; secondary: string; accent: string };
};

type RoutineFormSource = { slug: string; name: string };

const defaultThemeColors = {
    primary: '#d97706',
    accent: '#f59e0b',
    cover_bg: '#1e3a5f',
    cover_text: '#f8fafc',
} as const;

const designPresets = ref<DesignPreset[]>([]);
const routineForms = ref<RoutineFormSource[]>([]);
const selectedPresetId = ref('phoenix_industrial');
const selectedFormSlug = ref('');
const applyingPreset = ref(false);
const presetMode = ref<'full' | 'theme_only'>('theme_only');
const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('design_reports'));
const canDeleteTemplate = canWrite;
const dirty = ref(false);
const suppressDirty = ref(false);
const deletingTemplate = ref(false);
const tpl = ref<ReportTpl | null>(null);
const templateName = ref('');
const templateDescription = ref('');
const components = ref<Component[]>([]);
const pageSettings = ref<PageSettings>({
    size: 'A4',
    font_family: 'roboto',
    header: { enabled: false, text: '{{company}} · Rutina #{{routine_id}}' },
    footer: { enabled: false, text: 'Documento generado por Phoenix' },
    page_number: { enabled: false, start_at: 1 },
    cover_page: {
        enabled: false,
        title: 'Informe de mantenimiento',
        subtitle: '{{company}}',
        body: '',
        show_date: true,
        date_fixed: '',
        omit_header_footer: true,
        logo_source: 'none',
    },
    typography: { title_pt: 22, subtitle_pt: 16, body_pt: 11 },
    theme: { colors: { ...defaultThemeColors } },
});
const formFields = ref<FormFieldOption[]>([]);
const sectionTemplates = ref<SectionTemplateRow[]>([]);
const orphanFields = ref<string[]>([]);
type RoutineTypeLinkRow = {
    routine_type_id: number;
    routine_type_name: string;
    form_slug?: string | null;
    form_name?: string | null;
    aligned_with_draft: boolean;
    missing: string[];
};
const routineTypeLinks = ref<RoutineTypeLinkRow[]>([]);

const misalignedRoutineTypes = computed(() =>
    routineTypeLinks.value.filter((link) => !link.aligned_with_draft),
);
const loading = ref(true);
const previewUrl = ref<string | null>(null);
/** Blob URL sin fragmento (para revoke). */
const previewObjectUrl = ref<string | null>(null);
const coverImageInput = ref<HTMLInputElement | null>(null);
const coverImageUploading = ref(false);
const coverImagePreview = ref<string | null>(null);
type CompanyBranding = { name?: string | null; logo_url?: string | null };

const companyBranding = ref<CompanyBranding>({});
const clientsWithLogo = ref<ClientRow[]>([]);
const downloadingPreview = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | null = null;

function publicStorageUrl(path: string | undefined): string | null {
    if (!path) {
        return null;
    }

    return `/storage/${path}`;
}

const logoSourceOptions = [
    { value: 'none', label: 'Sin logo en portada' },
    { value: 'company', label: 'Logo de la empresa (tenant)' },
    { value: 'client', label: 'Logo de cliente de facturación' },
    { value: 'custom', label: 'Imagen personalizada' },
];

function normalizeCoverPage(cover: PageSettings['cover_page']): PageSettings['cover_page'] {
    if (!cover) {
        return cover;
    }
    const next = { ...cover };
    if (!next.logo_source) {
        if (next.use_client_logo) {
            next.logo_source = 'client';
        } else if (next.image_path) {
            next.logo_source = 'custom';
        } else {
            next.logo_source = 'none';
        }
    }
    return next;
}

function ensureThemeColors(settings: PageSettings): void {
    settings.theme ??= {};
    settings.theme.colors = {
        ...defaultThemeColors,
        ...settings.theme.colors,
    };
}

const coverLogoSource = computed({
    get: () => pageSettings.value.cover_page?.logo_source ?? 'none',
    set: (value: 'none' | 'company' | 'client' | 'custom') => {
        if (!pageSettings.value.cover_page) {
            return;
        }
        pageSettings.value.cover_page.logo_source = value;
        pageSettings.value.cover_page.use_client_logo = value === 'client';
        if (value !== 'client') {
            pageSettings.value.cover_page.client_id = null;
        } else if (!pageSettings.value.cover_page.client_id && clientsWithLogo.value.length) {
            pageSettings.value.cover_page.client_id = clientsWithLogo.value[0].id;
        }
        if (value !== 'custom') {
            pageSettings.value.cover_page.image_path = undefined;
            if (coverImagePreview.value?.startsWith('blob:')) {
                URL.revokeObjectURL(coverImagePreview.value);
            }
            coverImagePreview.value = null;
        }
        schedulePreview();
    },
});

const coverImageDisplayUrl = computed(() => {
    const cover = pageSettings.value.cover_page;
    const source = cover?.logo_source ?? 'none';
    if (source === 'client' && cover?.client_id) {
        const client = clientsWithLogo.value.find((c) => c.id === cover.client_id);
        if (client?.logo_url) {
            return client.logo_url;
        }
    }
    if (source === 'company' && companyBranding.value.logo_url) {
        return companyBranding.value.logo_url;
    }
    if (source === 'custom') {
        return coverImagePreview.value ?? publicStorageUrl(cover?.image_path) ?? null;
    }
    return null;
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

const paragraphFieldOptions = computed(() =>
    formFields.value
        .filter((f) => f.field_type !== 'photo')
        .map((f) => ({ value: f.key, label: f.label })),
);

const imageFieldOptions = computed(() => {
    const photos = formFields.value.filter((f) => f.field_type === 'photo');
    const source = photos.length ? photos : formFields.value;
    return source.map((f) => ({ value: f.key, label: f.label }));
});

const liveOrphanFields = computed(() => {
    const known = new Set(formFields.value.map((f) => f.key));
    const orphans = new Set<string>();
    for (const c of components.value) {
        if ((c.type === 'paragraph' || c.type === 'image') && c.field && !known.has(c.field)) {
            orphans.add(c.field);
        }
        if (c.type === 'image' && c.field) {
            const meta = formFields.value.find((f) => f.key === c.field);
            if (meta && meta.field_type && meta.field_type !== 'photo') {
                orphans.add(c.field);
            }
        }
    }
    for (const key of orphanFields.value) {
        if (!known.has(key)) {
            orphans.add(key);
        }
    }
    return [...orphans];
});

function fieldOptionsForComponent(type: string) {
    return type === 'image' ? imageFieldOptions.value : paragraphFieldOptions.value;
}

function defaultPhotoField(): string {
    const photo = formFields.value.find((f) => f.field_type === 'photo');
    return photo?.key ?? formFields.value[0]?.key ?? 'foto_equipo';
}

async function load() {
    loading.value = true;
    suppressDirty.value = true;
    try {
        const res = await api<{
            data: ReportTpl;
            form_fields: FormFieldOption[];
            section_templates?: SectionTemplateRow[];
            routine_forms?: RoutineFormSource[];
            company_branding?: CompanyBranding;
            field_alignment?: { orphan_fields?: string[]; aligned?: boolean };
            routine_type_links?: RoutineTypeLinkRow[];
        }>(`/design/reports/${route.params.id}`);
        tpl.value = res.data;
        companyBranding.value = res.company_branding ?? {};
        templateName.value = res.data.name;
        templateDescription.value = res.data.description ?? '';
        formFields.value = res.form_fields ?? [];
        sectionTemplates.value = res.section_templates ?? [];
        routineForms.value = res.routine_forms ?? [];
        routineTypeLinks.value = res.routine_type_links ?? [];
        const linkedFormSlug = routineTypeLinks.value.find((l) => l.form_slug)?.form_slug;
        if (linkedFormSlug) {
            selectedFormSlug.value = linkedFormSlug;
        } else if (!selectedFormSlug.value && routineForms.value.length) {
            selectedFormSlug.value = routineForms.value[0].slug;
        }
        orphanFields.value = res.field_alignment?.orphan_fields ?? [];
        const d =
            res.data.versions.find((v) => v.status === 'draft') ??
            [...res.data.versions]
                .filter((v) => v.status === 'published')
                .sort((a, b) => b.version - a.version)[0];
        components.value = structuredClone(d?.components ?? []);
        const merged = { ...pageSettings.value, ...(d?.page_settings ?? {}) };
        merged.cover_page = normalizeCoverPage({
            ...pageSettings.value.cover_page,
            ...(d?.page_settings?.cover_page ?? {}),
        });
        if (merged.cover_page.enabled === undefined) {
            merged.cover_page.enabled = false;
        }
        merged.typography = { ...pageSettings.value.typography, ...(d?.page_settings?.typography ?? {}) };
        merged.theme = {
            ...pageSettings.value.theme,
            ...(d?.page_settings?.theme ?? {}),
            colors: {
                ...pageSettings.value.theme?.colors,
                ...(d?.page_settings?.theme?.colors ?? {}),
            },
        };
        ensureThemeColors(merged);
        if (merged.cover_page.date_fixed === undefined || merged.cover_page.date_fixed === null) {
            merged.cover_page.date_fixed = '';
        }
        if (!merged.theme) {
            merged.theme = {};
        }
        if (!merged.theme.section_style) {
            merged.theme.section_style = 'card';
        }
        pageSettings.value = merged;
        const presetsRes = await api<{ data: DesignPreset[] }>('/design/reports/presets');
        designPresets.value = presetsRes.data ?? [];
        if (pageSettings.value.theme?.preset_id) {
            selectedPresetId.value = pageSettings.value.theme.preset_id;
        }
        await loadClientsWithLogo();
        await refreshPreview();
        dirty.value = false;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
        suppressDirty.value = false;
    }
}

async function applyDesignPreset() {
    if (!canWrite.value) {
        return;
    }
    if (presetMode.value === 'full' && !routineForms.value.length) {
        toast.error('Publica al menos un formulario con uso Rutina para aplicar una plantilla completa.');
        return;
    }
    const message =
        presetMode.value === 'theme_only'
            ? 'Se actualizarán colores, tipografía y encabezado/pie. Los bloques del informe se conservan. ¿Continuar?'
            : 'Se reemplazarán los bloques del borrador y la configuración de página con la plantilla. ¿Continuar?';
    const ok = await confirm(message, {
        title: 'Aplicar plantilla de diseño',
        confirmLabel: 'Aplicar',
        danger: presetMode.value === 'full',
    });
    if (!ok) {
        return;
    }
    applyingPreset.value = true;
    try {
        const res = await api<{ data: ReportVersion }>(`/design/reports/${route.params.id}/apply-preset`, {
            method: 'POST',
            body: JSON.stringify({
                preset_id: selectedPresetId.value,
                form_slug: selectedFormSlug.value || null,
                mode: presetMode.value,
            }),
        });
        suppressDirty.value = true;
        if (presetMode.value === 'full') {
            components.value = structuredClone(res.data.components ?? []);
        }
        const merged = { ...pageSettings.value, ...(res.data.page_settings ?? {}) };
        merged.cover_page = { ...pageSettings.value.cover_page, ...(res.data.page_settings?.cover_page ?? {}) };
        merged.typography = { ...pageSettings.value.typography, ...(res.data.page_settings?.typography ?? {}) };
        merged.theme = {
            ...pageSettings.value.theme,
            ...(res.data.page_settings?.theme ?? {}),
            colors: {
                ...pageSettings.value.theme?.colors,
                ...(res.data.page_settings?.theme?.colors ?? {}),
            },
        };
        ensureThemeColors(merged);
        pageSettings.value = merged;
        if (merged.theme?.preset_id) {
            selectedPresetId.value = merged.theme.preset_id;
        }
        await refreshPreview();
        dirty.value = false;
        toast.success(
            presetMode.value === 'theme_only'
                ? 'Tema aplicado (bloques conservados).'
                : 'Plantilla de diseño aplicada al borrador.',
        );
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        applyingPreset.value = false;
        suppressDirty.value = false;
    }
}

function selectDesignPreset(id: string) {
    selectedPresetId.value = id;
}

const routineFormOptions = computed(() =>
    routineForms.value.map((f) => ({ value: f.slug, label: f.name })),
);

const sectionStyleOptions = [
    { value: 'card', label: 'Tarjeta (borde y fondo suave)' },
    { value: 'line', label: 'Línea (separadores)' },
    { value: 'minimal', label: 'Minimal' },
];

const dividerStyleOptions = [
    { value: 'solid', label: 'Continua' },
    { value: 'dashed', label: 'Discontinua' },
    { value: 'dotted', label: 'Punteada' },
];

async function downloadPreviewPdf() {
    downloadingPreview.value = true;
    try {
        const token = getToken();
        const companyId = getCompanyId();
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/pdf',
        };
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        if (companyId) {
            headers['X-Company-Id'] = companyId;
        }
        const res = await fetch(`/api/v1/design/reports/${route.params.id}/preview-pdf`, {
            method: 'POST',
            headers,
            body: JSON.stringify({
                components: components.value ?? [],
                page_settings: pageSettings.value,
            }),
        });
        if (!res.ok) {
            throw new Error('No se pudo generar el PDF de vista previa.');
        }
        const blob = await res.blob();
        const safeName = (templateName.value || 'reporte').replace(/[^\w\s-]/g, '').trim() || 'reporte';
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `vista-previa-${safeName}.pdf`;
        anchor.click();
        URL.revokeObjectURL(url);
        toast.success('PDF de vista previa descargado.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        downloadingPreview.value = false;
    }
}

async function refreshPreview() {
    const token = getToken();
    const companyId = getCompanyId();
    const usePdfPreview = Boolean(pageSettings.value.cover_page?.enabled);
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: usePdfPreview ? 'application/pdf' : 'text/html',
    };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    const endpoint = usePdfPreview
        ? `/api/v1/design/reports/${route.params.id}/preview-pdf`
        : `/api/v1/design/reports/${route.params.id}/preview`;
    const res = await fetch(endpoint, {
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
    const blob = await res.blob();
    if (previewObjectUrl.value) {
        URL.revokeObjectURL(previewObjectUrl.value);
    }
    const objectUrl = URL.createObjectURL(blob);
    previewObjectUrl.value = objectUrl;
    previewUrl.value = usePdfPreview ? `${objectUrl}#toolbar=0&navpanes=0` : objectUrl;
}

function schedulePreview() {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }
    const delay = pageSettings.value.cover_page?.enabled ? 750 : 500;
    previewTimer = setTimeout(() => {
        void refreshPreview();
    }, delay);
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
        components.value.push({
            type: 'paragraph',
            field: first?.key ?? 'technician_comments',
            label: first?.label,
            show_field_key: false,
            align: 'left',
        });
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
    if (!canWrite.value) {
        return;
    }
    await saveMeta();
    await api(`/design/reports/${route.params.id}/components`, {
        method: 'PUT',
        body: JSON.stringify({ components: components.value, page_settings: pageSettings.value }),
    });
    dirty.value = false;
    toast.success('Borrador guardado.');
    await refreshPreview();
}

async function publish() {
    if (!canWrite.value) {
        return;
    }
    await save();
    const res = await api<{
        data: ReportVersion;
        meta?: { routine_types_pending_relink?: { id: number; name: string }[] };
    }>(`/design/reports/${route.params.id}/publish`, { method: 'POST' });
    const pending = res.meta?.routine_types_pending_relink ?? [];
    if (pending.length > 0) {
        toast.warning(
            `Publicado v${res.data.version}. Tipos de rutina aún en versión anterior: ${pending
                .map((t) => t.name)
                .join(', ')}. Re-enlázalos en Tipos de rutina.`,
        );
    } else {
        toast.success(`Publicado v${res.data.version}.`);
    }
    await load();
}

async function deleteTemplate() {
    if (!tpl.value) {
        return;
    }
    if (
        !window.confirm(
            `¿Eliminar la plantilla «${tpl.value.name}»? Se borrarán todas sus versiones. Esta acción no se puede deshacer.`,
        )
    ) {
        return;
    }
    deletingTemplate.value = true;
    try {
        await api(`/design/reports/${route.params.id}`, { method: 'DELETE' });
        toast.success('Plantilla eliminada.');
        await router.push('/app/design/reports');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deletingTemplate.value = false;
    }
}

async function loadClientsWithLogo() {
    try {
        const res = await api<{ data: ClientRow[] }>('/clients');
        clientsWithLogo.value = res.data.filter((c) => Boolean(c.logo_url));
    } catch {
        clientsWithLogo.value = [];
    }
}

function onClientLogoSelected(clientId: string) {
    if (!pageSettings.value.cover_page) {
        return;
    }
    pageSettings.value.cover_page.client_id = clientId ? Number(clientId) : null;
    pageSettings.value.cover_page.logo_source = 'client';
    pageSettings.value.cover_page.use_client_logo = true;
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
        pageSettings.value.cover_page = normalizeCoverPage({
            ...pageSettings.value.cover_page,
            ...json.data.page_settings.cover_page,
        });
    } else if (json.data.image_path) {
        pageSettings.value.cover_page = normalizeCoverPage({
            ...pageSettings.value.cover_page,
            image_path: json.data.image_path,
            logo_source: 'custom',
            use_client_logo: false,
            client_id: null,
        });
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
    if (pageSettings.value.cover_page) {
        pageSettings.value.cover_page.logo_source = 'custom';
        pageSettings.value.cover_page.use_client_logo = false;
        pageSettings.value.cover_page.client_id = null;
    }
    coverImageUploading.value = true;
    try {
        await uploadCoverImage(file);
        if (coverImagePreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(coverImagePreview.value);
        }
        coverImagePreview.value = null;
        toast.info('Imagen de portada guardada en el borrador (no requiere Guardar).');
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
            pageSettings.value.cover_page = normalizeCoverPage({
                ...rest,
                image_path: undefined,
                logo_source: 'none',
            });
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
    () => pageSettings.value.cover_page?.enabled,
    (enabled) => {
        if (enabled && pageSettings.value.cover_page) {
            pageSettings.value.cover_page.omit_header_footer = true;
            if (!pageSettings.value.page_number) {
                pageSettings.value.page_number = { enabled: false, start_at: 2 };
            } else if (!pageSettings.value.page_number.start_at || Number(pageSettings.value.page_number.start_at) < 2) {
                pageSettings.value.page_number.start_at = 2;
            }
        }
    },
);

watch(
    () => [components.value, pageSettings.value],
    () => {
        if (loading.value) {
            return;
        }
        if (!suppressDirty.value) {
            dirty.value = true;
        }
        schedulePreview();
    },
    { deep: true },
);

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (!dirty.value || !canWrite.value) {
        return;
    }
    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    void load();
});
onUnmounted(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    if (previewTimer) {
        clearTimeout(previewTimer);
    }
    if (previewObjectUrl.value) {
        URL.revokeObjectURL(previewObjectUrl.value);
    }
});
</script>

<template>
    <div v-if="loading" class="text-portal-muted">Cargando…</div>
    <div v-else-if="tpl" class="portal-page report-enter-stagger w-full max-w-none space-y-4">
        <div class="report-module-chrome space-y-3">
            <ReportDesignNav />
            <ReadOnlyNotice v-if="!canWrite" module-label="Reportes" />
            <p v-if="dirty && canWrite" class="text-amber-600 text-xs">
                Hay cambios sin guardar en el borrador.
            </p>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <PageHeader class="flex-1" title="Diseñador de reporte" :subtitle="`Plantilla: ${tpl.name}`" />
                <div class="flex shrink-0 items-center gap-2 pt-1">
                    <RouterLink to="/app/design/reports" class="text-portal-muted text-sm underline">Volver al listado</RouterLink>
                    <AppButton
                        v-if="canDeleteTemplate"
                        type="button"
                        variant="danger"
                        :disabled="deletingTemplate"
                        @click="deleteTemplate"
                    >
                        {{ deletingTemplate ? 'Eliminando…' : 'Eliminar plantilla' }}
                    </AppButton>
                </div>
            </div>
            <p class="text-portal-muted text-sm">
                <span v-if="published">En producción: v{{ published.version }}.</span>
                Borrador: v{{ draft?.version }}.
                Los campos de párrafo/imagen deben coincidir con un formulario de uso Rutina publicado.
            </p>
        </div>
        <div
            v-if="misalignedRoutineTypes.length"
            class="portal-callout portal-callout--warning report-enter-item mb-4"
            role="alert"
        >
            <p class="font-medium">Desalineado con tipos de rutina que usan este informe</p>
            <ul class="mt-2 space-y-2 text-xs opacity-90">
                <li v-for="link in misalignedRoutineTypes" :key="link.routine_type_id">
                    <strong>{{ link.routine_type_name }}</strong>
                    usa el formulario «{{ link.form_name ?? link.form_slug }}».
                    Campos del borrador que no existen ahí:
                    {{ link.missing.join(', ') }}.
                    Aplica una plantilla con ese formulario o cambia el formulario enlazado en Tipos de rutina.
                </li>
            </ul>
        </div>
        <div
            v-if="liveOrphanFields.length"
            class="portal-callout portal-callout--warning report-enter-item mb-4"
            role="alert"
        >
            <p class="font-medium">Campos del informe sin coincidencia en formularios de rutina</p>
            <p class="mt-1 text-xs opacity-90">
                {{ liveOrphanFields.join(', ') }}. No podrás publicar hasta corregirlos o publicar el formulario con esas keys.
            </p>
        </div>

        <section
            class="portal-form-panel report-design-presets report-enter-item mb-4 space-y-4"
            aria-labelledby="report-preset-heading"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="report-preset-heading" class="text-portal-heading text-sm font-semibold">
                        Plantillas de diseño profesional
                    </h2>
                    <p class="text-portal-muted mt-1 max-w-2xl text-xs leading-relaxed">
                        Por defecto se aplica solo el tema (colores, tipografía, encabezado/pie) y se conservan tus
                        bloques y textos de portada. Usa «Completa» solo si quieres regenerar el árbol de bloques desde
                        un formulario.
                    </p>
                </div>
                <AppButton
                    variant="primary"
                    :disabled="!canWrite || applyingPreset || (presetMode === 'full' && !routineForms.length)"
                    @click="applyDesignPreset"
                >
                    {{ applyingPreset ? 'Aplicando…' : 'Aplicar al borrador' }}
                </AppButton>
            </div>

            <div class="flex flex-wrap gap-4">
                <label class="text-portal-muted flex cursor-pointer items-center gap-2 text-sm">
                    <input v-model="presetMode" type="radio" value="full" class="size-4" :disabled="!canWrite" />
                    <span class="text-portal-heading">Completa (reemplaza bloques)</span>
                </label>
                <label class="text-portal-muted flex cursor-pointer items-center gap-2 text-sm">
                    <input v-model="presetMode" type="radio" value="theme_only" class="size-4" :disabled="!canWrite" />
                    <span class="text-portal-heading">Solo tema (conserva bloques)</span>
                </label>
            </div>

            <div v-if="!routineForms.length && presetMode === 'full'" class="portal-callout portal-callout--info text-sm" role="status">
                No hay formularios de rutina publicados. Crea y publica un formulario en Diseño → Formularios antes de
                usar las plantillas completas, o elige «Solo tema».
            </div>

            <MaterialSelect
                v-if="routineForms.length && presetMode === 'full'"
                v-model="selectedFormSlug"
                class="max-w-md"
                label="Formulario de rutina (mapeo de campos)"
                :options="routineFormOptions"
                :disabled="!canWrite"
            />

            <MaterialSelect
                v-model="pageSettings.theme!.section_style"
                class="max-w-md"
                label="Estilo de tablas de campos"
                :options="sectionStyleOptions"
                :disabled="!canWrite"
            />

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    v-for="preset in designPresets"
                    :key="preset.id"
                    type="button"
                    class="report-preset-card text-left transition"
                    :class="{ 'report-preset-card--active': selectedPresetId === preset.id }"
                    :disabled="!canWrite"
                    @click="selectDesignPreset(preset.id)"
                >
                    <div class="report-preset-card__swatch" aria-hidden="true">
                        <span :style="{ background: preset.swatch.primary }" />
                        <span :style="{ background: preset.swatch.secondary }" />
                        <span :style="{ background: preset.swatch.accent }" />
                    </div>
                    <p class="text-portal-heading mt-2 text-sm font-medium">{{ preset.label }}</p>
                    <p class="text-portal-muted mt-1 text-xs leading-snug">{{ preset.description }}</p>
                </button>
            </div>

            <div class="border-portal-border/40 grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                <p class="text-portal-heading sm:col-span-2 text-xs font-medium uppercase tracking-wide">
                    Ajuste fino de colores (opcional)
                </p>
                <p class="text-portal-muted sm:col-span-2 text-xs leading-snug">
                    Los colores de la portada se configuran en la sección
                    <strong class="text-portal-heading font-medium">Página de presentación</strong>.
                </p>
                <ColorPickerField v-model="pageSettings.theme!.colors!.primary" label="Primario" />
                <ColorPickerField v-model="pageSettings.theme!.colors!.accent" label="Acento" />
            </div>
        </section>

        <div class="report-enter-item grid gap-6 xl:grid-cols-[1fr_minmax(320px,42%)]">
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
                            <input v-model="pageSettings.cover_page!.enabled" type="checkbox" class="size-4 rounded" :disabled="!canWrite" />
                            <span class="text-portal-heading font-medium">Incluir portada</span>
                        </label>
                    </div>
                    <p v-if="!pageSettings.cover_page?.enabled" class="text-portal-muted text-sm leading-snug">
                        Activa <strong class="text-portal-heading font-medium">Incluir portada</strong> para configurar
                        imagen, título y texto de la primera página del PDF.
                    </p>
                    <template v-if="pageSettings.cover_page?.enabled">
                        <div class="portal-callout portal-callout--info text-xs leading-relaxed" role="note">
                            <p class="text-portal-heading font-medium">Contrato estable de portada</p>
                            <p class="text-portal-muted mt-1">
                                La portada es siempre la hoja 1, sin encabezado ni pie. El cuerpo empieza en la hoja 2.
                                Con portada activa, la vista previa usa el mismo PDF que producción (más fiable).
                            </p>
                        </div>
                        <MaterialSelect
                            v-model="coverLogoSource"
                            class="max-w-md"
                            label="Logo en portada"
                            :options="logoSourceOptions"
                        />
                        <MaterialSelect
                            v-if="coverLogoSource === 'client'"
                            class="max-w-md"
                            :model-value="pageSettings.cover_page?.client_id ? String(pageSettings.cover_page.client_id) : ''"
                            label="Cliente de facturación"
                            :options="[
                                { value: '', label: clientsWithLogo.length ? '— Seleccionar —' : 'Sin logos' },
                                ...clientLogoOptions,
                            ]"
                            @update:model-value="onClientLogoSelected"
                        />
                        <p
                            v-if="coverLogoSource === 'client' && !clientsWithLogo.length"
                            class="text-portal-muted text-xs"
                        >
                            No hay clientes con logo.
                            <RouterLink to="/app/catalog/clients" class="text-portal-link underline">Catálogo de clientes</RouterLink>.
                        </p>
                        <p v-if="coverLogoSource === 'company' && !companyBranding.logo_url" class="text-portal-muted text-xs">
                            La empresa aún no tiene logo. Súbelo en
                            <span class="text-portal-heading">Clientes de plataforma</span> (administrador de sistema) o usa otra opción.
                        </p>
                        <div
                            v-if="coverLogoSource !== 'none' && coverLogoSource !== 'custom' && coverImageDisplayUrl"
                            class="border-portal-border/40 flex min-h-[3.5rem] w-fit items-center justify-center rounded-lg border bg-white/80 px-3 py-2"
                        >
                            <img :src="coverImageDisplayUrl" alt="Vista previa del logo" class="max-h-12 max-w-[10rem] object-contain" />
                        </div>
                        <div
                            v-if="coverLogoSource === 'custom'"
                            class="border-portal-border/50 hover:border-primary-500/40 rounded-xl border-2 border-dashed bg-white/5 p-4 transition-colors"
                        >
                            <p class="text-portal-heading mb-2 text-sm font-medium">Imagen personalizada</p>
                            <p class="text-portal-muted mb-3 text-xs">
                                Se muestra centrada <strong>encima del título</strong> en la portada (JPG, PNG o WebP).
                            </p>
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
                                                  : 'Subir imagen'
                                        }}
                                    </AppButton>
                                    <IconActionButton
                                        v-if="coverImageDisplayUrl"
                                        icon="trash"
                                        label="Quitar imagen de portada"
                                        variant="danger"
                                        :disabled="coverImageUploading"
                                        @click="removeCoverImage"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            v-else-if="coverLogoSource === 'none'"
                            class="text-portal-muted text-xs"
                        >
                            Puedes elegir logo de empresa, de cliente o una imagen propia arriba.
                        </div>
                        <MaterialField v-model="pageSettings.cover_page!.title" label="Título portada" />
                        <MaterialField v-model="pageSettings.cover_page!.subtitle" label="Subtítulo portada" />
                        <RichTextEditor v-model="pageSettings.cover_page!.body" label="Cuerpo portada" />
                        <div class="border-portal-border/40 grid gap-3 rounded-lg border p-3 sm:grid-cols-2">
                            <p class="text-portal-heading sm:col-span-2 text-xs font-medium">Colores de la portada</p>
                            <ColorPickerField
                                v-model="pageSettings.theme!.colors!.cover_bg"
                                label="Fondo"
                                default-hex="#1e3a5f"
                            />
                            <ColorPickerField
                                v-model="pageSettings.theme!.colors!.cover_text"
                                label="Textos (título, subtítulo, cuerpo y fecha)"
                                default-hex="#f8fafc"
                            />
                        </div>
                        <label class="text-portal-muted flex items-center gap-2 text-sm">
                            <input v-model="pageSettings.cover_page!.show_date" type="checkbox" class="size-4 rounded" />
                            Mostrar fecha en portada
                        </label>
                        <template v-if="pageSettings.cover_page!.show_date">
                            <MaterialField
                                v-model="pageSettings.cover_page!.date_fixed"
                                type="date"
                                label="Fecha en portada"
                                placeholder="Automática"
                            />
                            <p class="text-portal-muted -mt-1 text-xs leading-snug">
                                Deja la fecha vacía para usar la del informe al generar el PDF (fecha de envío de la
                                rutina en informes reales; hoy en vista previa).
                            </p>
                        </template>
                        <p class="text-portal-muted text-xs leading-snug">
                            Encabezado y pie se omiten automáticamente en la portada (contrato del motor). Configúralos
                            para el cuerpo en la sección siguiente.
                        </p>
                    </template>
                </div>

                <div class="portal-form-panel space-y-3">
                    <h3 class="text-portal-heading text-sm font-medium">Encabezado y pie</h3>
                    <p class="text-portal-muted text-xs leading-snug">
                        Encabezado, pie y numeración aplican al cuerpo del informe. Con portada, la numeración suele
                        empezar en 2.
                    </p>
                    <label class="text-portal-muted flex items-center gap-2 text-sm">
                        <input v-model="pageSettings.header!.enabled" type="checkbox" :disabled="!canWrite" />
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
                            <div class="table-row-actions">
                                <IconActionButton
                                    icon="chevron-up"
                                    label="Subir componente"
                                    :disabled="!canWrite || i === 0"
                                    @click="moveUp(i)"
                                />
                                <IconActionButton
                                    icon="chevron-down"
                                    label="Bajar componente"
                                    :disabled="!canWrite || i === components.length - 1"
                                    @click="moveDown(i)"
                                />
                                <IconActionButton
                                    icon="trash"
                                    label="Eliminar componente"
                                    variant="danger"
                                    :disabled="!canWrite"
                                    @click="removeComponent(i)"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <MaterialSelect v-model="c.align" label="Alineación" :options="alignOptions" />
                            <ColorPickerField
                                optional
                                label="Color del texto"
                                :model-value="c.color"
                                @update:model-value="(v) => (c.color = v)"
                            />
                            <MaterialField v-model="c.size_pt" label="Tamaño (pt)" type="number" />
                        </div>
                        <MaterialField
                            v-if="c.type === 'title' || c.type === 'subtitle'"
                            v-model="c.text"
                            :label="c.type === 'title' ? 'Título' : 'Subtítulo'"
                        />
                        <RichTextEditor v-else-if="c.type === 'text'" v-model="c.text" label="Texto enriquecido" />
                        <template v-else-if="c.type === 'paragraph' || c.type === 'image'">
                            <MaterialSelect
                                v-model="c.field"
                                :label="c.type === 'image' ? 'Campo foto del formulario' : 'Campo del formulario'"
                                :options="fieldOptionsForComponent(c.type)"
                                :disabled="!canWrite"
                            />
                            <MaterialField
                                v-if="c.type === 'paragraph'"
                                v-model="c.label"
                                label="Etiqueta en el PDF (opcional)"
                                :readonly="!canWrite"
                            />
                            <label
                                v-if="c.type === 'paragraph'"
                                class="text-portal-muted flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="c.show_field_key"
                                    type="checkbox"
                                    class="size-4 rounded"
                                    :disabled="!canWrite"
                                />
                                <span class="text-portal-heading">Mostrar key técnica junto a la etiqueta</span>
                            </label>
                        </template>
                        <div v-else-if="c.type === 'divider'" class="grid gap-2 md:grid-cols-2">
                            <MaterialSelect
                                v-model="c.style"
                                label="Estilo de línea"
                                :options="dividerStyleOptions"
                                :disabled="!canWrite"
                            />
                            <MaterialField
                                v-model="c.margin_pt"
                                label="Margen vertical (pt)"
                                type="number"
                                :readonly="!canWrite"
                            />
                        </div>
                        <MaterialSelect
                            v-else-if="c.type === 'section_template'"
                            :model-value="c.section_template_id ? String(c.section_template_id) : ''"
                            label="Sección reutilizable"
                            :options="[
                                { value: '', label: sectionTemplates.length ? '— Seleccionar —' : 'Sin secciones' },
                                ...sectionTemplateOptions,
                            ]"
                            :disabled="!canWrite"
                            @update:model-value="(v) => (c.section_template_id = v ? Number(v) : null)"
                        />
                        <p
                            v-if="(c.type === 'paragraph' || c.type === 'image') && c.field && liveOrphanFields.includes(c.field)"
                            class="portal-msg-warning text-xs"
                        >
                            Esta key no existe (o no es foto) en un formulario de rutina publicado.
                        </p>
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
                        :disabled="!canWrite"
                        @click="addComponent(t.value)"
                    >
                        + {{ t.label }}
                    </AppButton>
                </div>

                <div class="flex gap-2">
                    <AppButton type="button" variant="secondary" :disabled="!canWrite" @click="save">
                        Guardar
                    </AppButton>
                    <AppButton type="button" :disabled="!canWrite" @click="publish">Publicar</AppButton>
                </div>
            </div>

            <div
                class="portal-form-panel flex max-h-[min(80vh,720px)] flex-col overflow-hidden xl:sticky xl:top-24 xl:z-0 xl:self-start"
            >
                <div class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-portal-heading text-sm font-medium">Vista previa en vivo</p>
                        <p class="text-portal-muted text-xs">
                            {{
                                pageSettings.cover_page?.enabled
                                    ? 'PDF real (mismo motor que producción)'
                                    : 'HTML rápido · descarga PDF para validar tipografía exacta'
                            }}
                        </p>
                    </div>
                    <AppButton
                        type="button"
                        variant="secondary"
                        :disabled="!previewUrl || downloadingPreview"
                        @click="downloadPreviewPdf"
                    >
                        {{ downloadingPreview ? 'Generando…' : 'Descargar PDF' }}
                    </AppButton>
                </div>
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
