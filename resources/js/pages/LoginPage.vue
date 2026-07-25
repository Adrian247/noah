<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';
import GlassCard from '@/components/ui/GlassCard.vue';
import AppButton from '@/components/ui/AppButton.vue';
import BacteriumNetwork from '@/components/BacteriumNetwork.vue';
import NoahBrand from '@/components/ui/NoahBrand.vue';

const email = ref('admin@noah.local');
const password = ref('password');
const loading = ref(false);
const router = useRouter();
const auth = useAuthStore();
const company = useCompanyStore();

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
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-200">
        <BacteriumNetwork subdued />
        <div class="relative z-10 flex min-h-screen items-center justify-center p-6">
            <GlassCard class="noah-login-panel w-full max-w-md backdrop-blur-sm" padding="lg">
                <div class="mb-6 flex flex-col items-center gap-3">
                    <NoahBrand size="lg" show-wordmark variant="light" />
                    <p class="text-sm text-slate-600">Plataforma de mantenimiento y reportes</p>
                </div>
                <form class="space-y-4" @submit.prevent="submit">
                    <label class="block text-sm font-medium text-slate-700">
                        Correo
                        <input
                            v-model="email"
                            type="email"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                        />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Contraseña
                        <input
                            v-model="password"
                            type="password"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                        />
                    </label>
                    <Transition name="login-fade">
                        <p v-if="auth.error" class="text-sm text-red-600">{{ auth.error }}</p>
                    </Transition>
                    <AppButton type="submit" class="w-full" :disabled="loading">
                        {{ loading ? 'Entrando…' : 'Iniciar sesión' }}
                    </AppButton>
                </form>
                <p class="mt-6 text-center text-xs text-slate-500">Demo: admin@noah.local / password</p>
            </GlassCard>
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
