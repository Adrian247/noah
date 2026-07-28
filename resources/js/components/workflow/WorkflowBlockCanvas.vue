<script setup lang="ts">
import { computed, markRaw, nextTick, ref, watch } from 'vue';
import {
    VueFlow,
    useVueFlow,
    type Connection,
    type Edge,
    type Node,
} from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import RichTextEditor from '@/components/ui/RichTextEditor.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';
import type { WorkflowDefinition } from '@/lib/workflowFlowMapper';
import {
    type ActionEmailConfig,
    type ActionEmailRecipientKey,
    type BlockAction,
    type BlockEdge,
    type BlockGraph,
    type BlockNode,
    ROUTINE_ID,
    BILLING_ID,
    END_ID,
    WORKFLOW_ROLE_CATALOG,
    roleCatalogLabel,
    EMAIL_TOKENS,
    graphFromDefinition,
    compileBlockGraph,
    newRoleNode,
    edgeDisplayLabel,
    defaultEdgeForAction,
    defaultNotifyClose,
    defaultBlockGraph,
} from '@/lib/workflowBlockModel';
import WorkflowBlockNode from '@/components/workflow/WorkflowBlockNode.vue';

const FLOW_ID = 'workflow-block-canvas';

const nodeTypes = { block: markRaw(WorkflowBlockNode) };

const { fitView } = useVueFlow({ id: FLOW_ID });

const props = defineProps<{
    definition: WorkflowDefinition;
    editable: boolean;
}>();

const emit = defineEmits<{
    'update:definition': [WorkflowDefinition];
}>();

const graph = ref<BlockGraph>({ nodes: [], edges: [] });
const nodes = ref<Node[]>([]);
const edges = ref<Edge[]>([]);
const selectedNodeId = ref<string | null>(null);
const selectedEdgeId = ref<string | null>(null);
const pendingAction = ref<BlockAction | null>(null);

const recipientOptions: { value: ActionEmailRecipientKey; label: string }[] = [
    { value: 'executing_technician', label: 'Técnico ejecutor' },
    { value: 'incident_creator', label: 'Creador de la incidencia' },
    { value: 'approval_supervisor', label: 'Supervisor que aprobó' },
    { value: 'roles', label: 'Roles del catálogo' },
];

function syncFromDefinition(def: WorkflowDefinition) {
    try {
        graph.value = graphFromDefinition(def);
    } catch {
        graph.value = defaultBlockGraph();
    }
    rebuildFlow();
    void nextTick(() => fitView({ padding: 0.25, duration: 150 }));
}

function onFlowNodesInitialized() {
    void fitView({ padding: 0.25, duration: 150 });
}

function flowEdgeProps(e: BlockEdge): Partial<Edge> {
    const labelProps = {
        labelShowBg: true,
        labelBgStyle: { fill: '#ffffff', fillOpacity: 0.96 },
        labelStyle: { fill: '#0f172a', fontSize: 11, fontWeight: 600 },
    };

    if (e.action === 'reject') {
        return {
            ...labelProps,
            type: 'smoothstep',
            sourceHandle: 'out-reject',
            targetHandle: 'in-reject',
            pathOptions: { borderRadius: 20, offset: 40 },
            style: { stroke: '#b45309', strokeWidth: 2 },
        };
    }

    if (e.action === 'submit' && e.source === ROUTINE_ID) {
        return {
            ...labelProps,
            type: 'smoothstep',
            sourceHandle: 'out-forward',
            targetHandle: 'in-forward',
            pathOptions: { borderRadius: 16, offset: 20 },
            style: { stroke: '#64748b', strokeWidth: 1.5 },
        };
    }

    return {
        ...labelProps,
        type: 'smoothstep',
        sourceHandle: 'out-forward',
        targetHandle: 'in-forward',
        pathOptions: { borderRadius: 12, offset: 8 },
    };
}

function rebuildFlow() {
    nodes.value = graph.value.nodes.map((n) => ({
        id: n.id,
        type: 'block',
        position: { ...n.position },
        draggable: props.editable && !n.locked,
        selectable: true,
        data: { ...n },
    })) as Node[];

    edges.value = graph.value.edges.map((e) => ({
        id: e.id,
        source: e.source,
        target: e.target,
        label: edgeDisplayLabel(e),
        data: { ...e },
        markerEnd: 'arrowclosed',
        animated: e.action === 'reject',
        ...flowEdgeProps(e),
    })) as Edge[];
}

watch(() => props.definition, (d) => syncFromDefinition(d), { immediate: true, deep: true });

function emitCompiled() {
    emit('update:definition', compileBlockGraph(graph.value, props.definition));
}

