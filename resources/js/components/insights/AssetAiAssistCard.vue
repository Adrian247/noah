<script setup lang="ts">
import { ref, watch } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import PlateOcrControl from '@/components/insights/PlateOcrControl.vue';

const props = defineProps<{
    assetId: number;
    showOcr?: boolean;
}>();

const emit = defineEmits<{
    'apply-ocr': [text: string];
}>();

const toast = useToast();
const loading = ref(false);
const suggestions = ref<Record<string, unknown>[]>([]);

async function loadSuggestions() {
    loading.value = true;
    suggestions.value = [];
    try {
        const res = await api<{ data: Record<string, unknown>[] }>(
            `/insights/assets/${props.assetId}/supply-suggestions`,
        );
        suggestions.value = res.data;
        if (suggestions.value.length === 0) {
            toast.info('Sin sugerencias de insumos para este activo todavía.');
        }
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.assetId,
    () => {
        suggestions.value = [];
    },
);
</script>

<template>
    <div class="space-y-4 rounded-xl border border-[var(--portal-edge-label-border)] bg-[var(--portal-canvas-bg)] p-3">
        <div class="space-y-2">
            <p class="text-portal-heading text-sm font-semibold">IA · refacciones</p>
            <p class="text-portal-muted text-xs">Sugerencias según historial de consumos del activo.</p>
            <AppButton type="button" variant="secondary" :disabled="loading" @click="loadSuggestions">
                {{ loading ? 'Consultando…' : 'Sugerir insumos' }}
            </AppButton>
            <ul v-if="suggestions.length" class="text-portal-heading space-y-1 text-sm">
                <li v-for="item in suggestions" :key="String(item.supply_item_id)">
                    {{ item.name }} — uso {{ item.usage_count }}×
                </li>
            </ul>
        </div>
        <div v-if="showOcr" class="border-t border-[var(--portal-edge-label-border)] pt-3">
            <p class="text-portal-heading mb-2 text-sm font-semibold">IA · OCR</p>
            <PlateOcrControl apply-label="etiqueta / serie" @apply="emit('apply-ocr', $event)" />
        </div>
    </div>
</template>
