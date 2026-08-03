<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { api } from '@/api/client';
import BacteriumNetwork from '@/components/BacteriumNetwork.vue';
import AppAtmosphere from '@/components/AppAtmosphere.vue';
import PhoenixBrand from '@/components/ui/PhoenixBrand.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';
import LoginFormPanel from '@/components/login/LoginFormPanel.vue';
import { useSystemEnterStore } from '@/stores/systemEnter';
import { postLoginRoute } from '@/lib/sessionCompany';

/** Tiempo mínimo del overlay de entrada (loader + animación). */
const LOGIN_ENTER_MIN_MS = 2600;

type PortalContent = {
    service_title?: string | null;
    service_description?: string | null;
    service_highlights?: string[];
    help_title?: string | null;
    help_text?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    contact_hours?: string | null;
};

const capabilities = [
    {
        num: '01',
        title: 'Rutinas y validación',
        text: 'Ejecución en campo, evidencias y aprobación de supervisores.',
    },
    {
        num: '02',
        title: 'Diseño configurable',
        text: 'Formularios, reportes PDF y workflows sin desplegar código.',
    },
    {
        num: '03',
        title: 'Powered by AI',
        text: 'AI Gateway con plantillas auditables y proveedor intercambiable.',
    },
];

const email = ref('admin@sandbox-demo.com');
const password = ref('pyro.2026$');
const passwordReadonly = ref(true);
const loading = ref(false);
const showNeuralBg = ref(false);
const portal = ref<PortalContent | null>(null);

const router = useRouter();
const auth = useAuthStore();
const company = useCompanyStore();

async function loadPortal() {
    try {
        const res = await api<{ data: PortalContent }>('/portal');
        portal.value = res.data;
    } catch {
        portal.value = null;
    }
}

const demoHint = ref<string | null>(null);

async function loadDemoHealth() {
    try {
        const res = await api<{
            demo?: { accounts_ready: boolean; password: string };
        }>('/health');
        if (res.demo && !res.demo.accounts_ready) {
            demoHint.value =
                'No hay cuentas demo. Reparando… vuelve a intentar en unos segundos o ejecuta: docker compose exec app php artisan phoenix:refresh-demo';
            window.setTimeout(() => void loadDemoHealth(), 2500);
        } else {
            demoHint.value = null;
        }
    } catch {
        demoHint.value = null;
    }
}

async function submit() {
    applyDemoCredentials();
    loading.value = true;
    auth.error = null;
    const systemEnter = useSystemEnterStore();
    systemEnter.show('Autenticando…');
    const enterStartedAt = Date.now();
    try {
        await auth.login(email.value.trim(), password.value);
        systemEnter.show('Entrando al sistema…');
        company.hydrate(auth.companies);
        await router.push(postLoginRoute(auth.companies));
        const remaining = LOGIN_ENTER_MIN_MS - (Date.now() - enterStartedAt);
        if (remaining > 0) {
            await new Promise((resolve) => window.setTimeout(resolve, remaining));
        }
    } finally {
        systemEnter.hide();
        loading.value = false;
    }
}

const DEMO_EMAIL = 'admin@sandbox-demo.com';
const LEGACY_DEMO_EMAIL = 'admin@pyro-systems.com';
const DEMO_PASSWORD = 'pyro.2026$';

function applyDemoCredentials() {
    if (
        email.value === ''
        || email.value === LEGACY_DEMO_EMAIL
        || email.value === DEMO_EMAIL
    ) {
        email.value = DEMO_EMAIL;
    }
    if (password.value === '' || password.value === 'password') {
        password.value = DEMO_PASSWORD;
    }
}

function onPasswordFocus() {
    passwordReadonly.value = false;
    applyDemoCredentials();
}

onMounted(() => {
    requestAnimationFrame(() => {
        showNeuralBg.value = true;
    });
    void loadPortal();
    void loadDemoHealth();
    applyDemoCredentials();
    window.setTimeout(applyDemoCredentials, 150);
    window.setTimeout(applyDemoCredentials, 600);
});
</script>

