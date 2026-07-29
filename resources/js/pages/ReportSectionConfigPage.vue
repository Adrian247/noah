<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import RichTextEditor from '@/components/ui/RichTextEditor.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ReportDesignNav from '@/components/reports/ReportDesignNav.vue';
import { RouterLink } from 'vue-router';

type SectionRow = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    body: string;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const canWrite = computed(() => canWriteModule('design_reports'));

const sections = ref<SectionRow[]>([]);
const loading = ref(true);
const savingId = ref<number | null>(null);

const newName = ref('');
const newDescription = ref('');
const newBody = ref('');

const editingId = ref<number | null>(null);
const editName = ref('');
const editDescription = ref('');
const editBody = ref('');

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: SectionRow[] }>('/design/reports/section-templates');
        sections.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function startEdit(row: SectionRow) {
    editingId.value = row.id;
    editName.value = row.name;
    editDescription.value = row.description ?? '';
    editBody.value = row.body;
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit() {
    if (editingId.value === null || !editName.value.trim() || !editBody.value.trim()) {
        return;
    }
    savingId.value = editingId.value;
    try {
        await api(`/design/reports/section-templates/${editingId.value}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: editName.value.trim(),
                description: editDescription.value.trim() || null,
                body: editBody.value,
            }),
        });
        await load();
        cancelEdit();
        toast.success('Sección actualizada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingId.value = null;
    }
}

async function createSection() {
    if (!newName.value.trim() || !newBody.value.trim()) {
        toast.warning('Indica nombre y contenido de la sección.');
        return;
    }
    try {
        await api('/design/reports/section-templates', {
            method: 'POST',
            body: JSON.stringify({
                name: newName.value.trim(),
                description: newDescription.value.trim() || null,
                body: newBody.value,
            }),
        });
        newName.value = '';
        newDescription.value = '';
        newBody.value = '';
        await load();
        toast.success('Sección creada.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function removeSection(id: number) {
    if (!window.confirm('¿Eliminar esta sección? Los reportes que la referencien mostrarán un aviso.')) {
        return;
    }
    await api(`/design/reports/section-templates/${id}`, { method: 'DELETE' });
    if (editingId.value === id) {
        cancelEdit();
    }
    await load();
}

onMounted(load);
</script>

<template>
    <div class="portal-page report-enter-stagger space-y-4">
        <div class="report-module-chrome space-y-4">
            <ReportDesignNav />
            <PageHeader
                title="Secciones de reporte"
                subtitle="Bloques de texto enriquecido (alcance, garantía, procedimientos) para insertar en cualquier plantilla."
            />
            <RouterLink to="/app/design/reports">
                <AppButton type="button" variant="secondary">← Ver plantillas de reporte</AppButton>
            </RouterLink>
        </div>

        <p v-if="loading" class="text-portal-muted report-enter-item">Cargando…</p>

        <template v-else>
            <section class="report-enter-item space-y-4">
                <h3 class="text-portal-heading font-medium">Secciones</h3>
                <p class="text-portal-muted max-w-3xl text-sm">
                    Define bloques como alcance del servicio, garantías o procedimientos. En el diseñador de cada plantilla
                    agrégalos con el componente <strong class="text-portal-heading">Sección (plantilla)</strong>.
                </p>

                <div v-if="!sections.length" class="portal-form-panel text-portal-muted text-sm">
                    No hay secciones todavía.
                    <span v-if="canWrite">Crea la primera abajo o ejecuta el seed demo para las secciones premium.</span>
                </div>

                <div v-for="s in sections" :key="s.id" class="portal-form-panel space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-portal-heading font-medium">{{ s.name }}</p>
                            <p class="text-portal-muted font-mono text-xs">{{ s.slug }}</p>
                            <p v-if="s.description" class="text-portal-muted mt-1 text-sm">{{ s.description }}</p>
                        </div>
                        <div v-if="canWrite" class="table-row-actions">
                            <IconActionButton
                                v-if="editingId !== s.id"
                                icon="pencil"
                                label="Editar sección"
                                @click="startEdit(s)"
                            />
                            <IconActionButton
                                icon="trash"
                                label="Eliminar sección"
                                variant="danger"
                                @click="removeSection(s.id)"
                            />
                        </div>
                    </div>

                    <div v-if="editingId === s.id" class="space-y-3 border-t border-white/10 pt-3">
                        <MaterialField v-model="editName" label="Nombre" />
                        <MaterialField v-model="editDescription" label="Descripción interna" multiline :rows="2" />
                        <RichTextEditor v-model="editBody" label="Contenido" />
                        <div class="flex gap-2">
                            <AppButton type="button" :disabled="savingId === s.id" @click="saveEdit">
                                {{ savingId === s.id ? 'Guardando…' : 'Guardar' }}
                            </AppButton>
                            <button type="button" class="text-portal-muted text-sm underline" @click="cancelEdit">Cancelar</button>
                        </div>
                    </div>
                </div>

                <form v-if="canWrite" class="portal-form-panel max-w-3xl space-y-4" @submit.prevent="createSection">
                    <h4 class="text-portal-heading text-sm font-medium">Nueva sección</h4>
                    <MaterialField v-model="newName" label="Nombre" required />
                    <MaterialField v-model="newDescription" label="Descripción interna" multiline :rows="2" />
                    <RichTextEditor v-model="newBody" label="Contenido" />
                    <AppButton type="submit">Crear sección</AppButton>
                </form>
            </section>
        </template>

    </div>
</template>
