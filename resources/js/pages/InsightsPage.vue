<script setup lang="ts">
import { computed, ref } from 'vue';
import { api, getCompanyId, getToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import GlassCard from '@/components/ui/GlassCard.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

type AssistantSource = { type: string; id: number; label: string };

const toast = useToast();
const question = ref('');
const assistantAnswer = ref('');
const assistantSources = ref<AssistantSource[]>([]);
const assistantLoading = ref(false);

const ocrFile = ref<File | null>(null);
const ocrText = ref('');
const ocrLoading = ref(false);

const routineId = ref('');
const narrative = ref('');
const costEstimate = ref<Record<string, unknown> | null>(null);
const insightsLoading = ref(false);

const assetId = ref('');
const supplySuggestions = ref<Record<string, unknown>[]>([]);

async function askAssistant() {
    if (!question.value.trim()) {
        return;
    }
    assistantLoading.value = true;
    assistantAnswer.value = '';
    assistantSources.value = [];
    try {
        const res = await api<{ data: { answer: string; sources: AssistantSource[] } }>('/insights/assistant', {
            method: 'POST',
            body: JSON.stringify({ question: question.value.trim() }),
        });
        assistantAnswer.value = res.data.answer;
        assistantSources.value = res.data.sources;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        assistantLoading.value = false;
    }
}

function onOcrFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    ocrFile.value = input.files?.[0] ?? null;
}

async function runOcr() {
    if (!ocrFile.value) {
        toast.error('Selecciona una imagen.');
        return;
    }
    ocrLoading.value = true;
    ocrText.value = '';
    const form = new FormData();
    form.append('file', ocrFile.value);
    try {
        const res = await fetch('/api/v1/insights/ocr', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${getToken()}`,
                'X-Company-Id': String(getCompanyId() ?? ''),
            },
            body: form,
        });
        const json = await res.json();
        if (!res.ok) {
            throw new Error(json.message ?? 'Error OCR');
        }
        ocrText.value = json.data?.text ?? '';
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        ocrLoading.value = false;
    }
}

async function loadRoutineInsights() {
    const id = routineId.value.trim();
    if (!id) {
        return;
    }
    insightsLoading.value = true;
    narrative.value = '';
    costEstimate.value = null;
    try {
        const [narr, cost] = await Promise.all([
            api<{ data: { narrative: string } }>(`/insights/routines/${id}/narrative`),
            api<{ data: Record<string, unknown> }>(`/insights/routines/${id}/cost-estimate`),
        ]);
        narrative.value = narr.data.narrative;
        costEstimate.value = cost.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        insightsLoading.value = false;
    }
}

async function loadSupplySuggestions() {
    const id = assetId.value.trim();
    if (!id) {
        return;
    }
    try {
        const res = await api<{ data: Record<string, unknown>[] }>(`/insights/assets/${id}/supply-suggestions`);
        supplySuggestions.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    }
}

const formattedCost = computed(() => {
    if (!costEstimate.value) {
        return null;
    }
    const total = costEstimate.value.estimated_total;
    const currency = costEstimate.value.currency ?? 'MXN';
    return typeof total === 'number' ? `${currency} ${total.toFixed(2)}` : null;
});
</script>

<template>
    <div class="portal-page space-y-6">
        <PageHeader
            title="Insights IA"
            subtitle="Asistente operativo, narrativa de reportes, estimación de costos y OCR de placas."
        />

        <GlassCard padding="lg" class="space-y-4">
            <h2 class="text-portal-heading text-lg font-medium">Asistente</h2>
            <p class="text-portal-muted text-sm">
                Pregunta por rutinas recientes o eventos de auditoría (respuestas basadas en datos de la empresa).
            </p>
            <MaterialField v-model="question" label="Pregunta" placeholder="¿Qué rutinas están activas?" />
            <AppButton type="button" :disabled="assistantLoading" @click="askAssistant">
                {{ assistantLoading ? 'Consultando…' : 'Preguntar' }}
            </AppButton>
            <pre v-if="assistantAnswer" class="text-portal-heading whitespace-pre-wrap rounded-lg border border-white/10 bg-black/20 p-3 text-sm">{{ assistantAnswer }}</pre>
            <ul v-if="assistantSources.length" class="text-portal-muted list-inside list-disc text-xs">
                <li v-for="src in assistantSources" :key="`${src.type}-${src.id}`">{{ src.label }}</li>
            </ul>
        </GlassCard>

        <GlassCard padding="lg" class="space-y-4">
            <h2 class="text-portal-heading text-lg font-medium">OCR de placa / etiqueta</h2>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onOcrFileChange" />
            <AppButton type="button" :disabled="ocrLoading" @click="runOcr">
                {{ ocrLoading ? 'Procesando…' : 'Extraer texto' }}
            </AppButton>
            <p v-if="ocrText" class="text-portal-heading font-mono text-sm">{{ ocrText }}</p>
        </GlassCard>

        <GlassCard padding="lg" class="space-y-4">
            <h2 class="text-portal-heading text-lg font-medium">Rutina — narrativa y costo</h2>
            <div class="flex flex-wrap items-end gap-3">
                <MaterialField v-model="routineId" label="ID de rutina" type="number" class="max-w-xs" />
                <AppButton type="button" :disabled="insightsLoading" @click="loadRoutineInsights">
                    Analizar
                </AppButton>
            </div>
            <p v-if="formattedCost" class="text-portal-heading text-sm">
                Costo estimado: <strong>{{ formattedCost }}</strong>
                <span v-if="costEstimate?.sample_size" class="text-portal-muted">
                    (muestra {{ costEstimate.sample_size }})
                </span>
            </p>
            <pre v-if="narrative" class="text-portal-heading whitespace-pre-wrap rounded-lg border border-white/10 bg-black/20 p-3 text-sm">{{ narrative }}</pre>
        </GlassCard>

        <GlassCard padding="lg" class="space-y-4">
            <h2 class="text-portal-heading text-lg font-medium">Refacciones sugeridas por activo</h2>
            <div class="flex flex-wrap items-end gap-3">
                <MaterialField v-model="assetId" label="ID de activo" type="number" class="max-w-xs" />
                <AppButton type="button" @click="loadSupplySuggestions">Sugerir</AppButton>
            </div>
            <ul v-if="supplySuggestions.length" class="text-portal-heading space-y-1 text-sm">
                <li v-for="item in supplySuggestions" :key="String(item.supply_item_id)">
                    {{ item.name }} — uso {{ item.usage_count }}×
                </li>
            </ul>
        </GlassCard>
    </div>
</template>
