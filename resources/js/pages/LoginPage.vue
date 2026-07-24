<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCompanyStore } from '@/stores/company';

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
    <div class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
        <form
            class="w-full max-w-sm space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <h1 class="text-xl font-semibold">Noah</h1>
                <p class="text-sm text-slate-500">Iniciar sesión</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="email">Correo</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password">Contraseña</label>
                <input
                    id="password"
                    v-model="password"
                    type="password"
                    required
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
            </div>
            <p v-if="auth.error" class="text-sm text-red-600">{{ auth.error }}</p>
            <button
                type="submit"
                class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                :disabled="loading"
            >
                {{ loading ? 'Entrando…' : 'Entrar' }}
            </button>
            <p class="text-xs text-slate-400">Demo: admin@noah.local / password</p>
        </form>
    </div>
</template>
