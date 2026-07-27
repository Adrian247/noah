<script setup lang="ts">
import GlassCard from '@/components/ui/GlassCard.vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const emit = defineEmits<{
    close: [];
}>();

const sizeClass: Record<string, string> = {
    sm: 'max-w-md',
    md: 'max-w-2xl',
    lg: 'max-w-3xl',
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-30 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'modal-title-' + title.replace(/\s/g, '-')"
        @click.self="emit('close')"
    >
        <GlassCard
            :class="['flex max-h-[90vh] w-full flex-col', sizeClass[size]]"
            padding="none"
        >
            <div class="border-b border-white/10 px-6 py-4">
                <h3 :id="'modal-title-' + title.replace(/\s/g, '-')" class="text-portal-heading text-lg font-semibold">
                    {{ title }}
                </h3>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                <slot />
            </div>
            <div
                v-if="$slots.footer"
                class="modal-footer flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-white/10 px-6 py-4"
            >
                <slot name="footer" />
            </div>
        </GlassCard>
    </div>
</template>
