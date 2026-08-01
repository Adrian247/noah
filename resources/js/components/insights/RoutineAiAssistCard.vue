<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';

const props = defineProps<{
    routineId: number;
}>();

const toast = useToast();
const openAssistant = inject<(() => void) | undefined>('openPhoenixAssistant', undefined);
const loading = ref(false);
const narrative = ref('');
const costEstimate = ref<Record<string, unknown> | null>(null);

const formattedCost = computed(() => {
    if (!costEstimate.value) {
        return null;
    }
    const total = costEstimate.value.estimated_total;
    const currency = costEstimate.value.currency ?? 'MXN';
    return typeof total === 'number' ? `${currency} ${total.toFixed(2)}` : null;
});

async function analyze() {
    loading.value = true;
    narrative.value = '';
    costEstimate.value = null;
    try {
        const [narr, cost] = await Promise.all([
            api<{ data: { narrative: string } }>(`/insights/routines/${props.routineId}/narrative`),
            api<{ data: Record<string, unknown> }>(`/insights/routines/${props.routineId}/cost-estimate`),
        ]);
        narrative.value = narr.data.narrative;
        costEstimate.value = cost.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="portal-form-panel space-y-3 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-portal-heading text-sm font-semibold">Asistencia IA</h3>
                <p class="text-portal-muted text-xs">
                    Narrativa factual y costo estimado con datos de esta rutina.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <AppButton
                    v-if="openAssistant"
                    type="button"
                    variant="ghost"
                    @click="openAssistant()"
                >
                    Abrir chat
                </AppButton>
                <AppButton type="button" variant="secondary" :disabled="loading" @click="analyze">
                    {{ loading ? 'Analizando…' : 'Generar narrativa y costo' }}
                </AppButton>
            </div>
        </div>
        <p v-if="formattedCost" class="text-portal-heading text-sm">
            Costo estimado: <strong>{{ formattedCost }}</strong>
            <span v-if="costEstimate?.sample_size" class="text-portal-muted text-xs">
                (muestra {{ costEstimate.sample_size }})
            </span>
        </p>
        <pre
            v-if="narrative"
            class="text-portal-heading max-h-64 overflow-y-auto whitespace-pre-wrap rounded-lg border border-[var(--portal-edge-label-border)] bg-[var(--portal-canvas-bg)] p-3 text-sm"
        >{{ narrative }}</pre>
    </section>
</template>
