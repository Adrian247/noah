<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    VueFlow,
    Handle,
    Position,
    type Connection,
    type Edge,
    type Node,
    MarkerType,
} from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import {
    type WorkflowDefinition,
    definitionToFlow,
    flowToDefinition,
    createEmailStep,
    TRIGGER_OPTIONS,
    triggerLabel,
} from '@/lib/workflowFlowMapper';

const props = defineProps<{
    definition: WorkflowDefinition;
    editable: boolean;
}>();

const emit = defineEmits<{
    'update:definition': [WorkflowDefinition];
}>();

const nodes = ref<Node[]>([]);
const edges = ref<Edge[]>([]);
const selectedNodeId = ref<string | null>(null);
const selectedEdgeId = ref<string | null>(null);

function syncFromDefinition(def: WorkflowDefinition) {
    const flow = definitionToFlow(def);
    nodes.value = flow.nodes as Node[];
    edges.value = flow.edges.map((e) => ({
        ...e,
        markerEnd: MarkerType.ArrowClosed,
        animated: e.data?.trigger === 'service_complete',
    })) as Edge[];
}

watch(
    () => props.definition,
    (def) => syncFromDefinition(def),
    { immediate: true, deep: true },
);

function emitUpdate() {
    const updated = flowToDefinition(
        nodes.value as Parameters<typeof flowToDefinition>[0],
        edges.value.map((e) => ({
            id: e.id,
            source: e.source,
            target: e.target,
            label: typeof e.label === 'string' ? e.label : undefined,
            data: e.data as { trigger: string; actions?: string[] },
        })),
        props.definition,
    );
    emit('update:definition', updated);
}

function onConnect(connection: Connection) {
    if (!props.editable || !connection.source || !connection.target) {
        return;
    }
    const trigger = suggestTrigger(connection.source);
    const id = `e-${connection.source}-${trigger}-${Date.now()}`;
    edges.value = [
        ...edges.value,
        {
            id,
            source: connection.source,
            target: connection.target,
            label: triggerLabel(trigger),
            data: { trigger },
            markerEnd: MarkerType.ArrowClosed,
        },
    ];
    emitUpdate();
}

function onNodeDragStop() {
    if (!props.editable) {
        return;
    }
    emitUpdate();
}

function suggestTrigger(sourceId: string): string {
    const step = props.definition.steps[sourceId];
    if (sourceId === props.definition.initial_step) {
        return 'execution_submitted';
    }
    if (step?.type === 'service_task') {
        return 'service_complete';
    }
    if (step?.assigned_role === 'billing' || sourceId === 'billing_review') {
        return 'invoice_issued';
    }

    return 'approved';
}

const selectedStep = computed(() => {
    if (!selectedNodeId.value) {
        return null;
    }

    return props.definition.steps[selectedNodeId.value] ?? null;
});

const selectedEdgeTrigger = computed(() => {
    if (!selectedEdgeId.value) {
        return null;
    }
    const edge = edges.value.find((e) => e.id === selectedEdgeId.value);

    return (edge?.data as { trigger?: string } | undefined)?.trigger ?? null;
});

function selectNode(id: string) {
    selectedNodeId.value = id;
    selectedEdgeId.value = null;
}

function selectEdge(id: string) {
    selectedEdgeId.value = id;
    selectedNodeId.value = null;
}

function updateSelectedLabel(label: string) {
    if (!selectedNodeId.value || !props.editable) {
        return;
    }
    const def = structuredClone(props.definition);
    if (def.steps[selectedNodeId.value]) {
        def.steps[selectedNodeId.value].label = label;
        emit('update:definition', def);
    }
}

function updateEmailField(field: 'subject' | 'message', value: string) {
    if (!selectedNodeId.value || !props.editable) {
        return;
    }
    const def = structuredClone(props.definition);
    const step = def.steps[selectedNodeId.value];
    if (!step?.email) {
        return;
    }
    step.email[field] = value;
    emit('update:definition', def);
}

