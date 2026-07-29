<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        label: string;
        /** Si true, permite quitar el color (valor indefinido en el componente). */
        optional?: boolean;
        defaultHex?: string;
    }>(),
    { optional: false, defaultHex: '#111827' },
);

const emit = defineEmits<{
    'update:modelValue': [string | undefined];
}>();

const hexPattern = /^#[0-9A-Fa-f]{6}$/;

const pickerHex = computed({
    get(): string {
        const v = (props.modelValue ?? '').trim();
        return hexPattern.test(v) ? v : props.defaultHex;
    },
    set(value: string) {
        emit('update:modelValue', value);
    },
});

const displayHex = computed(() => {
    const v = (props.modelValue ?? '').trim();
    return hexPattern.test(v) ? v : null;
});

const showClear = computed(() => props.optional && displayHex.value !== null);

function clearColor(): void {
    emit('update:modelValue', undefined);
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <label class="text-portal-muted flex flex-wrap items-center gap-2 text-xs">
            <span class="text-portal-heading min-w-0">{{ label }}</span>
            <input
                v-model="pickerHex"
                type="color"
                class="h-8 w-12 shrink-0 cursor-pointer rounded border border-transparent bg-transparent"
                :aria-label="label"
            />
            <span v-if="displayHex" class="font-mono text-[10px] opacity-70">{{ displayHex }}</span>
        </label>
        <button
            v-if="showClear"
            type="button"
            class="text-portal-link w-fit text-xs underline"
            @click="clearColor"
        >
            Quitar color
        </button>
    </div>
</template>
