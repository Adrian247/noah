<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import ReadOnlyNotice from '@/components/ui/ReadOnlyNotice.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import IconActionButton from '@/components/ui/IconActionButton.vue';

type Workflow = {
    id: number;
    name: string;
    slug: string;
    version: number;
    status: string;
    template?: string | null;
    routine_types_count: number;
};

type WorkflowTemplate = {
    key: string;
    label: string;
    description: string;
    default_options: Record<string, boolean>;
};

const { canWriteModule } = useModuleAccess();
const toast = useToast();
const confirm = useConfirm();
const canWrite = computed(() => canWriteModule('design_workflows'));

const items = ref<Workflow[]>([]);
const templates = ref<WorkflowTemplate[]>([]);
const loading = ref(true);
const showCreate = ref(false);
const name = ref('');
const selectedTemplate = ref('standard_billing');
const creating = ref(false);
const duplicatingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Workflow[] }>('/design/workflows');
        items.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function loadTemplates() {
    try {
        const res = await api<{ data: WorkflowTemplate[] }>('/design/workflows/templates');
        templates.value = res.data;
    } catch {
        templates.value = [];
    }
}

function openCreate() {
    name.value = '';
    selectedTemplate.value = 'standard_billing';
    showCreate.value = true;
}

async function createWorkflow() {
    if (!name.value.trim()) {
        toast.warning('Indica el nombre del workflow.');
        return;
    }
    creating.value = true;
    try {
        await api('/design/workflows', {
            method: 'POST',
            body: JSON.stringify({
                name: name.value.trim(),
                template: selectedTemplate.value,
            }),
        });
        toast.success('Workflow creado en borrador. Ábrelo para configurar y publicar.');
        showCreate.value = false;
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        creating.value = false;
    }
}

function statusBadgeKey(status: string): string {
    return status === 'published' ? 'published' : 'draft';
}

async function duplicateWorkflow(w: Workflow) {
    duplicatingId.value = w.id;
    try {
        await api(`/design/workflows/${w.id}/duplicate`, { method: 'POST' });
        toast.success('Workflow duplicado (borrador).');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        duplicatingId.value = null;
    }
}

async function deleteWorkflow(w: Workflow) {
    const blocked =
        w.routine_types_count > 0
            ? 'Este workflow está asignado a tipos de servicio. Quita la asignación antes de eliminarlo.'
            : null;
    if (blocked) {
        toast.warning(blocked);
        return;
    }
    const accepted = await confirm(
        `¿Eliminar el workflow «${w.name}»? Esta acción no se puede deshacer.`,
        { title: 'Eliminar workflow', confirmLabel: 'Eliminar', danger: true },
    );
    if (!accepted) {
        return;
    }
    deletingId.value = w.id;
    try {
        await api(`/design/workflows/${w.id}`, { method: 'DELETE' });
        toast.success('Workflow eliminado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        deletingId.value = null;
    }
}

onMounted(async () => {
    await Promise.all([load(), loadTemplates()]);
});
</script>

<template>
    <div class="portal-page" data-tour="page-design-workflows">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <PageHeader
                class="flex-1"
                title="Workflows"
                subtitle="Elige una plantilla al crear, configura pasos y publica antes de asignar a tipos de servicio."
            />
            <AppButton v-if="canWrite" type="button" @click="openCreate">Nuevo workflow</AppButton>
        </div>

        <ReadOnlyNotice v-if="!canWrite" module-label="Workflows" />

        <p v-if="loading" class="text-portal-muted">Cargando…</p>
        <ul v-else class="portal-list-panel divide-y">
            <li
                v-for="w in items"
                :key="w.id"
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
            >
                <div class="min-w-0 flex-1">
                    <RouterLink class="text-portal-heading font-medium hover:text-amber-600" :to="`/app/design/workflows/${w.id}`">
                        {{ w.name }}
                    </RouterLink>
                    <p class="text-portal-muted text-xs">{{ w.slug }} · v{{ w.version }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="statusBadgeKey(w.status)" />
                    <span class="text-portal-muted text-xs">
                        {{ w.routine_types_count === 1 ? '1 tipo de servicio' : `${w.routine_types_count} tipos de servicio` }}
                    </span>
                    <div v-if="canWrite" class="table-row-actions">
                        <IconActionButton
                            icon="copy"
                            label="Duplicar workflow"
                            :disabled="duplicatingId === w.id || deletingId === w.id"
                            @click="duplicateWorkflow(w)"
                        />
                        <IconActionButton
                            icon="trash"
                            label="Eliminar workflow"
                            variant="danger"
                            :disabled="duplicatingId === w.id || deletingId === w.id"
                            @click="deleteWorkflow(w)"
                        />
                    </div>
                </div>
            </li>
        </ul>

        <AppModal :open="showCreate" title="Nuevo workflow" size="md" @close="showCreate = false">
            <form class="space-y-4" @submit.prevent="createWorkflow">
                <MaterialField
                    v-model="name"
                    label="Nombre"
                    class="w-full"
                    required
                    :disabled="creating"
                />
                <fieldset class="space-y-2">
                    <legend class="text-portal-heading text-sm font-medium">Plantilla inicial</legend>
                    <label
                        v-for="t in templates"
                        :key="t.key"
                        class="flex cursor-pointer gap-3 rounded-lg border border-portal-border p-3 text-sm"
                    >
                        <input
                            v-model="selectedTemplate"
                            type="radio"
                            class="mt-1"
                            :value="t.key"
                            :disabled="creating"
                        />
                        <span>
                            <span class="text-portal-heading font-medium">{{ t.label }}</span>
                            <span class="text-portal-muted mt-0.5 block text-xs">{{ t.description }}</span>
                        </span>
                    </label>
                </fieldset>
                <p class="text-portal-muted text-xs">
                    Se crea en <strong>borrador</strong>. Podrás ajustar opciones, transiciones y diseño antes de publicar.
                </p>
                <div class="flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="showCreate = false">Cancelar</AppButton>
                    <AppButton type="submit" :disabled="creating">Crear borrador</AppButton>
                </div>
            </form>
        </AppModal>
    </div>
</template>
