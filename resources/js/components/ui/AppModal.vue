<script setup lang="ts">
import GlassCard from '@/components/ui/GlassCard.vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        /** Elevated confirm dialogs sit above toasts / assistant. */
        confirm?: boolean;
        tone?: 'default' | 'danger';
    }>(),
    { size: 'md', confirm: false, tone: 'default' },
);

const emit = defineEmits<{
    close: [];
}>();

const sizeClass: Record<string, string> = {
    sm: 'max-w-md',
    md: 'max-w-2xl',
    lg: 'max-w-3xl',
    xl: 'max-w-4xl',
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="app-modal-overlay fixed inset-0 flex items-end justify-center p-4 sm:items-center"
            :class="confirm ? 'app-modal-overlay--confirm z-[260]' : 'z-[110]'"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="'modal-title-' + title.replace(/\s/g, '-')"
            @click.self="emit('close')"
        >
            <GlassCard
                :class="[
                    'app-modal-panel flex max-h-[90vh] w-full flex-col',
                    sizeClass[size],
                    tone === 'danger' ? 'app-modal-panel--danger' : '',
                    confirm ? 'app-modal-panel--confirm' : '',
                ]"
                padding="none"
            >
                <div
                    class="app-modal-panel__header border-b px-6 py-4"
                    :class="tone === 'danger' ? 'app-modal-panel__header--danger' : ''"
                >
                    <div v-if="tone === 'danger'" class="app-confirm__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                            />
                        </svg>
                    </div>
                    <h3
                        :id="'modal-title-' + title.replace(/\s/g, '-')"
                        class="text-portal-heading text-lg font-semibold"
                    >
                        {{ title }}
                    </h3>
                </div>
                <div class="portal-scroll min-h-0 flex-1 px-6 py-4">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="modal-footer app-modal-panel__footer flex shrink-0 flex-wrap items-center justify-end gap-2 border-t px-6 py-4"
                >
                    <slot name="footer" />
                </div>
            </GlassCard>
        </div>
    </Teleport>
</template>
