<script setup lang="ts">
import { useId } from 'vue';

export type MaterialSelectOption = {
    value: string | number;
    label: string;
};

const props = withDefaults(
    defineProps<{
        modelValue: string | number;
        label: string;
        options: MaterialSelectOption[];
        required?: boolean;
        disabled?: boolean;
        compact?: boolean;
    }>(),
    { required: false, disabled: false, compact: false },
);

const emit = defineEmits<{ 'update:modelValue': [string | number] }>();

const inputId = useId();

function onChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const match = props.options.find((o) => String(o.value) === value);
    emit('update:modelValue', match?.value ?? value);
}
</script>

<template>
    <label
        class="material-field material-field--select block"
        :class="{
            'material-field--filled': true,
            'material-field--compact': compact,
        }"
    >
        <span class="material-field__label">{{ label }}</span>
        <select
            :id="inputId"
            class="material-field__input material-field__select"
            :required="required"
            :disabled="disabled"
            :value="modelValue"
            @change="onChange"
        >
            <option v-for="opt in options" :key="String(opt.value)" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <span class="material-field__line" aria-hidden="true" />
    </label>
</template>