function onNodeDragStop() {
    if (!props.editable) {
        return;
    }
    for (const vn of nodes.value) {
        const g = graph.value.nodes.find((n) => n.id === vn.id);
        if (g) {
            g.position = { ...vn.position };
        }
    }
    emitCompiled();
}

function inferAction(sourceId: string, targetId: string): BlockAction | null {
    const source = graph.value.nodes.find((n) => n.id === sourceId);
    const target = graph.value.nodes.find((n) => n.id === targetId);
    if (!source || !target) {
        return null;
    }
    if (pendingAction.value) {
        return pendingAction.value;
    }
    if (source.id === ROUTINE_ID) {
        if (target.kind === 'end') {
            return null;
        }
        return 'submit';
    }
    if (source.kind === 'role' && target.id === ROUTINE_ID) {
        return 'reject';
    }
    if (source.kind === 'role' && target.kind === 'end') {
        return source.assigned_role === 'billing' || source.id === BILLING_ID ? 'invoice' : null;
    }
    if (source.kind === 'role') {
        return 'approve';
    }
    return null;
}

function onConnect(connection: Connection) {
    if (!props.editable || !connection.source || !connection.target) {
        return;
    }
    const action = inferAction(connection.source, connection.target);
    if (!action) {
        return;
    }

    if (action === 'submit' && connection.source === ROUTINE_ID) {
        graph.value.edges = graph.value.edges.filter((e) => !(e.source === ROUTINE_ID && e.action === 'submit'));
    }

    const defaults = defaultEdgeForAction(action);
    const id = `e-${connection.source}-${action}-${Date.now()}`;
    graph.value.edges.push({
        id,
        source: connection.source,
        target: connection.target,
        action: defaults.action,
        label: defaults.label,
        routine_validated: defaults.routine_validated,
        notify: defaults.notify,
    });
    pendingAction.value = null;
    rebuildFlow();
    emitCompiled();
}

const selectedNode = computed(() => graph.value.nodes.find((n) => n.id === selectedNodeId.value) ?? null);
const selectedEdge = computed(() => graph.value.edges.find((e) => e.id === selectedEdgeId.value) ?? null);

const roleCatalogOptions = WORKFLOW_ROLE_CATALOG.map((r) => ({ value: r.value, label: r.label }));

const selectedRoleValue = computed({
    get(): string {
        return selectedNode.value?.kind === 'role' ? selectedNode.value.assigned_role ?? 'supervisor' : '';
    },
    set(value: string) {
        updateSelectedRole(value);
    },
});

function updateSelectedRole(role: string) {
    const node = selectedNode.value;
    if (!node || node.kind !== 'role' || !props.editable) {
        return;
    }
    node.assigned_role = role;
    node.label = roleCatalogLabel(role);
    emitCompiled();
}

function selectNode(id: string) {
    selectedNodeId.value = id;
    selectedEdgeId.value = null;
}

function selectEdge(id: string) {
    selectedEdgeId.value = id;
    selectedNodeId.value = null;
}

function addRoleBlock() {
    if (!props.editable) {
        return;
    }
    const node = newRoleNode();
    graph.value.nodes.push(node);
    rebuildFlow();
    emitCompiled();
    selectedNodeId.value = node.id;
}

function ensureEnd() {
    if (graph.value.nodes.some((n) => n.id === END_ID)) {
        return;
    }
    graph.value.nodes.push({
        id: END_ID,
        kind: 'end',
        label: 'Fin',
        position: { x: 800, y: 160 },
    });
}

function ensureNotify(edge: BlockEdge): ActionEmailConfig {
    if (!edge.notify) {
        edge.notify = {
            enabled: false,
            subject: '',
            body_html: '',
            recipients: [],
        };
    }
    return edge.notify;
}

function updateEdgeLabel(value: string) {
    const edge = selectedEdge.value;
    if (!edge || !props.editable) {
        return;
    }
    edge.label = value;
    rebuildFlow();
    emitCompiled();
}

function toggleEdgeNotify() {
    const edge = selectedEdge.value;
    if (!edge || !props.editable) {
        return;
    }
    const notify = ensureNotify(edge);
    notify.enabled = !notify.enabled;
    if (notify.enabled && notify.recipients.length === 0) {
        if (edge.action === 'invoice') {
            Object.assign(notify, defaultNotifyClose());
        } else if (edge.action === 'submit' || edge.action === 'reject') {
            notify.recipients = ['executing_technician'];
        }
    }
    emitCompiled();
}

function updateEdgeNotifyField(field: 'subject' | 'body_html', value: string) {
    const edge = selectedEdge.value;
    if (!edge || !props.editable) {
        return;
    }
    ensureNotify(edge)[field] = value;
    emitCompiled();
}

