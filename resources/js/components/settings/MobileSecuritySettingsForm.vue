<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';

type Settings = {
    mobile_require_app_lock: boolean;
    mobile_allow_biometric_unlock: boolean;
};

const toast = useToast();
const requireAppLock = ref(false);
const allowBiometricUnlock = ref(true);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Settings }>('/mobile/settings');
        requireAppLock.value = res.data.mobile_require_app_lock;
        allowBiometricUnlock.value = res.data.mobile_allow_biometric_unlock;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    try {
        await api('/mobile/settings', {
            method: 'PUT',
            body: JSON.stringify({
                mobile_require_app_lock: requireAppLock.value,
                mobile_allow_biometric_unlock: allowBiometricUnlock.value,
            }),
        });
        toast.success('Política móvil guardada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);

defineExpose({ load });
</script>

<template>
    <div v-if="loading" class="text-portal-muted text-sm">Cargando política móvil…</div>
    <div v-else class="space-y-4">
        <label class="flex cursor-pointer items-start gap-3">
            <input v-model="requireAppLock" type="checkbox" class="mt-1" />
            <span>
                <span class="text-portal-heading block font-medium">Exigir bloqueo con PIN</span>
                <span class="text-portal-muted text-xs">
                    Los técnicos deben configurar PIN en Phoenix Campo antes de usar la app.
                </span>
            </span>
        </label>
        <label class="flex cursor-pointer items-start gap-3">
            <input
                v-model="allowBiometricUnlock"
                type="checkbox"
                class="mt-1"
                :disabled="!requireAppLock"
            />
            <span>
                <span class="text-portal-heading block font-medium">Permitir desbloqueo biométrico</span>
                <span class="text-portal-muted text-xs">
                    Huella o rostro además del PIN. Solo aplica si el bloqueo con PIN está activo.
                </span>
            </span>
        </label>
        <AppButton @click="save">Guardar política móvil</AppButton>
    </div>
</template>
