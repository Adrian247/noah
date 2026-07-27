<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute();

const activeTab = computed(() => (route.name === 'report-section-config' ? 'settings' : 'templates'));
</script>

<template>
    <nav class="report-design-nav flex flex-wrap gap-1 border-b border-white/10 pb-3" aria-label="Reportes">
        <RouterLink
            to="/app/design/reports"
            class="report-design-nav__tab"
            :class="{ 'report-design-nav__tab--active': activeTab === 'templates' }"
        >
            Plantillas
        </RouterLink>
        <RouterLink
            to="/app/design/reports/settings"
            class="report-design-nav__tab"
            :class="{ 'report-design-nav__tab--active': activeTab === 'settings' }"
        >
            Configuración
            <span class="text-portal-muted font-normal">· Secciones</span>
        </RouterLink>
    </nav>
</template>

<style scoped>
.report-design-nav__tab {
    border-radius: 0.5rem;
    padding: 0.4rem 0.85rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--portal-muted, rgb(148 163 184));
    transition: background 0.15s, color 0.15s;
}
.report-design-nav__tab:hover {
    background: rgb(255 255 255 / 0.06);
    color: var(--portal-heading, rgb(248 250 252));
}
.report-design-nav__tab--active {
    background: rgb(255 255 255 / 0.1);
    color: var(--portal-heading, rgb(248 250 252));
}
</style>
