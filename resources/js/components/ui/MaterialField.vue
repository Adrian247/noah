<script setup lang="ts">
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | number;
        label: string;
        type?: string;
        required?: boolean;
        autocomplete?: string;
        multiline?: boolean;
        rows?: number;
        inputmode?: string;
        compact?: boolean;
        placeholder?: string;
        readonly?: boolean;
        name?: string;
    }>(),
    { type: 'text', required: false, multiline: false, rows: 3, compact: false },
);

const emit = defineEmits<{
    'update:modelValue': [string | number];
    focus: [FocusEvent];
}>();

const inputId = useId();

const nativePickerTypes = new Set(['date', 'time', 'datetime-local', 'month', 'week']);

const hasValue = computed(() => String(props.modelValue ?? '').trim().length > 0);

const labelFloated = computed(
    () =>
        hasValue.value ||
        Boolean(props.placeholder?.trim()) ||
        nativePickerTypes.has(props.type ?? 'text'),
);

function onNativeInput(raw: string) {
    if (props.type === 'number') {
        if (raw.trim() === '') {
            emit('update:modelValue', '');
            return;
        }
        const n = Number(raw);
        emit('update:modelValue', Number.isFinite(n) ? n : raw);
        return;
    }
    emit('update:modelValue', raw);
}
</script>

<template>
    <label
        class="material-field block"
        :class="{
            'material-field--filled': labelFloated,
            'material-field--placeholder': Boolean(props.placeholder?.trim()),
            'material-field--native-picker': nativePickerTypes.has(props.type ?? 'text'),
            'material-field--compact': compact,
        }"
    >
        <span class="material-field__label">{{ label }}</span>
        <textarea
            v-if="multiline"
            :id="inputId"
            class="material-field__input material-field__textarea resize-y"
            :required="required"
            :rows="rows"
            :placeholder="placeholder"
            :readonly="readonly"
            :name="name"
            :value="modelValue"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
            @focus="emit('focus', $event)"
        />
        <input
            v-else
            :id="inputId"
            class="material-field__input"
            :type="type"
            :required="required"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :placeholder="placeholder"
            :readonly="readonly"
            :name="name"
            :value="modelValue"
            @input="onNativeInput(($event.target as HTMLInputElement).value)"
            @focus="emit('focus', $event)"
        />
        <span class="material-field__line" aria-hidden="true" />
    </label>
</template>
