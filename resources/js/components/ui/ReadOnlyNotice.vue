<script setup lang="ts">
defineProps<{ moduleLabel?: string }>();
</script>

<template>
    <p class="text-sm text-slate-500">
        Solo lectura{{ moduleLabel ? ` (${moduleLabel})` : '' }}: no puedes crear ni modificar registros.
    </p>
</template>