<template>
    <div class="login-shell relative min-h-dvh text-slate-100 lg:h-dvh lg:overflow-hidden">

        <BacteriumNetwork v-if="showNeuralBg && !loading" subdued warm />

        <AppAtmosphere />

        <div class="relative z-10 grid min-h-dvh w-full lg:h-full lg:min-h-0 lg:grid-cols-[minmax(340px,40%)_1fr]">
            <!-- Columna login: pegada a la izquierda -->
            <section
                class="flex items-center justify-start px-6 py-12 sm:px-10 lg:py-0 lg:pl-10 xl:pl-16 2xl:pl-24"
            >
                <LoginFormPanel class="w-full max-w-[22rem] sm:max-w-sm">
                    <div class="login-reveal mb-7">
                        <PhoenixBrand size="lg" :show-wordmark="true" variant="sidebar" animated />
                        <p class="mt-3 text-sm leading-relaxed text-slate-400">
                            Acceso seguro a operaciones industriales
                        </p>
                    </div>

                    <form class="login-reveal space-y-5" autocomplete="off" @submit.prevent="submit">
                        <MaterialField
                            v-model="email"
                            label="Correo electrónico"
                            type="email"
                            name="phoenix-email"
                            required
                            autocomplete="username"
                        />
                        <MaterialField
                            v-model="password"
                            label="Contraseña"
                            type="password"
                            name="phoenix-password"
                            required
                            placeholder="pyro.2026$"
                            autocomplete="off"
                            :readonly="passwordReadonly"
                            @focus="onPasswordFocus"
                        />
                        <Transition name="login-fade">
                            <div v-if="demoHint || auth.error" class="space-y-2">
                                <p v-if="demoHint" class="text-sm text-amber-400">{{ demoHint }}</p>
                                <p v-if="auth.error" class="text-sm text-red-400">
                                    {{ auth.error }}
                                    <span class="mt-1 block text-xs text-slate-500">
                                        Demo local:
                                        <span class="font-mono">admin@sandbox-demo.com</span>
                                        /
                                        <span class="font-mono">pyro.2026$</span>
                                        — si falla:
                                        <span class="font-mono">docker compose exec app php artisan phoenix:refresh-demo</span>
                                    </span>
                                </p>
                            </div>
                        </Transition>
                        <AppButton
                            type="submit"
                            variant="primary"
                            class="login-cta w-full py-3 font-semibold"
                            :disabled="loading"
                        >
                            {{ loading ? 'Entrando…' : 'Iniciar sesión' }}
                        </AppButton>
                        <p class="text-center text-xs text-slate-500">
                            Demo local (Sandbox):
                            <span class="font-mono text-slate-400">admin@sandbox-demo.com</span>
                            /
                            <span class="font-mono text-slate-400">pyro.2026$</span>
                        </p>
                    </form>

                    <div class="login-reveal mt-7 border-t border-white/10 pt-6 text-sm leading-relaxed text-slate-400">
                        <p class="font-medium text-amber-400/95">{{ portal?.help_title ?? 'Ayuda' }}</p>
                        <p class="mt-2">{{ portal?.help_text }}</p>
                    </div>

                    <div class="login-reveal mt-6 border-t border-white/10 pt-5 text-sm lg:hidden">
                        <p class="font-medium text-slate-200">Contacto</p>
                        <p class="mt-2 text-slate-300">
                            <a
                                v-if="portal?.contact_email"
                                class="text-amber-400/95 hover:underline"
                                :href="`mailto:${portal.contact_email}`"
                            >
                                {{ portal.contact_email }}
                            </a>
                            <span v-else>soporte@pyro-systems.com</span>
                        </p>
                        <p class="mt-1 text-slate-400">{{ portal?.contact_phone ?? '+52 55 0000 0000' }}</p>
                        <p class="mt-1 text-slate-500">
                            {{ portal?.contact_hours ?? 'Lun–Vie 8:00–18:00 (hora Ciudad de México)' }}
                        </p>
                    </div>
                </LoginFormPanel>
            </section>

            <!-- Columna narrativa: anclada arriba-izquierda del espacio libre -->
            <section
                class="login-stagger hidden flex-col justify-between px-8 pb-12 pt-14 lg:flex xl:px-14 xl:pb-14 xl:pt-16 2xl:pr-24"
            >
                <div class="login-reveal max-w-3xl">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shadow-[0_0_8px_#fbbf24]" />
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-200/90">
                            Plataforma Phoenix
                        </span>
                    </div>
                    <h1 class="max-w-2xl text-4xl font-extrabold leading-[1.12] tracking-tight xl:text-5xl 2xl:text-[3.25rem]">
                        <span class="login-headline-accent">
                            {{ portal?.service_title ?? 'Gestión técnica clara para operaciones industriales' }}
                        </span>
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-300/95 xl:text-lg">
                        {{
                            portal?.service_description ??
                            'Rutinas, validación, evidencias y facturación en una sola plataforma.'
                        }}
                    </p>
                    <ul
                        v-if="portal?.service_highlights?.length"
                        class="mt-6 flex flex-wrap gap-2"
                    >
                        <li
                            v-for="(item, i) in portal.service_highlights"
                            :key="i"
                            class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-medium text-slate-300 backdrop-blur-sm"
                        >
                            {{ item }}
                        </li>
                    </ul>
                </div>

                <div class="login-reveal mt-10 grid max-w-4xl gap-4 sm:grid-cols-3">
                    <article
                        v-for="cap in capabilities"
                        :key="cap.num"
                        class="login-cap-card rounded-2xl p-5 backdrop-blur-sm"
                    >
                        <p class="font-mono text-xs font-semibold text-amber-500/80">{{ cap.num }}</p>
                        <h2 class="mt-2 text-sm font-semibold text-white">{{ cap.title }}</h2>
                        <p class="mt-2 text-xs leading-relaxed text-slate-400">{{ cap.text }}</p>
                    </article>
                </div>

                <footer class="login-reveal mt-10 max-w-4xl border-t border-white/10 pt-6">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Contacto</p>
                    <div
                        class="mt-3 flex flex-col gap-3 text-sm text-slate-300 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-8 sm:gap-y-2"
                    >
                        <a
                            v-if="portal?.contact_email"
                            class="text-amber-400/95 transition hover:text-amber-300"
                            :href="`mailto:${portal.contact_email}`"
                        >
                            {{ portal.contact_email }}
                        </a>
                        <span v-else class="text-slate-300">soporte@pyro-systems.com</span>
                        <span class="hidden text-slate-600 sm:inline" aria-hidden="true">·</span>
                        <span>{{ portal?.contact_phone ?? '+52 55 0000 0000' }}</span>
                        <span class="hidden text-slate-600 sm:inline" aria-hidden="true">·</span>
                        <span class="text-slate-500">
                            {{ portal?.contact_hours ?? 'Lun–Vie 8:00–18:00 (hora Ciudad de México)' }}
                        </span>
                    </div>
                </footer>
            </section>
        </div>
    </div>
</template>

<style scoped>
.login-fade-enter-active,
.login-fade-leave-active {
    transition: opacity 0.2s ease;
}

.login-fade-enter-from,
.login-fade-leave-to {
    opacity: 0;
}
</style>
