<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';

type StepMeta = { type: string; label: string };
type Transition = { from: string; to: string; trigger: string; actions?: string[] };
type LayoutNodes = Record<string, { x: number; y: number }>;

type Definition = {
    initial_step: string;
    steps: Record<string, StepMeta>;
    transitions: Transition[];
    layout: { nodes: LayoutNodes };
};

type Workflow = {
    id: number;
    name: string;
    slug: string;
    version: number;
    definition: Definition;
};

const route = useRoute();
const workflow = ref<Workflow | null>(null);
const definition = ref<Definition | null>(null);
const message = ref<string | null>(null);
const saving = ref(false);
const dragging = ref<string | null>(null);
const dragOffset = ref({ x: 0, y: 0 });
const canvasEl = ref<HTMLElement | null>(null);

const nodeEntries = computed(() => {
    if (!definition.value) {
        return [];
    }
    return Object.entries(definition.value.steps).map(([id, meta]) => ({
        id,
        ...meta,
        pos: definition.value!.layout.nodes[id] ?? { x: 0, y: 0 },
    }));
});

const TRIGGER_LABELS: Record<string, string> = {
    execution_submitted: 'Técnico envía ejecución',
    approved: 'Supervisor valida',
    rejected: 'Supervisor rechaza',
};

const NODE_TYPE_LABELS: Record<string, string> = {
    human_task: 'Tarea humana',
    end: 'Fin del flujo',
    service_task: 'Automático',
};

const NODE_W = 144;
const NODE_H = 56;

type EdgeDraw = {
    key: string;
    kind: 'line' | 'curve';
    label: string;
    x1?: number;
    y1?: number;
    x2?: number;
    y2?: number;
    path?: string;
    labelX: number;
    labelY: number;
};

const edges = computed((): EdgeDraw[] => {
    if (!definition.value) {
        return [];
    }
    const transitions = definition.value.transitions;
    const hasRejectLoop = transitions.some(
        (t) => t.trigger === 'rejected' && t.from === 'supervisor_review' && t.to === 'field_execution',
    );

    return transitions.map((t, index) => {
        const from = definition.value!.layout.nodes[t.from] ?? { x: 0, y: 0 };
        const to = definition.value!.layout.nodes[t.to] ?? { x: 0, y: 0 };
        const label = TRIGGER_LABELS[t.trigger] ?? t.trigger;

        if (t.trigger === 'rejected') {
            const x1 = from.x + 24;
            const y1 = from.y + NODE_H - 6;
            const x2 = to.x + NODE_W - 24;
            const y2 = to.y + NODE_H - 6;
            const midX = (x1 + x2) / 2;
            const controlY = Math.max(y1, y2) + 52;
            return {
                key: `edge-${index}`,
                kind: 'curve',
                label,
                path: `M ${x1} ${y1} Q ${midX} ${controlY} ${x2} ${y2}`,
                labelX: midX,
                labelY: controlY + 14,
            };
        }

        const x1 = from.x + NODE_W;
        const y1 = from.y + NODE_H / 2;
        const x2 = to.x;
        const y2 = to.y + NODE_H / 2;
        const midX = (x1 + x2) / 2;
        const labelOffset =
            hasRejectLoop && t.from === 'field_execution' && t.to === 'supervisor_review' ? -18 : -10;

        return {
            key: `edge-${index}`,
            kind: 'line',
            label,
            x1,
            y1,
            x2,
            y2,
            labelX: midX,
            labelY: (y1 + y2) / 2 + labelOffset,
        };
    });
});

async function load() {
    const res = await api<{ data: Workflow }>(`/design/workflows/${route.params.id}`);
    workflow.value = res.data;
    definition.value = structuredClone(res.data.definition);
}

