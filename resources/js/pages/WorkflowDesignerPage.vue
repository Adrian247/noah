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

const edges = computed(() => {
    if (!definition.value) {
        return [];
    }
    return definition.value.transitions.map((t) => {
        const from = definition.value!.layout.nodes[t.from] ?? { x: 0, y: 0 };
        const to = definition.value!.layout.nodes[t.to] ?? { x: 0, y: 0 };
        return {
            ...t,
            x1: from.x + 90,
            y1: from.y + 28,
            x2: to.x + 10,
            y2: to.y + 28,
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
        <p class="text-sm text-slate-600">v{{ workflow.version }} · arrastra nodos; las flechas siguen las transiciones del motor.</p>

        <div
            ref="canvasEl"
            class="relative h-80 w-full max-w-3xl overflow-hidden rounded-lg border border-slate-300 bg-slate-50"
        >
            <svg class="absolute inset-0 h-full w-full pointer-events-none">
                <defs>
                    <marker id="arrow" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto">
                        <path d="M0,0 L6,3 L0,6 Z" fill="#64748b" />
                    </marker>
                </defs>
                <line
                    v-for="(e, i) in edges"
                    :key="i"
                    :x1="e.x1"
                    :y1="e.y1"
                    :x2="e.x2"
                    :y2="e.y2"
                    stroke="#64748b"
                    stroke-width="2"
                    marker-end="url(#arrow)"
                />
            </svg>
            <div
                v-for="node in nodeEntries"
                :key="node.id"
                class="absolute w-36 cursor-move select-none rounded-md border border-slate-300 bg-white px-2 py-2 text-xs shadow-sm"
                :style="{ left: `${node.pos.x}px`, top: `${node.pos.y}px` }"
                @mousedown.prevent="onNodeDown(node.id, $event)"
            >
                <p class="font-mono text-[10px] text-slate-400">{{ node.id }}</p>
                <input
                    v-model="definition.steps[node.id].label"
                    class="mt-1 w-full border-0 p-0 text-sm font-medium focus:ring-0"
                    @mousedown.stop
                />
                <p class="text-slate-500">{{ node.type }}</p>
            </div>
        </div>

        <ul class="max-w-xl text-xs text-slate-600 space-y-1">
            <li v-for="(t, i) in definition.transitions" :key="i">
                {{ t.from }} → {{ t.to }} ({{ t.trigger }})
                <span v-if="t.actions?.length"> · acciones: {{ t.actions.join(', ') }}</span>
            </li>
        </ul>

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