function updateEdgeTrigger(trigger: string) {
    if (!selectedEdgeId.value || !props.editable) {
        return;
    }
    const edge = edges.value.find((e) => e.id === selectedEdgeId.value);
    if (!edge) {
        return;
    }
    edge.label = triggerLabel(trigger);
    edge.data = { ...(edge.data as object), trigger };
    edge.animated = trigger === 'service_complete';
    emitUpdate();
}

function addEmailStep() {
    if (!props.editable) {
        return;
    }
    const { id, meta } = createEmailStep();
    const def = structuredClone(props.definition);
    def.steps[id] = meta;
    if (!def.layout.nodes) {
        def.layout = { nodes: {} };
    }
    def.layout.nodes[id] = { x: 240, y: 40 };
    emit('update:definition', def);
    selectedNodeId.value = id;
}

function removeSelectedNode() {
    if (!selectedNodeId.value || !props.editable) {
        return;
    }
    const id = selectedNodeId.value;
    if (id === props.definition.initial_step || id === 'complete') {
        return;
    }
    const def = structuredClone(props.definition);
    delete def.steps[id];
    delete def.layout.nodes[id];
    def.transitions = def.transitions.filter((t) => t.from !== id && t.to !== id);
    emit('update:definition', def);
    selectedNodeId.value = null;
}

function nodeClass(kind: string): string {
    if (kind === 'email') {
        return 'wf-node wf-node--email';
    }
    if (kind === 'end') {
        return 'wf-node wf-node--end';
    }

    return 'wf-node wf-node--human';
}
</script>

<template>
    <div class="workflow-flow-layout">
        <aside v-if="editable" class="workflow-flow-palette portal-form-panel p-3 text-sm">
            <p class="text-portal-heading mb-2 font-medium">Componentes</p>
            <button type="button" class="workflow-palette-btn" @click="addEmailStep">
                + Paso de email
            </button>
            <p class="text-portal-muted mt-3 text-xs leading-relaxed">
                Conecta los puntos entre pasos. Haz clic en una flecha para cambiar el disparador.
            </p>
        </aside>

        <div class="workflow-flow-canvas workflow-canvas">
            <VueFlow
                v-model:nodes="nodes"
                v-model:edges="edges"
                :nodes-draggable="editable"
                :nodes-connectable="editable"
                :elements-selectable="editable"
                :fit-view-on-init="true"
                @connect="onConnect"
                @node-drag-stop="onNodeDragStop"
                @node-click="({ node }) => selectNode(node.id)"
                @edge-click="({ edge }) => selectEdge(edge.id)"
            >
                <Background pattern-color="#94a3b8" :gap="16" />
                <Controls v-if="editable" />
                <template #node-human="{ data }">
                    <Handle type="target" :position="Position.Left" class="wf-handle" />
                    <div :class="nodeClass('human')">
                        <p class="wf-node__id">{{ data.stepId }}</p>
                        <p class="wf-node__title">{{ data.label }}</p>
                        <p class="wf-node__meta">Tarea humana</p>
                    </div>
                    <Handle type="source" :position="Position.Right" class="wf-handle" />
                </template>
                <template #node-email="{ data }">
                    <Handle type="target" :position="Position.Left" class="wf-handle" />
                    <div :class="nodeClass('email')">
                        <p class="wf-node__id">{{ data.stepId }}</p>
                        <p class="wf-node__title">{{ data.label }}</p>
                        <p class="wf-node__meta">Email automático</p>
                    </div>
                    <Handle type="source" :position="Position.Right" class="wf-handle" />
                </template>
                <template #node-end="{ data }">
                    <Handle type="target" :position="Position.Left" class="wf-handle" />
                    <div :class="nodeClass('end')">
                        <p class="wf-node__title">{{ data.label }}</p>
                        <p class="wf-node__meta">Fin</p>
                    </div>
                </template>
            </VueFlow>
        </div>

        <aside class="workflow-flow-inspector portal-form-panel p-3 text-sm">
            <p class="text-portal-heading mb-2 font-medium">Propiedades</p>

            <template v-if="selectedEdgeId && editable">
                <p class="text-portal-muted mb-2 text-xs">Transición seleccionada</p>
                <select
                    class="field-input mb-3 w-full py-1 text-xs"
                    :value="selectedEdgeTrigger ?? 'approved'"
                    @change="updateEdgeTrigger(($event.target as HTMLSelectElement).value)"
                >
                    <option v-for="opt in TRIGGER_OPTIONS" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </template>

            <template v-else-if="selectedStep && selectedNodeId">
                <label class="mb-2 block text-xs text-portal-muted">Etiqueta</label>
                <input
                    :value="selectedStep.label"
                    class="mb-3 w-full rounded border border-portal-border bg-transparent px-2 py-1"
                    :readonly="!editable"
                    @input="updateSelectedLabel(($event.target as HTMLInputElement).value)"
                />
                <template v-if="selectedStep.task === 'send_email' && selectedStep.email">
                    <label class="mb-1 block text-xs text-portal-muted">Asunto</label>
                    <input
                        :value="selectedStep.email.subject"
                        class="mb-2 w-full rounded border border-portal-border bg-transparent px-2 py-1 text-xs"
                        :readonly="!editable"
                        @input="updateEmailField('subject', ($event.target as HTMLInputElement).value)"
                    />
                    <label class="mb-1 block text-xs text-portal-muted">Mensaje</label>
                    <textarea
                        :value="selectedStep.email.message"
                        rows="3"
                        class="mb-2 w-full rounded border border-portal-border bg-transparent px-2 py-1 text-xs"
                        :readonly="!editable"
                        @input="updateEmailField('message', ($event.target as HTMLTextAreaElement).value)"
                    />
                    <p class="text-portal-muted text-xs">Destinatarios: supervisores y administradores.</p>
                </template>
                <button
                    v-if="editable && selectedNodeId !== definition.initial_step && selectedNodeId !== 'complete'"
                    type="button"
                    class="mt-3 text-xs text-red-500 underline"
                    @click="removeSelectedNode"
                >
                    Eliminar paso
                </button>
            </template>
            <p v-else class="text-portal-muted text-xs">Selecciona un nodo o una flecha del diagrama.</p>
        </aside>
    </div>
