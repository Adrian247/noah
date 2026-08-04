<script setup lang="ts">
import { storeToRefs } from 'pinia';
import AppButton from '@/components/ui/AppButton.vue';
import AppModal from '@/components/ui/AppModal.vue';
import { useConfirmStore } from '@/stores/confirm';

const confirmStore = useConfirmStore();
const { request } = storeToRefs(confirmStore);
</script>

<template>
    <AppModal
        v-if="request"
        :open="!!request"
        :title="request.title"
        size="sm"
        :tone="request.danger ? 'danger' : 'default'"
        confirm
        @close="confirmStore.cancel()"
    >
        <p class="app-confirm__message">{{ request.message }}</p>
        <template #footer>
            <AppButton type="button" variant="secondary" @click="confirmStore.cancel()">
                {{ request.cancelLabel }}
            </AppButton>
            <AppButton
                type="button"
                :variant="request.danger ? 'danger' : 'primary'"
                @click="confirmStore.confirm()"
            >
                {{ request.confirmLabel }}
            </AppButton>
        </template>
    </AppModal>
</template>
