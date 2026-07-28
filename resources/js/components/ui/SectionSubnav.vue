<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useModuleAccess } from '@/composables/useModuleAccess';
import type { SectionSubnavItem } from '@/lib/sectionNav';

const props = defineProps<{
    items: SectionSubnavItem[];
}>();

const route = useRoute();
const { isVisible } = useModuleAccess();

const visibleItems = computed(() =>
    props.items.filter((item) => !item.moduleId || isVisible(item.moduleId)),
);

function isActive(to: string): boolean {
    return route.path === to;
}
</script>

<template>
    <nav v-if="visibleItems.length > 1" class="section-subnav" aria-label="Sección">
        <RouterLink
            v-for="item in visibleItems"
            :key="item.to"
            :to="item.to"
            class="section-subnav__link"
            :class="{ 'section-subnav__link--active': isActive(item.to) }"
        >
            {{ item.label }}
        </RouterLink>
    </nav>
</template>

<style scoped>
.section-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 1rem;
    padding: 0.2rem;
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--portal-surface-muted, rgba(255, 255, 255, 0.04)) 80%, transparent);
}

.section-subnav__link {
    padding: 0.4rem 0.85rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--portal-muted, #94a3b8);
    text-decoration: none;
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.section-subnav__link:hover {
    color: var(--portal-heading, #e2e8f0);
    background: rgba(255, 255, 255, 0.06);
}

.section-subnav__link--active {
    color: var(--portal-heading, #f1f5f9);
    background: rgba(255, 255, 255, 0.1);
}

[data-theme='light'] .section-subnav {
    background: rgba(15, 23, 42, 0.04);
}

[data-theme='light'] .section-subnav__link--active {
    background: rgba(15, 23, 42, 0.08);
    color: #0f172a;
}
</style>
