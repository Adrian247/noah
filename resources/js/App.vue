<script setup lang="ts">
import { RouterView } from 'vue-router';
import { storeToRefs } from 'pinia';
import AppConfirmHost from '@/components/ui/AppConfirmHost.vue';
import AppToastHost from '@/components/ui/AppToastHost.vue';
import PhoenixSystemEnter from '@/components/ui/PhoenixSystemEnter.vue';
import { useSystemEnterStore } from '@/stores/systemEnter';

const systemEnter = useSystemEnterStore();
const { active, message, sessionKey } = storeToRefs(systemEnter);
</script>

<template>
    <PhoenixSystemEnter v-if="active" :key="sessionKey" :message="message" />
    <AppToastHost />
    <AppConfirmHost />
    <RouterView />
</template>
