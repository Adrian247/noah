<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api, getToken, getCompanyId } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';
import ReportDesignNav from '@/components/reports/ReportDesignNav.vue';

type ReportRow = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    published_version?: { version: number; status: string } | null;
    draft_version?: { version: number; status: string } | null;
};

const { canWriteModule, state } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_reports'));
const canReadSettings = computed(() => state('design_reports').read);

const reports = ref<ReportRow[]>([]);
const loading = ref(true);
const name = ref('');
const showCreate = ref(false);
const previewUrls = ref<Record<number, string>>({});

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: ReportRow[] }>('/design/reports');
        reports.value = res.data;
        for (const r of res.data) {
            void loadPreviewThumb(r.id);
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function loadPreviewThumb(id: number) {
    const token = getToken();
    const companyId = getCompanyId();
    const headers: Record<string, string> = {};
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    if (companyId) {
        headers['X-Company-Id'] = companyId;
    }
    try {
        const res = await fetch(`/api/v1/design/reports/${id}/preview?thumbnail=1`, { headers });
        const html = await res.text();
        previewUrls.value[id] = URL.createObjectURL(new Blob([html], { type: 'text/html' }));
    } catch {
        /* ignore thumb errors */
    }
}

async function createReport() {
    if (!name.value.trim()) {
        toast.warning('Indica el nombre del reporte.');
        return;
    }
    try {
        await api('/design/reports', {
            method: 'POST',
            body: JSON.stringify({ name: name.value.trim() }),
        });
        name.value = '';
        showCreate.value = false;
        toast.success('Reporte creado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function openCreate() {
    name.value = '';
    showCreate.value = !showCreate.value;
}

onMounted(load);
</script>

<template>
    <div class="portal-page space-y-4">
        <ReportDesignNav />
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Plantillas de reporte"
                subtitle="Publicar deja una versión en producción y abre un borrador nuevo. El enlace al tipo de rutina se hace en Tipos de rutina."
            />
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <AppButton v-if="canWrite" type="button" @click="openCreate">
                    Nueva plantilla
                </AppButton>
                <RouterLink v-if="canReadSettings" to="/app/design/reports/settings">
                    <AppButton type="button" variant="secondary">Secciones reutilizables</AppButton>
                </RouterLink>
            </div>
        </div>
        <form
            v-if="showCreate && canWrite"
            class="portal-form-panel flex max-w-xl flex-wrap items-end gap-4"
            @submit.prevent="createReport"
        >
            <MaterialField v-model="name" label="Nombre de plantilla" class="min-w-[14rem] flex-1" required />
            <AppButton type="submit">Crear</AppButton>
            <button type="button" class="text-portal-muted text-sm underline" @click="showCreate = false">
                Cancelar
            </button>
        </form>
        <ReadOnlyNotice v-if="!canWrite" module-label="Reportes" />
        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <div
            v-else-if="!reports.length"
            class="portal-form-panel max-w-xl space-y-2 text-sm"
        >
            <p class="text-portal-heading font-medium">Aún no hay plantillas de reporte</p>
            <p class="text-portal-muted">
                Crea una plantilla con <strong class="text-portal-heading">Nueva plantilla</strong> o ejecuta el seed demo
                (<code class="text-xs">php artisan db:seed --class=NoahDemoSeeder</code>) para cargar el informe de revisión
                mayor vehículo.
            </p>
        </div>
        <div v-else class="report-card-grid">
            <RouterLink
                v-for="r in reports"
                :key="r.id"
                :to="`/app/design/reports/${r.id}`"
                class="report-card group"
            >
                <div class="report-card-preview overflow-hidden">
                    <iframe
                        v-if="previewUrls[r.id]"
                        :src="previewUrls[r.id]"
                        class="report-card-iframe"
                        scrolling="no"
                        tabindex="-1"
                        title=""
                    />
                    <div v-else class="report-card-preview-placeholder text-portal-muted text-xs">Vista previa…</div>
                </div>
                <div class="report-card-body">
                    <p class="text-portal-heading font-medium">{{ r.name }}</p>
                    <p v-if="r.published_version" class="text-portal-muted text-xs">
                        En uso: v{{ r.published_version.version }} publicada
                    </p>
                    <p v-else class="text-xs text-amber-500">Sin versión publicada</p>
                    <p v-if="r.draft_version" class="text-portal-muted text-xs">Borrador: v{{ r.draft_version.version }}</p>
                </div>
                <div v-if="r.description" class="report-card-overlay">
                    <p class="text-sm leading-snug">{{ r.description }}</p>
                </div>
            </RouterLink>
        </div>
    </div>
</template>
