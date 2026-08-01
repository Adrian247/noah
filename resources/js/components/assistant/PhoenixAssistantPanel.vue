<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

type AssistantSource = { type: string; id: number; label: string };
type ToolCallMeta = { name: string; ok: boolean };
type DashboardChart = {
    type: string;
    title?: string;
    value?: number | string;
    unit?: string;
    hero?: boolean;
    metrics?: Array<{ title: string; value: number | string; unit?: string }>;
    data?: { headers?: string[]; rows?: string[][] };
    layout?: { colSpan?: number };
};
type AssistantPresentation = {
    type: string;
    title: string;
    content: {
        layout?: { columns?: number };
        charts?: DashboardChart[];
    };
};
type ChatMessage = {
    role: 'user' | 'assistant';
    text: string;
    sources?: AssistantSource[];
    provider?: string;
    tools?: ToolCallMeta[];
    presentation?: AssistantPresentation | null;
};

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const STORAGE_KEY = 'phoenix.assistant.conversation_id';

const route = useRoute();
const toast = useToast();
const draft = ref('');
const loading = ref(false);
const messages = ref<ChatMessage[]>([]);
const conversationId = ref<string | null>(localStorage.getItem(STORAGE_KEY));
const scrollRef = ref<HTMLElement | null>(null);
const closeButtonRef = ref<HTMLButtonElement | null>(null);
const titleId = 'phoenix-assistant-panel-title';

const routeContext = computed(() => {
    const parts: string[] = [`Pantalla: ${route.path}`];
    const routineId = route.params.id;
    if (routineId && String(route.name ?? '').includes('routine')) {
        parts.push(`Rutina en contexto: #${routineId}`);
    }
    const assetId = route.params.assetId ?? route.query.asset;
    if (assetId) {
        parts.push(`Activo en contexto: #${assetId}`);
    }

    return parts.join('. ');
});

const suggestions = [
    'Muéstrame el dashboard de KPIs',
    '¿Qué rutinas hay recientes?',
    'Lista clientes',
    'Facturas recientes',
];

function onDocumentKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            document.addEventListener('keydown', onDocumentKeydown);
            if (messages.value.length === 0) {
                messages.value.push({
                    role: 'assistant',
                    text: 'Soy el asistente de Phoenix. Consulto datos reales (rutinas, clientes, facturas, sitios, KPIs). En la ficha de una rutina puedes generar narrativa y costo; en activos, OCR y sugerencias de insumos.',
                });
            }
            void nextTick(() => closeButtonRef.value?.focus());
        } else {
            document.removeEventListener('keydown', onDocumentKeydown);
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onDocumentKeydown);
});

watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        scrollRef.value?.scrollTo({ top: scrollRef.value.scrollHeight, behavior: 'smooth' });
    },
);

function historyPayload() {
    return messages.value
        .filter((m) => m.role === 'user' || (m.role === 'assistant' && m.text && !m.text.startsWith('Soy el asistente')))
        .slice(-12)
        .map((m) => ({ role: m.role, text: m.text }));
}

async function send() {
    const text = draft.value.trim();
    if (!text || loading.value) {
        return;
    }

    messages.value.push({ role: 'user', text });
    draft.value = '';
    loading.value = true;

    try {
        const res = await api<{
            data: {
                answer: string;
                sources: AssistantSource[];
                provider?: string;
                tool_calls?: ToolCallMeta[];
                conversation_id?: string | null;
                presentation?: AssistantPresentation | null;
            };
        }>('/insights/assistant', {
            method: 'POST',
            body: JSON.stringify({
                question: text,
                context: routeContext.value,
                conversation_id: conversationId.value,
                history: historyPayload().slice(0, -1),
            }),
        });

        if (res.data.conversation_id) {
            conversationId.value = res.data.conversation_id;
            localStorage.setItem(STORAGE_KEY, res.data.conversation_id);
        }

        messages.value.push({
            role: 'assistant',
            text: res.data.answer,
            sources: res.data.sources,
            provider: res.data.provider,
            tools: res.data.tool_calls,
            presentation: res.data.presentation ?? null,
        });
    } catch (e) {
        toast.error((e as Error).message);
        messages.value.push({
            role: 'assistant',
            text: 'No pude completar la consulta. Reintenta en unos segundos.',
        });
    } finally {
        loading.value = false;
    }
}

function useSuggestion(text: string) {
    draft.value = text;
    void send();
}

function resetChat() {
    conversationId.value = null;
    localStorage.removeItem(STORAGE_KEY);
    messages.value = [
        {
            role: 'assistant',
            text: 'Conversación reiniciada. ¿Qué necesitas consultar?',
        },
    ];
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void send();
    }
}

function formatKpiValue(value: number | string | undefined, unit?: string): string {
    if (value === undefined || value === null) {
        return '—';
    }
    const num = typeof value === 'number' ? value : Number(value);
    const base = Number.isFinite(num)
        ? new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(num)
        : String(value);

    return unit ? `${base} ${unit}` : base;
}
</script>