function toggleRecipient(key: ActionEmailRecipientKey) {
    const edge = selectedEdge.value;
    if (!edge || !props.editable) {
        return;
    }
    const notify = ensureNotify(edge);
    const idx = notify.recipients.indexOf(key);
    if (idx >= 0) {
        notify.recipients.splice(idx, 1);
    } else {
        notify.recipients.push(key);
    }
    emitCompiled();
}

function toggleEdgeRoutineValidated() {
    const edge = selectedEdge.value;
    if (!edge || edge.action !== 'approve') {
        return;
    }
    edge.routine_validated = !edge.routine_validated;
    rebuildFlow();
    emitCompiled();
}

const edgeNotifyBody = computed({
    get(): string {
        return selectedEdge.value?.notify?.body_html ?? '';
    },
    set(v: string) {
        updateEdgeNotifyField('body_html', v);
    },
});

function removeSelectedNode() {
    const id = selectedNodeId.value;
    if (!id || !props.editable) {
        return;
    }
    const node = graph.value.nodes.find((n) => n.id === id);
    if (!node || node.locked) {
        return;
    }
    graph.value.nodes = graph.value.nodes.filter((n) => n.id !== id);
    graph.value.edges = graph.value.edges.filter((e) => e.source !== id && e.target !== id);
    selectedNodeId.value = null;
    rebuildFlow();
    emitCompiled();
}

ensureEnd();
</script>

<template>
    <div
        class="workflow-block-layout"
        :class="editable ? 'workflow-block-layout--editable' : 'workflow-block-layout--readonly'"
    >
        <aside v-if="editable" class="workflow-flow-palette portal-form-panel p-3 text-sm">
            <p class="text-portal-heading mb-2 font-medium">Bloques</p>
            <button type="button" class="workflow-palette-btn mb-2" @click="addRoleBlock">+ Bloque rol</button>
            <p class="text-portal-heading mb-2 mt-3 font-medium">Al conectar</p>
            <button
                type="button"
                class="workflow-palette-btn mb-1"
                :class="{ 'ring-2 ring-sky-400': pendingAction === 'approve' }"
                @click="pendingAction = 'approve'"
            >
                Acción aprobar
            </button>
            <button
                type="button"
                class="workflow-palette-btn mb-1"
                :class="{ 'ring-2 ring-amber-400': pendingAction === 'reject' }"
                @click="pendingAction = 'reject'"
            >
                Acción rechazo
            </button>
            <p class="text-portal-muted mt-3 text-xs leading-relaxed">
                Rutina es el inicio. Las flechas son acciones con nombre y correo opcional. Emisión de factura cierra la
                rutina y envía notificaciones.
            </p>
        </aside>

        <div class="workflow-flow-canvas">
            <p v-if="nodes.length === 0" class="p-4 text-sm text-portal-muted">
                No hay bloques en el grafo. Guarda de nuevo o recarga la página.
            </p>
            <VueFlow
                :id="FLOW_ID"
                v-model:nodes="nodes"
                v-model:edges="edges"
                :node-types="nodeTypes"
                :nodes-connectable="editable"
                :elements-selectable="editable"
                :fit-view-on-init="true"
                class="workflow-block-flow"
                @nodes-initialized="onFlowNodesInitialized"
                @connect="onConnect"
                @node-drag-stop="onNodeDragStop"
                @node-click="({ node }) => selectNode(node.id)"
                @edge-click="({ edge }) => selectEdge(edge.id)"
            >
                <Background pattern-color="#94a3b8" :gap="16" />
                <Controls v-if="editable" />
            </VueFlow>
        </div>

        <aside class="workflow-flow-inspector portal-form-panel overflow-y-auto p-3 text-sm">
            <p class="text-portal-heading mb-2 font-medium">Propiedades</p>

            <template v-if="selectedEdge">
                <label class="text-portal-muted mb-1 block text-xs">Nombre de la acción</label>
                <input
                    class="mb-2 w-full rounded border border-portal-border bg-transparent px-2 py-1 text-xs"
                    :value="selectedEdge.label"
                    :readonly="!editable"
                    @input="updateEdgeLabel(($event.target as HTMLInputElement).value)"
                />
                <label v-if="selectedEdge.action === 'approve' && editable" class="mt-1 flex items-center gap-2 text-xs">
                    <input type="checkbox" :checked="selectedEdge.routine_validated" @change="toggleEdgeRoutineValidated" />
                    Generar PDF y borrador al aprobar
                </label>
                <label class="mt-3 flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        :checked="selectedEdge.notify?.enabled"
                        :disabled="!editable"
                        @change="toggleEdgeNotify"
                    />
                    Notificar por correo
                </label>
                <template v-if="selectedEdge.notify?.enabled">
                    <label class="text-portal-muted mb-1 mt-2 block text-xs">Asunto</label>
                    <input
                        class="mb-2 w-full rounded border border-portal-border bg-transparent px-2 py-1 text-xs"
                        :value="selectedEdge.notify.subject"
                        :readonly="!editable"
                        @input="updateEdgeNotifyField('subject', ($event.target as HTMLInputElement).value)"
                    />
                    <p class="text-portal-muted mb-1 text-xs">{{ EMAIL_TOKENS.map((t) => t.key).join(', ') }}</p>
                    <div v-if="editable" class="mb-2 max-h-40 overflow-y-auto">
                        <RichTextEditor v-model="edgeNotifyBody" />
                    </div>
                    <p class="text-portal-muted mb-1 mt-2 text-xs font-medium">Destinatarios</p>
                    <label
                        v-for="opt in recipientOptions"
                        :key="opt.value"
                        class="mb-1 flex items-center gap-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedEdge.notify?.recipients.includes(opt.value)"
                            :disabled="!editable"
                            @change="toggleRecipient(opt.value)"
                        />
                        {{ opt.label }}
                    </label>
                </template>
            </template>

            <template v-else-if="selectedNode?.kind === 'role'">
                <p class="text-portal-heading font-medium">Bloque rol</p>
                <MaterialSelect
                    v-if="editable"
                    v-model="selectedRoleValue"
                    class="mt-2"
                    label="Rol"
                    :options="roleCatalogOptions"
                />
                <p v-else class="text-portal-muted text-xs">{{ roleCatalogLabel(selectedNode.assigned_role ?? '') }}</p>
                <button
                    v-if="editable"
                    type="button"
                    class="mt-3 text-xs text-red-500 underline"
                    @click="removeSelectedNode"
                >
                    Eliminar bloque
                </button>
            </template>

            <template v-else-if="selectedNode">
                <p class="text-portal-heading font-medium">{{ selectedNode.label }}</p>
                <p class="text-portal-muted text-xs">{{ selectedNode.kind }}</p>
            </template>

            <p v-else class="text-portal-muted text-xs">Selecciona un bloque o una acción (flecha).</p>
        </aside>
    </div>
