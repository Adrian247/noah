<script setup lang="ts">
const model = defineModel<string>({ default: '' });

const emit = defineEmits<{ 'update:modelValue': [string] }>();

defineProps<{
    label?: string;
    multiline?: boolean;
    rows?: number;
}>();

function wrap(before: string, after: string) {
    const el = document.activeElement as HTMLTextAreaElement | null;
    const text = model.value;
    if (el && el.tagName === 'TEXTAREA') {
        const start = el.selectionStart;
        const end = el.selectionEnd;
        const selected = text.slice(start, end);
        const next = text.slice(0, start) + before + selected + after + text.slice(end);
        model.value = next;
        emit('update:modelValue', next);
        return;
    }
    model.value = text + before + after;
    emit('update:modelValue', model.value);
}

function insertPrefix(prefix: string) {
    model.value = prefix + model.value;
    emit('update:modelValue', model.value);
}
</script>

<template>
    <div class="space-y-2">
        <p v-if="label" class="text-portal-heading text-sm font-medium">{{ label }}</p>
        <div class="flex flex-wrap gap-1">
            <button type="button" class="report-md-btn" title="Negrita" @click="wrap('**', '**')">B</button>
            <button type="button" class="report-md-btn" title="Cursiva" @click="wrap('*', '*')">I</button>
            <button type="button" class="report-md-btn" title="Subtítulo" @click="insertPrefix('## ')">H2</button>
            <button type="button" class="report-md-btn" title="Lista" @click="insertPrefix('- ')">•</button>
        </div>
        <textarea
            :value="model"
            :rows="rows ?? 4"
            class="portal-input w-full font-mono text-sm"
            @input="model = ($event.target as HTMLTextAreaElement).value; emit('update:modelValue', model)"
        />
        <p class="text-portal-muted text-xs">Markdown: **negrita**, *cursiva*, listas con «-».</p>
    </div>
</template>
