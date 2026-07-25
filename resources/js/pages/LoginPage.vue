<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import { api } from '@/api/client';
import BacteriumNetwork from '@/components/BacteriumNetwork.vue';
import NoahBrand from '@/components/ui/NoahBrand.vue';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

type PortalContent = {
    hero_image_url?: string | null;
    hero_image_alt?: string | null;
    service_title?: string | null;
    service_description?: string | null;
    service_highlights?: string[];
    help_title?: string | null;
    help_text?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    contact_hours?: string | null;
};

const email = ref('admin@noah.local');
const password = ref('password');
const loading = ref(false);
const portal = ref<PortalContent | null>(null);
const parallaxX = ref(0);
const parallaxY = ref(0);

const router = useRouter();
const auth = useAuthStore();
const company = useCompanyStore();

const parallaxStyle = computed(() => ({
    transform: `translate3d(${parallaxX.value * 0.02}px, ${parallaxY.value * 0.02}px, 0) scale(1.08)`,
}));

const serviceLayerStyle = computed(() => ({
    transform: `translate3d(${parallaxX.value * -0.01}px, ${parallaxY.value * -0.01}px, 0)`,
}));

function onPointerMove(event: PointerEvent) {
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;
    parallaxX.value = event.clientX - cx;
    parallaxY.value = event.clientY - cy;
}

async function loadPortal() {
    try {
        const res = await api<{ data: PortalContent }>('/portal');
        portal.value = res.data;
    } catch {
        portal.value = null;
    }
}

async function submit() {
    loading.value = true;
    try {
        await auth.login(email.value, password.value);
        company.hydrate(auth.companies);
        await router.push('/app/dashboard');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void loadPortal();
    window.addEventListener('pointermove', onPointerMove);
});

onUnmounted(() => {
    window.removeEventListener('pointermove', onPointerMove);
});
</script>

<template>
    <div class="login-dashboard relative min-h-screen overflow-hidden bg-slate-950 text-slate-100">
        <BacteriumNetwork subdued />
        <div class="login-dashboard__grid relative z-10 min-h-screen lg:grid lg:grid-cols-2">
            <section class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-14 xl:px-20">
                <div class="login-glass-panel mx-auto w-full max-w-md p-8 sm:p-10">
                    <div class="mb-8 flex flex-col gap-2">
                        <NoahBrand size="lg" :show-wordmark="true" variant="sidebar" />
                        <p class="text-sm text-slate-400">Acceso seguro a operaciones industriales</p>
                    </div>

                    <form class="space-y-6" @submit.prevent="submit">
                        <MaterialField
                            v-model="email"
                            label="Correo electrónico"
                            type="email"
                            required
                            autocomplete="username"
                        />
                        <MaterialField
                            v-model="password"
                            label="Contraseña"
                            type="password"
                            required
                            autocomplete="current-password"
                        />
                        <Transition name="login-fade">
                            <p v-if="auth.error" class="text-sm text-red-400">{{ auth.error }}</p>
                        </Transition>
                        <AppButton type="submit" class="w-full !bg-amber-500 !text-slate-950 hover:!bg-amber-400" :disabled="loading">
                            {{ loading ? 'Entrando…' : 'Iniciar sesión' }}
                        </AppButton>
                    </form>

                    <div class="mt-8 space-y-4 border-t border-white/10 pt-6 text-sm">
                        <div>
                            <h3 class="font-semibold text-amber-400/90">
                                {{ portal?.help_title ?? 'Ayuda' }}
                            </h3>
                            <p class="mt-1 text-slate-400">
                                {{ portal?.help_text }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-200">Contacto</h3>
                            <ul class="mt-2 space-y-1 text-slate-400">
                                <li v-if="portal?.contact_email">
                                    <a
                                        class="text-amber-400/90 hover:text-amber-300"
                                        :href="`mailto:${portal.contact_email}`"
                                    >
                                        {{ portal.contact_email }}
                                    </a>
                                </li>
                                <li v-if="portal?.contact_phone">{{ portal.contact_phone }}</li>
                                <li v-if="portal?.contact_hours">{{ portal.contact_hours }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="relative hidden min-h-[28rem] overflow-hidden lg:block">
                <div
                    class="login-hero-layer absolute inset-0 will-change-transform"
                    :style="parallaxStyle"
                >
                    <img
                        v-if="portal?.hero_image_url"
                        :src="portal.hero_image_url"
                        :alt="portal.hero_image_alt ?? ''"
                        class="h-full w-full object-cover object-center opacity-40"
                    />
                    <div class="absolute inset-0 bg-gradient-to-l from-slate-950 via-slate-950/70 to-transparent" />
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-slate-900/40" />
                </div>

                <div
                    class="login-service-panel absolute bottom-10 left-10 right-10 will-change-transform"
                    :style="serviceLayerStyle"
                >
                    <div class="login-glass-panel-dark p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-500/80">
                            Servicio Noah
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-white">
                            {{ portal?.service_title }}
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300">
                            {{ portal?.service_description }}
                        </p>
                        <ul v-if="portal?.service_highlights?.length" class="mt-4 space-y-2 text-sm text-slate-400">
                            <li
                                v-for="(item, i) in portal.service_highlights"
                                :key="i"
                                class="flex gap-2"
                            >
                                <span class="text-amber-500">▸</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>

        <div class="login-glass-panel-dark relative z-10 mx-4 mb-8 p-6 lg:hidden">
            <h2 class="text-lg font-semibold text-white">{{ portal?.service_title }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ portal?.service_description }}</p>
        </div>
    </div>
</template>

<style scoped>
.login-fade-enter-active,
.login-fade-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}

.login-fade-enter-from,
.login-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
