<script setup lang="ts">
/** Formulario de login: FX tipo colapso de menú, recortada dentro del panel. */
</script>

<template>
    <div class="login-form-panel login-glass-premium login-form-panel--enter">
        <div
            class="login-form-panel__fx sidebar-collapse-fx sidebar-collapse-fx--collapse"
            aria-hidden="true"
        >
            <div class="sidebar-collapse-fx__beam" />
            <div class="sidebar-collapse-fx__glow login-form-panel__glow" />
            <div class="sidebar-collapse-fx__scan" />
        </div>
        <div class="login-form-panel__body login-stagger">
            <slot />
        </div>
    </div>
</template>