function onNodeDown(id: string, event: MouseEvent) {
    if (!definition.value || !canvasEl.value) {
        return;
    }
    dragging.value = id;
    const pos = definition.value.layout.nodes[id];
    const rect = canvasEl.value.getBoundingClientRect();
    dragOffset.value = {
        x: event.clientX - rect.left - pos.x,
        y: event.clientY - rect.top - pos.y,
    };
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function onMove(event: MouseEvent) {
    if (!dragging.value || !definition.value || !canvasEl.value) {
        return;
    }
    const rect = canvasEl.value.getBoundingClientRect();
    definition.value.layout.nodes[dragging.value] = {
        x: Math.max(0, Math.min(rect.width - 144, event.clientX - rect.left - dragOffset.value.x)),
        y: Math.max(0, Math.min(rect.height - 60, event.clientY - rect.top - dragOffset.value.y)),
    };
}

function onUp() {
    dragging.value = null;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
}

async function save() {
    if (!definition.value) {
        return;
    }
    saving.value = true;
    message.value = null;
    try {
        await api(`/design/workflows/${route.params.id}/definition`, {
            method: 'PUT',
            body: JSON.stringify({ definition: definition.value }),
        });
        message.value = 'Workflow guardado.';
        await load();
    } catch (e) {
        message.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="workflow && definition" class="space-y-4">
        <h2 class="text-xl font-semibold">{{ workflow.name }}</h2>
        <div class="max-w-3xl space-y-2 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700">
            <p class="font-medium">Cómo leer este diagrama</p>
            <ul class="list-inside list-disc space-y-1 text-slate-600">
                <li>
                    <strong>Las cajas</strong> son pasos del flujo. Puedes <strong>moverlas</strong> y cambiar el
                    <strong>título</strong> (solo afecta la vista; el motor usa el identificador interno).
                </li>
                <li>
                    <strong>Las flechas no se arrastran ni se crean aquí</strong> (MVP): definen qué ocurre en la app
                    cuando el técnico envía, el supervisor valida o rechaza.
                </li>
                <li>
                    La <strong>acción real</strong> la ves en <em>Rutinas</em>: historial de transiciones en el detalle
                    y estados (pendiente validación, validada, PDF, factura).
                </li>
            </ul>
        </div>

        <div
            ref="canvasEl"
            class="relative h-96 w-full max-w-3xl overflow-visible rounded-lg border border-slate-300 bg-slate-50"
        >
            <svg class="absolute inset-0 h-full w-full overflow-visible pointer-events-none">
                <defs>
                    <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                        <path d="M0,0 L6,3 L0,6 Z" fill="#64748b" />
                    </marker>
                    <marker id="arrow-reject" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                        <path d="M0,0 L6,3 L0,6 Z" fill="#b45309" />
                    </marker>
                </defs>
                <template v-for="e in edges" :key="e.key">
                    <line
                        v-if="e.kind === 'line'"
                        :x1="e.x1"
                        :y1="e.y1"
                        :x2="e.x2"
                        :y2="e.y2"
                        stroke="#64748b"
                        stroke-width="2"
                        marker-end="url(#arrow)"
                    />
                    <path
                        v-else
                        :d="e.path"
                        fill="none"
                        stroke="#b45309"
                        stroke-width="2"
                        stroke-dasharray="6 4"
                        marker-end="url(#arrow-reject)"
                    />
                    <rect
                        :x="e.labelX - 72"
                        :y="e.labelY - 11"
                        width="144"
                        height="14"
                        rx="3"
                        class="fill-slate-50 opacity-95"
                    />
                    <text
                        :x="e.labelX"
                        :y="e.labelY"
                        text-anchor="middle"
                        class="fill-slate-700 text-[10px] font-medium"
                    >
                        {{ e.label }}
                    </text>
                </template>
            </svg>
            <div
                v-for="node in nodeEntries"
                :key="node.id"
                class="absolute w-36 cursor-move select-none rounded-md border px-2 py-2 text-xs shadow-sm"
                :class="
                    node.type === 'end'
                        ? 'border-emerald-400 bg-emerald-50'
                        : 'border-slate-300 bg-white'
                "
                :style="{ left: `${node.pos.x}px`, top: `${node.pos.y}px` }"
                @mousedown.prevent="onNodeDown(node.id, $event)"
            >
                <p class="font-mono text-[10px] text-slate-400">{{ node.id }}</p>
                <input
                    v-model="definition.steps[node.id].label"
                    class="mt-1 w-full border-0 p-0 text-sm font-medium focus:ring-0"
                    @mousedown.stop
                />
                <p class="text-slate-500">{{ NODE_TYPE_LABELS[node.type] ?? node.type }}</p>
            </div>
        </div>

        <div class="max-w-xl">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Reglas del motor (solo lectura)</p>
            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                <li
                    v-for="(t, i) in definition.transitions"
                    :key="i"
                    class="rounded border border-slate-100 bg-slate-50 px-3 py-2"
                >
                    <span class="font-medium">{{ TRIGGER_LABELS[t.trigger] ?? t.trigger }}</span>
                    <span class="text-slate-500">
                        — de «{{ definition.steps[t.from]?.label ?? t.from }}» a
                        «{{ definition.steps[t.to]?.label ?? t.to }}»
                    </span>
                    <span v-if="t.actions?.length" class="block text-xs text-slate-500 mt-1">
                        Al validar: {{ t.actions.includes('routine_validated') ? 'genera PDF (cola) y borrador de factura' : t.actions.join(', ') }}
                    </span>
                </li>
            </ul>
        </div>

        <button
            type="button"
            class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
            :disabled="saving"
            @click="save"
        >
            Guardar diseño
        </button>
        <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    </div>
</template>
