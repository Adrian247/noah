<script setup lang="ts">
defineProps<{ moduleLabel?: string }>();
</script>

<template>
    <p class="text-portal-muted text-sm">
        Solo lectura{{ moduleLabel ? ` (${moduleLabel})` : '' }}: no puedes crear ni modificar registros.
    </p>
</template>
