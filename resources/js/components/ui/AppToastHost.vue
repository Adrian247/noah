<script setup lang="ts">
import AlertBanner from '@/components/ui/AlertBanner.vue';
import { useToastStore, type ToastVariant } from '@/stores/toast';

const toast = useToastStore();

const bannerVariant: Record<
    ToastVariant,
    'info' | 'success' | 'warning' | 'danger'
> = {
    success: 'success',
    danger: 'danger',
    warning: 'warning',
    info: 'info',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="app-toast-host pointer-events-none fixed top-4 right-0 z-[250] flex w-full flex-col items-end gap-2.5 px-4 sm:top-5 sm:px-6"
            aria-live="polite"
            aria-relevant="additions"
        >
            <TransitionGroup name="app-toast">
                <AlertBanner
                    v-for="item in toast.items"
                    :key="item.id"
                    :variant="bannerVariant[item.variant]"
                    class="app-toast pointer-events-auto w-full"
                    role="alert"
                >
                    <div class="flex items-start gap-3">
                        <p class="min-w-0 flex-1 font-medium">{{ item.message }}</p>
                        <button
                            type="button"
                            class="app-toast__dismiss shrink-0 rounded-lg p-1"
                            aria-label="Cerrar"
                            @click="toast.dismiss(item.id)"
                        >
                            <span aria-hidden="true" class="block text-base leading-none">×</span>
                        </button>
                    </div>
                </AlertBanner>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
