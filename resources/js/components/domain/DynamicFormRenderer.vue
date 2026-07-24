<script setup lang="ts">
import { computed } from 'vue';

export type FormField = {
    key: string;
    type: string;
    label: string;
    required?: boolean;
};

export type FormSection = {
    title?: string;
    fields: FormField[];
};

const props = defineProps<{
    schema: { sections?: FormSection[] } | null | undefined;
    disabled?: boolean;
}>();

const model = defineModel<Record<string, string | number>>({ default: () => ({}) });

const sections = computed(() => props.schema?.sections ?? []);

function fieldValue(key: string): string {
    const v = model.value[key];
    return v === undefined || v === null ? '' : String(v);
}

function updateField(key: string, value: string) {
    model.value = { ...model.value, [key]: value };
}
</script>

<template>
    <div v-if="sections.length" class="space-y-4">
        <section
            v-for="(section, idx) in sections"
            :key="idx"
            class="rounded-lg border border-slate-200 bg-white p-4"
        >
            <h3 v-if="section.title" class="text-sm font-medium text-slate-800">
                {{ section.title }}
            </h3>
            <div class="mt-3 space-y-3">
                <label
                    v-for="field in section.fields"
                    :key="field.key"
                    class="block text-sm"
                >
                    {{ field.label }}
                    <textarea
                        v-if="field.type === 'textarea'"
                        :value="fieldValue(field.key)"
                        :required="field.required"
                        :disabled="disabled"
                        rows="3"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 disabled:bg-slate-50"
                        @input="updateField(field.key, ($event.target as HTMLTextAreaElement).value)"
                    />
                    <input
                        v-else-if="field.type === 'number'"
                        :value="fieldValue(field.key)"
                        type="number"
                        :required="field.required"
                        :disabled="disabled"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 disabled:bg-slate-50"
                        @input="updateField(field.key, ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        v-else
                        :value="fieldValue(field.key)"
                        type="text"
                        :required="field.required"
                        :disabled="disabled"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 disabled:bg-slate-50"
                        @input="updateField(field.key, ($event.target as HTMLInputElement).value)"
                    />
                </label>
            </div>
        </section>
    </div>
</template>
