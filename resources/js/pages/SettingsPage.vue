<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useTheme, type AppTheme } from '@/composables/useTheme';
import { usePermissions } from '@/composables/usePermissions';
import { useModuleAccess } from '@/composables/useModuleAccess';
import { useCompanyStore } from '@/stores/company';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import BillingSettingsForm from '@/components/settings/BillingSettingsForm.vue';
import MobileSecuritySettingsForm from '@/components/settings/MobileSecuritySettingsForm.vue';
import NotificationSettingsForm from '@/components/settings/NotificationSettingsForm.vue';

const { theme, setTheme } = useTheme();
const { can } = usePermissions();
const { canWriteModule } = useModuleAccess();
const company = useCompanyStore();

const canBillingSettings = computed(
    () => canWriteModule('billing') || can('billing.settings'),
);
const isAdmin = computed(() => company.current?.role === 'administrator');

const themeOptions: { id: AppTheme; title: string; description: string }[] = [
    {
        id: 'dark',
        title: 'Oscuro',
        description: 'Cristal industrial sobre fondo profundo, como el login.',
    },
    {
        id: 'light',
        title: 'Claro',
        description: 'Paneles claros para entornos muy iluminados.',
    },
];

function selectTheme(next: AppTheme) {
    setTheme(next);
}
</script>

<template>
    <div class="max-w-2xl space-y-8">
        <PageHeader
            title="Configuración"
            subtitle="Tema, notificaciones, facturación, app móvil y contenido del portal de acceso."
        />

        <section id="apariencia">
            <GlassCard padding="lg" class="space-y-4">
                <h2 class="text-portal-heading text-base font-semibold">Apariencia</h2>
                <p class="text-portal-muted text-sm">El tema se guarda en este navegador.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="opt in themeOptions"
                        :key="opt.id"
                        type="button"
                        class="theme-option text-left"
                        :class="{ 'theme-option--active': theme === opt.id }"
                        @click="selectTheme(opt.id)"
                    >
                        <div
                            class="theme-option__preview mb-3"
                            :class="
                                opt.id === 'dark' ? 'theme-option__preview--dark' : 'theme-option__preview--light'
                            "
                        />
                        <p class="text-portal-heading font-semibold">{{ opt.title }}</p>
                        <p class="text-portal-muted mt-1 text-xs leading-relaxed">{{ opt.description }}</p>
                    </button>
                </div>
            </GlassCard>
        </section>

        <section id="notificaciones">
            <GlassCard padding="lg" class="space-y-4">
                <h2 class="text-portal-heading text-base font-semibold">Notificaciones</h2>
                <p class="text-portal-muted text-sm">
                    Posición en pantalla, sonido por tipo y prueba en vivo. Se guarda en este navegador.
                </p>
                <NotificationSettingsForm />
            </GlassCard>
        </section>

        <section v-if="canBillingSettings" id="facturacion">
            <GlassCard padding="lg" class="space-y-4">
                <h2 class="text-portal-heading text-base font-semibold">Facturación</h2>
                <p class="text-portal-muted text-sm">
                    Parámetros de borradores (mano de obra, IVA) y timbrado fiscal PAC al emitir.
                </p>
                <BillingSettingsForm />
            </GlassCard>
        </section>

        <section v-if="isAdmin" id="app-movil">
            <GlassCard padding="lg" class="space-y-4">
                <h2 class="text-portal-heading text-base font-semibold">App móvil (campo)</h2>
                <p class="text-portal-muted text-sm">
                    Política de seguridad para técnicos en Phoenix Campo (PIN y biometría).
                </p>
                <MobileSecuritySettingsForm />
            </GlassCard>
        </section>

        <section v-if="isAdmin" id="portal-login">
            <GlassCard padding="lg" class="space-y-3">
                <h2 class="text-portal-heading text-base font-semibold">Portal de acceso</h2>
                <p class="text-portal-muted text-sm">
                    Textos de ayuda, contacto y mensajes públicos en la pantalla de login.
                </p>
                <RouterLink to="/app/admin/portal" class="text-portal-link inline-flex text-sm font-medium underline">
                    Editar contenido del login →
                </RouterLink>
            </GlassCard>
        </section>
    </div>
</template>

<style scoped>
.theme-option {
    border-radius: 1rem;
    border: 1px solid rgb(255 255 255 / 0.1);
    padding: 1rem;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.theme-option--active {
    border-color: rgb(245 158 11 / 0.45);
    box-shadow: 0 0 0 1px rgb(245 158 11 / 0.2);
}

.theme-option__preview {
    height: 4.5rem;
    border-radius: 0.65rem;
    border: 1px solid rgb(255 255 255 / 0.08);
}

.theme-option__preview--dark {
    background: linear-gradient(135deg, #0a0c12 0%, #1a1510 50%, #0f172a 100%);
}

.theme-option__preview--light {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
}

[data-theme='light'] .theme-option {
    border-color: rgb(15 23 42 / 0.12);
    background: rgb(255 255 255 / 0.8);
}

[data-theme='light'] .theme-option--active {
    border-color: rgb(245 158 11 / 0.55);
}
</style>
