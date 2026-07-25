<script setup lang="ts">
withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    { variant: 'primary', type: 'button', disabled: false },
);

const classes = {
    primary: 'bg-primary-600 text-white shadow-md hover:bg-primary-700',
    secondary: 'border border-slate-300/80 bg-white/90 text-slate-800 hover:bg-white',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    ghost: 'text-primary-700 hover:bg-primary-50',
};
</script>

<template>
    <button
        :type="type"
        class="md-ripple-hover inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-50"
        :class="classes[variant]"
        :disabled="disabled"
    >
        <slot />
    </button>
</template>
