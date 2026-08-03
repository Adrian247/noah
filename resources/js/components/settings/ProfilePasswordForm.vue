<script setup lang="ts">
import { ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

const toast = useToast();
const currentPassword = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const saving = ref(false);

async function save() {
    if (password.value !== passwordConfirmation.value) {
        toast.warning('La confirmación no coincide con la nueva contraseña.');
        return;
    }
    saving.value = true;
    try {
        await api('/auth/password', {
            method: 'PUT',
            body: JSON.stringify({
                current_password: currentPassword.value,
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            }),
        });
        currentPassword.value = '';
        password.value = '';
        passwordConfirmation.value = '';
        toast.success('Contraseña actualizada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="save">
        <MaterialField
            v-model="currentPassword"
            label="Contraseña actual"
            type="password"
            autocomplete="current-password"
            required
        />
        <MaterialField
            v-model="password"
            label="Nueva contraseña"
            type="password"
            autocomplete="new-password"
            required
        />
        <MaterialField
            v-model="passwordConfirmation"
            label="Confirmar nueva contraseña"
            type="password"
            autocomplete="new-password"
            required
        />
        <p class="text-portal-muted text-xs">Mínimo 8 caracteres.</p>
        <AppButton type="submit" :disabled="saving">
            {{ saving ? 'Guardando…' : 'Cambiar contraseña' }}
        </AppButton>
    </form>
</template>
