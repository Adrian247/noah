<script setup lang="ts">
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        type?: string;
        required?: boolean;
        autocomplete?: string;
    }>(),
    { type: 'text', required: false },
);

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const inputId = useId();

const hasValue = computed(() => props.modelValue.length > 0);
</script>

<template>
    <label class="material-field block" :class="{ 'material-field--filled': hasValue }">
        <span class="material-field__label">{{ label }}</span>
        <input
            :id="inputId"
            class="material-field__input"
            :type="type"
            :required="required"
            :autocomplete="autocomplete"
            :value="modelValue"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <span class="material-field__line" aria-hidden="true" />
    </label>
</template>
