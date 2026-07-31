<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { isPortalClientMembership } from '@/lib/sessionCompany';
import { applyStoredThemeForApp } from '@/lib/theme';
import { useTheme } from '@/composables/useTheme';
import { usePortalPublicContent } from '@/composables/usePortalPublicContent';
import { clientPortalSessionFooter } from '@/lib/clientPortalFooter';
import AppAtmosphere from '@/components/AppAtmosphere.vue';
import PortalLightFrost from '@/components/PortalLightFrost.vue';
import PhoenixBrand from '@/components/ui/PhoenixBrand.vue';
import NavIcon from '@/components/ui/NavIcon.vue';
import UserAvatar from '@/components/ui/UserAvatar.vue';

const auth = useAuthStore();
const company = useCompanyStore();
const router = useRouter();
const route = useRoute();
const loading = ref(true);
const { isDark, toggleTheme } = useTheme();
const { content: portalContent, ensureLoaded } = usePortalPublicContent();

onMounted(async () => {
    applyStoredThemeForApp();
    try {
        const ok = await auth.ensureSession();
        if (!ok) {
            await router.replace({ name: 'login' });
            return;
        }
        company.hydrate(auth.companies);
        await ensureLoaded();
    } finally {
        loading.value = false;
    }
});

const companyName = computed(
    () => company.current?.name ?? auth.companies.find((c) => isPortalClientMembership(c))?.name ?? 'Tu proveedor',
);

const userName = computed(() => auth.user?.name ?? 'Cliente');
const userEmail = computed(() => auth.user?.email ?? '');

const navItems = [
    { to: '/portal/invoices', name: 'portal-invoices', label: 'Facturas', icon: 'receipt' as const },
    { to: '/portal/routines', name: 'portal-routines', label: 'Servicios', icon: 'clipboard-list' as const },
];

function navActive(name: string): boolean {
    return route.name === name || (name === 'portal-routines' && route.name === 'portal-routine-detail');
}

const sessionFooter = computed(() => clientPortalSessionFooter(portalContent.value));

const hasContactInfo = computed(() => {
    const c = portalContent.value;
    return Boolean(c?.contact_email?.trim() || c?.contact_phone?.trim() || c?.contact_hours?.trim());
});

async function logout() {
    loading.value = true;
    try {
        await auth.logout();
        company.clear();
    } finally {
        await router.replace({ name: 'login' });
        loading.value = false;
    }
}
</script>

<template>
    <div class="client-portal-shell" :class="{ 'client-portal-shell--dark': isDark }">
        <AppAtmosphere v-if="isDark" class="client-portal-shell__atmosphere" />
        <PortalLightFrost v-else />

        <div class="client-portal-shell__frame">
            <header class="client-portal-header">
                <div class="client-portal-header__inner">
                    <div class="client-portal-header__brand">
                        <PhoenixBrand
                            size="sm"
                            show-wordmark
                            :variant="isDark ? 'sidebar' : 'light'"
                        />
                        <div class="client-portal-header__tenant">
                            <p class="client-portal-header__eyebrow">Portal de cliente</p>
                            <p class="client-portal-header__company">{{ companyName }}</p>
                        </div>
                    </div>

                    <nav class="client-portal-nav" aria-label="Secciones del portal">
                        <RouterLink
                            v-for="item in navItems"
                            :key="item.to"
                            :to="item.to"
                            class="client-portal-nav__link"
                            :class="{ 'client-portal-nav__link--active': navActive(item.name) }"
                        >
                            <NavIcon :name="item.icon" class="h-4 w-4 shrink-0 opacity-90" />
                            {{ item.label }}
                        </RouterLink>
                    </nav>

                    <div class="client-portal-header__session">
                        <button
                            type="button"
                            class="client-portal-header__theme"
                            :title="isDark ? 'Tema claro' : 'Tema oscuro'"
                            @click="toggleTheme"
                        >
                            {{ isDark ? '☀' : '☾' }}
                        </button>
                        <div class="client-portal-header__user">
                            <UserAvatar :name="userName" :avatar-url="auth.user?.avatar_url" size="sm" />
                            <div class="client-portal-header__user-text">
                                <p class="client-portal-header__user-name">{{ userName }}</p>
                                <p class="client-portal-header__user-email" :title="userEmail">{{ userEmail }}</p>
                            </div>
                        </div>
                        <button type="button" class="client-portal-header__logout" @click="logout">Salir</button>
                    </div>
                </div>
            </header>

            <main class="client-portal-main">
                <p v-if="loading" class="text-portal-muted client-portal-main__loading">Preparando tu espacio…</p>
                <RouterView v-else v-slot="{ Component, route: viewRoute }">
                    <Transition name="phoenix-page" mode="out-in">
                        <component :is="Component" :key="viewRoute.path" />
                    </Transition>
                </RouterView>
            </main>

            <footer v-if="portalContent && !loading" class="client-portal-footer">
                <div class="client-portal-footer__inner">
                    <div class="client-portal-footer__copy">
                        <p class="client-portal-footer__title">{{ sessionFooter.title }}</p>
                        <p v-if="sessionFooter.showDescription && sessionFooter.description" class="client-portal-footer__text">
                            {{ sessionFooter.description }}
                        </p>
                    </div>
                    <div v-if="hasContactInfo" class="client-portal-footer__contact">
                        <a
                            v-if="portalContent.contact_email"
                            class="client-portal-footer__link"
                            :href="`mailto:${portalContent.contact_email}`"
                            :title="portalContent.contact_email"
                        >
                            {{ portalContent.contact_email }}
                        </a>
                        <span v-if="portalContent.contact_phone">{{ portalContent.contact_phone }}</span>
                        <span v-if="portalContent.contact_hours" class="client-portal-footer__hours">
                            {{ portalContent.contact_hours }}
                        </span>
                    </div>
                </div>
                <p class="client-portal-footer__trace">
                    Acceso seguro · Solo documentos de tu organización · Descargas registradas
                </p>
            </footer>
        </div>

    </div>
</template>
