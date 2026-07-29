<script setup lang="ts">
import { computed } from 'vue';
import { clientPortalStepLabel, clientPortalTriggerLabel, formatPortalDateTime } from '@/lib/clientPortal';

export type PortalWorkflowTransition = {
    from_step?: string | null;
    to_step: string;
    trigger: string;
    occurred_at: string;
};

const props = defineProps<{
    currentStepKey?: string | null;
    transitions: PortalWorkflowTransition[];
}>();

const ordered = computed(() =>
    [...props.transitions].sort(
        (a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime(),
    ),
);
</script>

<template>
    <div class="client-portal-timeline">
        <p v-if="currentStepKey" class="client-portal-timeline__current text-portal-muted text-sm">
            Estado actual:
            <strong class="text-portal-heading">{{ clientPortalStepLabel(currentStepKey) }}</strong>
        </p>
        <ol class="client-portal-timeline__list">
            <li
                v-for="(t, index) in ordered"
                :key="`${t.occurred_at}-${index}`"
                class="client-portal-timeline__item"
            >
                <span class="client-portal-timeline__dot" aria-hidden="true" />
                <div class="client-portal-timeline__body">
                    <p class="text-portal-heading text-sm font-medium">
                        {{ clientPortalStepLabel(t.to_step) }}
                    </p>
                    <p class="text-portal-muted mt-0.5 text-xs">
                        {{ clientPortalTriggerLabel(t.trigger) }}
                        <span v-if="t.from_step" class="opacity-80">
                            · desde {{ clientPortalStepLabel(t.from_step) }}
                        </span>
                    </p>
                    <time class="text-portal-muted mt-1 block text-xs tabular-nums">
                        {{ formatPortalDateTime(t.occurred_at) }}
                    </time>
                </div>
            </li>
        </ol>
    </div>
</template>