</template>

<style scoped>
.workflow-flow-layout {
    display: grid;
    grid-template-columns: 11rem 1fr 14rem;
    gap: 0.75rem;
    min-height: 28rem;
}

@media (max-width: 1024px) {
    .workflow-flow-layout {
        grid-template-columns: 1fr;
    }
}

.workflow-flow-canvas {
    min-height: 26rem;
    height: 32rem;
}

.workflow-flow-canvas :deep(.vue-flow) {
    width: 100%;
    height: 100%;
    background: var(--portal-canvas-bg, #f1f5f9);
    border-radius: 0.75rem;
}

.workflow-flow-canvas :deep(.vue-flow__edge-text) {
    font-size: 10px;
}

.workflow-palette-btn {
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid var(--portal-border, #cbd5e1);
    padding: 0.5rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 500;
    transition: background 0.15s;
}

.workflow-palette-btn:hover {
    background: rgb(255 255 255 / 0.08);
}

.wf-handle {
    width: 8px;
    height: 8px;
    background: #64748b;
}

.wf-node {
    min-width: 9rem;
    border-radius: 0.5rem;
    border-width: 1px;
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-size: 0.75rem;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
}

.wf-node--human {
    border-color: #94a3b8;
    background: #fff;
    color: #0f172a;
}

.wf-node--email {
    border-color: #0ea5e9;
    background: #f0f9ff;
    color: #0c4a6e;
}

.wf-node--end {
    border-color: #10b981;
    background: #ecfdf5;
    color: #064e3b;
}

[data-theme='dark'] .wf-node--human {
    border-color: #64748b;
    background: #1e293b;
    color: #f1f5f9;
}

.wf-node__id {
    font-family: ui-monospace, monospace;
    font-size: 10px;
    opacity: 0.6;
}

.wf-node__title {
    font-size: 0.875rem;
    font-weight: 600;
}

.wf-node__meta {
    margin-top: 0.125rem;
    font-size: 10px;
    opacity: 0.7;
}
</style>
