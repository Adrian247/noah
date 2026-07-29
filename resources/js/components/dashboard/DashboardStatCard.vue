<script setup lang="ts">
import { RouterLink } from 'vue-router';
import NavIcon, { type NavIconName } from '@/components/ui/NavIcon.vue';

defineProps<{
    label: string;
    value: string | number;
    to?: string;
    icon: NavIconName;
    tone?: 'amber' | 'sky' | 'emerald' | 'violet' | 'rose' | 'slate';
    hint?: string;
}>();

const toneClass: Record<string, string> = {
    amber: 'dashboard-stat-card--amber',
    sky: 'dashboard-stat-card--sky',
    emerald: 'dashboard-stat-card--emerald',
    violet: 'dashboard-stat-card--violet',
    rose: 'dashboard-stat-card--rose',
    slate: 'dashboard-stat-card--slate',
};
</script>

<template>
    <component
        :is="to ? RouterLink : 'div'"
        :to="to"
        class="dashboard-stat-card phoenix-glass-card md-ripple-hover"
        :class="[tone ? toneClass[tone] : 'dashboard-stat-card--slate', to ? 'dashboard-stat-card--link' : '']"
    >
        <div class="dashboard-stat-card__glow" aria-hidden="true" />
        <div class="dashboard-stat-card__icon" aria-hidden="true">
            <NavIcon :name="icon" size="sm" />
        </div>
        <div class="dashboard-stat-card__body">
            <p class="dashboard-stat-card__label">{{ label }}</p>
            <p class="dashboard-stat-value dashboard-stat-card__value">{{ value }}</p>
            <p v-if="hint" class="dashboard-stat-card__hint">{{ hint }}</p>
        </div>
    </component>
</template>
