<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import WorkflowBlockCanvas from '@/components/workflow/WorkflowBlockCanvas.vue';
import type { WorkflowDefinition } from '@/lib/workflowFlowMapper';
import { compileBlockGraph, graphFromDefinition, cloneJson } from '@/lib/workflowBlockModel';

type Workflow = {
    id: number;
    name: string;
    slug: string;
    version: number;
    status: string;
    routine_types_count?: number;
    definition: WorkflowDefinition;
};

const route = useRoute();
const router = useRouter();
const { canWriteModule } = useModuleAccess();
const canWrite = computed(() => canWriteModule('design_workflows'));

const workflow = ref<Workflow | null>(null);
const definition = ref<WorkflowDefinition | null>(null);
const toast = useToast();
const saving = ref(false);
const publishing = ref(false);
const deleting = ref(false);

const isDraft = computed(() => workflow.value?.status === 'draft');
const canEditDefinition = computed(() => canWrite.value && isDraft.value);

async function load() {
    try {
        const res = await api<{ data: Workflow }>(`/design/workflows/${route.params.id}`);
        workflow.value = res.data;
        let def = cloneJson(res.data.definition);
        if (!def.layout?.nodes) {
            def.layout = { nodes: {} };
        }
        const graph = graphFromDefinition(def);
        definition.value = compileBlockGraph(graph, def);
    } catch (e) {
        toast.error((e as Error).message || 'No se pudo cargar el workflow.');
        definition.value = null;
    }
}

async function save() {
    if (!definition.value || !canEditDefinition.value) {
        return;
    }
    saving.value = true;
    try {
        const compiled = compileBlockGraph(
            graphFromDefinition(definition.value),
            definition.value,
        );
        await api(`/design/workflows/${route.params.id}/definition`, {
            method: 'PUT',
            body: JSON.stringify({ definition: compiled }),
        });
        toast.success('Workflow guardado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

function onDefinitionUpdate(def: WorkflowDefinition) {
    definition.value = def;
}

async function publishWorkflow() {
    if (!canWrite.value || !isDraft.value) {
        return;
    }
    publishing.value = true;
    try {
        if (canEditDefinition.value && definition.value) {
            await save();
        }
        await api(`/design/workflows/${route.params.id}/publish`, { method: 'POST' });
        toast.success('Workflow publicado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        publishing.value = false;
    }
}

async function deleteWorkflow() {
    if (!workflow.value || !canWrite.value) {
        return;
    }
    const count = workflow.value.routine_types_count ?? 0;
    if (count > 0) {
        toast.warning('Este workflow está asignado a tipos de rutina. Quita la asignación antes de eliminarlo.');
        return;
    }
    if (!window.confirm(`¿Eliminar el workflow «${workflow.value.name}»? Esta acción no se puede deshacer.`)) {
        return;
    }
    deleting.value = true;
    try {
        await api(`/design/workflows/${workflow.value.id}`, { method: 'DELETE' });
        toast.success('Workflow eliminado.');
        await router.push('/app/design/workflows');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deleting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="workflow && definition" class="portal-page w-full max-w-none space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                :title="workflow.name"
                :subtitle="`${workflow.slug} · v${workflow.version} · ${workflow.status === 'published' ? 'Publicado' : 'Borrador'}`"
            />
            <div class="flex flex-wrap gap-2">
                <AppButton v-if="canEditDefinition" type="button" :disabled="saving" @click="save">
                    Guardar diseño
                </AppButton>
                <AppButton
                    v-if="canWrite && isDraft"
                    type="button"
                    variant="primary"
                    :disabled="publishing"
                    @click="publishWorkflow"
                >
                    Publicar workflow
                </AppButton>
                <IconActionButton
                    v-if="canWrite"
                    icon="trash"
                    label="Eliminar workflow"
                    variant="danger"
                    :disabled="deleting || saving || publishing"
                    @click="deleteWorkflow"
                />
            </div>
        </div>

        <p
            v-if="!isDraft"
            class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-portal-heading"
        >
            Este workflow está publicado. Duplícalo para editar el grafo.
        </p>

        <ReadOnlyNotice v-if="!canWrite" module-label="Workflows" />

        <p v-if="isDraft" class="text-sm text-portal-muted">
            Rutina fija al inicio; las flechas son acciones con nombre y correo opcional.
        </p>

        <div class="workflow-designer-surface portal-form-panel w-full max-w-none p-3 sm:p-4">
            <WorkflowBlockCanvas
                :key="`${workflow.id}-${definition.meta?.block_editor_version ?? 1}`"
                :definition="definition"
                :editable="canEditDefinition"
                @update:definition="onDefinitionUpdate"
            />
        </div>
    </div>
</template>
