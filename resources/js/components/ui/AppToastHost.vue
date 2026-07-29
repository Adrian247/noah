<script setup lang="ts">
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const styles = {
    success: 'border-emerald-500/45 bg-emerald-950 text-emerald-50 shadow-emerald-900/30',
    danger: 'border-red-500/45 bg-red-950 text-red-50 shadow-red-900/40',
    warning: 'border-amber-500/45 bg-amber-950 text-amber-50 shadow-amber-900/30',
    info: 'border-sky-500/40 bg-slate-900 text-slate-50 shadow-slate-900/40',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed top-4 right-0 z-[200] flex w-full max-w-md flex-col items-end gap-2 px-4 sm:px-6"
            aria-live="polite"
            aria-relevant="additions"
        >
            <TransitionGroup name="app-toast">
                <div
                    v-for="item in toast.items"
                    :key="item.id"
                    class="pointer-events-auto w-full rounded-xl border px-4 py-3 text-sm shadow-2xl backdrop-blur-md"
                    :class="styles[item.variant]"
                    role="alert"
                >
                    <div class="flex items-start gap-3">
                        <p class="min-w-0 flex-1 font-medium leading-snug">{{ item.message }}</p>
                        <button
                            type="button"
                            class="shrink-0 rounded-md px-2 py-0.5 text-xs opacity-80 hover:opacity-100"
                            aria-label="Cerrar"
                            @click="toast.dismiss(item.id)"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
