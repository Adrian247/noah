<script setup lang="ts">
defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <header class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 max-w-2xl text-sm text-slate-600">{{ subtitle }}</p>
    </header>
</template>