<template>
    <Teleport to="body">
        <Transition name="phoenix-assistant-backdrop">
            <div
                v-if="open"
                class="phoenix-assistant-backdrop"
                aria-hidden="true"
                @click="emit('close')"
            />
        </Transition>
        <Transition name="phoenix-assistant-panel">
            <aside
                v-if="open"
                class="phoenix-assistant-panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
            >
                <header class="phoenix-assistant-panel__header">
                    <div>
                        <p :id="titleId" class="phoenix-assistant-panel__title">Asistente Phoenix</p>
                        <p class="phoenix-assistant-panel__subtitle">Datos verificados · sin alucinaciones</p>
                    </div>
                    <div class="phoenix-assistant-panel__actions">
                        <button
                            type="button"
                            class="phoenix-assistant-panel__ghost"
                            title="Nueva conversación"
                            @click="resetChat"
                        >
                            Nueva
                        </button>
                        <button
                            ref="closeButtonRef"
                            type="button"
                            class="phoenix-assistant-panel__close"
                            aria-label="Cerrar asistente"
                            @click="emit('close')"
                        >
                            ×
                        </button>
                    </div>
                </header>

                <p class="phoenix-assistant-panel__context">
                    {{ routeContext }}
                </p>

                <div ref="scrollRef" class="phoenix-assistant-panel__messages">
                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        class="phoenix-assistant-msg"
                        :class="msg.role === 'user' ? 'phoenix-assistant-msg--user' : 'phoenix-assistant-msg--assistant'"
                    >
                        <p class="phoenix-assistant-msg__text">{{ msg.text }}</p>

                        <div
                            v-if="msg.presentation?.type === 'dashboard' && msg.presentation.content.charts?.length"
                            class="phoenix-assistant-dashboard"
                        >
                            <p class="phoenix-assistant-dashboard__title">{{ msg.presentation.title }}</p>
                            <div class="phoenix-assistant-dashboard__grid">
                                <template v-for="(chart, cIdx) in msg.presentation.content.charts" :key="cIdx">
                                    <div
                                        v-if="chart.type === 'kpi'"
                                        class="phoenix-assistant-kpi"
                                        :class="{ 'phoenix-assistant-kpi--hero': chart.hero }"
                                        :style="{ gridColumn: `span ${Math.min(12, chart.layout?.colSpan ?? 4)}` }"
                                    >
                                        <span class="phoenix-assistant-kpi__label">{{ chart.title }}</span>
                                        <strong class="phoenix-assistant-kpi__value">
                                            {{ formatKpiValue(chart.value, chart.unit) }}
                                        </strong>
                                    </div>
                                    <div
                                        v-else-if="chart.type === 'kpi-grid'"
                                        class="phoenix-assistant-kpi-grid"
                                        :style="{ gridColumn: 'span 12' }"
                                    >
                                        <p class="phoenix-assistant-kpi-grid__title">{{ chart.title }}</p>
                                        <div class="phoenix-assistant-kpi-grid__items">
                                            <div
                                                v-for="(metric, mIdx) in chart.metrics ?? []"
                                                :key="mIdx"
                                                class="phoenix-assistant-kpi phoenix-assistant-kpi--compact"
                                            >
                                                <span class="phoenix-assistant-kpi__label">{{ metric.title }}</span>
                                                <strong class="phoenix-assistant-kpi__value">
                                                    {{ formatKpiValue(metric.value, metric.unit) }}
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="chart.type === 'table'"
                                        class="phoenix-assistant-table-wrap"
                                        :style="{ gridColumn: 'span 12' }"
                                    >
                                        <p class="phoenix-assistant-kpi-grid__title">{{ chart.title }}</p>
                                        <table class="phoenix-assistant-table">
                                            <thead>
                                                <tr>
                                                    <th
                                                        v-for="(header, hIdx) in chart.data?.headers ?? []"
                                                        :key="hIdx"
                                                    >
                                                        {{ header }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, rIdx) in chart.data?.rows ?? []"
                                                    :key="rIdx"
                                                >
                                                    <td v-for="(cell, cellIdx) in row" :key="cellIdx">{{ cell }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p v-if="msg.provider" class="phoenix-assistant-msg__meta">
                            {{ msg.provider }}
                            <span v-if="msg.tools?.length">
                                · {{ msg.tools.map((t) => t.name).join(', ') }}
                            </span>
                        </p>
                        <ul v-if="msg.sources?.length" class="phoenix-assistant-msg__sources">
                            <li v-for="src in msg.sources" :key="`${src.type}-${src.id}`">{{ src.label }}</li>
                        </ul>
                    </div>
                    <p v-if="loading" class="phoenix-assistant-panel__loading">Consultando herramientas…</p>
                </div>

                <div v-if="messages.length <= 1" class="phoenix-assistant-panel__suggestions">
                    <button
                        v-for="item in suggestions"
                        :key="item"
                        type="button"
                        class="phoenix-assistant-suggestion"
                        @click="useSuggestion(item)"
                    >
                        {{ item }}
                    </button>
                </div>

                <footer class="phoenix-assistant-panel__footer">
                    <MaterialField
                        v-model="draft"
                        label="Mensaje"
                        placeholder="Pregunta sobre KPIs, rutinas, clientes…"
                        @keydown="onKeydown"
                    />
                    <AppButton type="button" class="w-full" :disabled="loading || !draft.trim()" @click="send">
                        {{ loading ? 'Enviando…' : 'Enviar' }}
                    </AppButton>
                </footer>
            </aside>
        </Transition>
    </Teleport>
</template>
