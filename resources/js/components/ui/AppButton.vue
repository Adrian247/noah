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
    primary:
        'bg-gradient-to-br from-amber-400 to-amber-600 text-stone-950 shadow-md shadow-amber-500/25 hover:brightness-105',
    secondary: 'app-button-secondary',
    danger: 'bg-red-600/90 text-white hover:bg-red-600',
    ghost: 'app-button-ghost',
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