</template>

<style scoped>
.workflow-block-layout {
    display: grid;
    gap: 0.75rem;
    min-height: 0;
}

.workflow-block-layout--editable {
    grid-template-columns: 10.5rem minmax(0, 1fr) 17rem;
}

.workflow-block-layout--readonly {
    grid-template-columns: minmax(0, 1fr) 17rem;
}

@media (max-width: 1024px) {
    .workflow-block-layout--editable,
    .workflow-block-layout--readonly {
        grid-template-columns: 1fr;
    }
}

.workflow-flow-canvas {
    position: relative;
    min-height: 22rem;
    height: min(40rem, calc(100vh - 12rem));
    overflow: hidden;
    border-radius: 0.75rem;
    border: 1px solid var(--portal-border, rgb(148 163 184 / 0.45));
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.04);
}

.workflow-block-flow {
    width: 100%;
    height: 100%;
}

.workflow-flow-canvas :deep(.vue-flow) {
    width: 100%;
    height: 100%;
    background: var(--portal-canvas-bg, #f1f5f9);
    border-radius: 0.75rem;
}

.workflow-flow-canvas :deep(.vue-flow__edge-textbg) {
    fill: #fff;
    fill-opacity: 0.96;
}

.workflow-flow-canvas :deep(.vue-flow__edge-text) {
    fill: #0f172a;
    font-size: 11px;
    font-weight: 600;
}

.workflow-flow-inspector {
    max-height: min(40rem, calc(100vh - 12rem));
    min-height: 22rem;
}

.workflow-palette-btn {
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid var(--portal-border, #cbd5e1);
    padding: 0.4rem 0.5rem;
    text-align: left;
    font-size: 0.75rem;
}

.wf-handle {
    width: 8px;
    height: 8px;
    background: #64748b;
}

.wf-node {
    min-width: 9rem;
    border-radius: 0.5rem;
    border: 1px solid #94a3b8;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    background: #fff;
}

.wf-node--routine {
    border-color: #6366f1;
    background: #eef2ff;
    min-width: 10.5rem;
    box-shadow: 0 0 0 2px rgb(99 102 241 / 0.35);
}

.wf-node--anchor {
    cursor: default;
}

.wf-node__badge {
    margin-bottom: 0.25rem;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #4338ca;
}

.wf-handle--routine {
    background: #6366f1;
    width: 10px;
    height: 10px;
}

.wf-node--role {
    border-color: #0ea5e9;
}

.wf-node--end {
    border-color: #64748b;
}

.wf-node__title {
    font-weight: 600;
}

.wf-node__meta {
    font-size: 10px;
    opacity: 0.75;
}
</style>
