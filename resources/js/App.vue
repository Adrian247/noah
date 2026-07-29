<script setup lang="ts">
import { RouterView } from 'vue-router';
import { storeToRefs } from 'pinia';
import PhoenixSystemEnter from '@/components/ui/PhoenixSystemEnter.vue';
import { useSystemEnterStore } from '@/stores/systemEnter';

const systemEnter = useSystemEnterStore();
const { active, message, sessionKey } = storeToRefs(systemEnter);
</script>

<template>
    <PhoenixSystemEnter v-if="active" :key="sessionKey" :message="message" />
    <RouterView />
</template>
