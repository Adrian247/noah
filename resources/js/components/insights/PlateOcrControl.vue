<script setup lang="ts">
import { ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';

const props = defineProps<{
    /** Campo sugerido al aplicar el texto OCR */
    applyLabel?: string;
}>();

const emit = defineEmits<{
    apply: [text: string];
}>();

const toast = useToast();
const file = ref<File | null>(null);
const text = ref('');
const loading = ref(false);

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
}

function looksLikeOcrError(value: string): boolean {
    const lower = value.toLowerCase();
    return (
        lower.startsWith('ocr no disponible') ||
        lower.startsWith('ocr falló') ||
        lower.includes('configure openai') ||
        lower.includes('error ocr')
    );
}

async function runOcr() {
    if (!file.value) {
        toast.error('Selecciona una imagen de placa o etiqueta.');
        return;
    }
    loading.value = true;
    text.value = '';
    const form = new FormData();
    form.append('file', file.value);
    try {
        const res = await api<{ data: { text?: string } }>('/insights/ocr', {
            method: 'POST',
            body: form,
        });
        const extracted = (res.data?.text ?? '').trim();
        if (!extracted || extracted === '—') {
            toast.warning('No se detectó texto en la imagen.');
            return;
        }
        if (looksLikeOcrError(extracted)) {
            toast.error(extracted);
            return;
        }
        text.value = extracted;
    } catch (e) {
        text.value = '';
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

function apply() {
    if (text.value) {
        emit('apply', text.value);
        toast.success(props.applyLabel ? `Texto aplicado a ${props.applyLabel}.` : 'Texto aplicado.');
    }
}
</script>

<template>
    <div class="space-y-2">
        <p class="text-portal-muted text-xs">OCR de placa o etiqueta (vía AI Gateway).</p>
        <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="block w-full text-xs text-portal-muted file:mr-2 file:rounded-md file:border-0 file:bg-amber-500/15 file:px-2 file:py-1 file:text-portal-heading"
            @change="onFileChange"
        />
        <div class="flex flex-wrap gap-2">
            <AppButton type="button" variant="secondary" :disabled="loading || !file" @click="runOcr">
                {{ loading ? 'Procesando…' : 'Extraer texto' }}
            </AppButton>
            <AppButton
                v-if="text"
                type="button"
                variant="ghost"
                @click="apply"
            >
                Aplicar{{ applyLabel ? ` a ${applyLabel}` : '' }}
            </AppButton>
        </div>
        <p v-if="text" class="text-portal-heading font-mono text-sm whitespace-pre-wrap">{{ text }}</p>
    </div>
</template>
