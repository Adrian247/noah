<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';

type PortalPayload = {
    hero_image_url: string | null;
    hero_image_alt: string | null;
    service_title: string | null;
    service_description: string | null;
    service_highlights: string[];
    help_title: string | null;
    help_text: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_hours: string | null;
};

const form = ref<PortalPayload>({
    hero_image_url: '',
    hero_image_alt: '',
    service_title: '',
    service_description: '',
    service_highlights: ['', '', ''],
    help_title: '',
    help_text: '',
    contact_email: '',
    contact_phone: '',
    contact_hours: '',
});

const loading = ref(true);
const saving = ref(false);
const message = ref<string | null>(null);
const error = ref<string | null>(null);

const highlightsText = computed({
    get: () => form.value.service_highlights.filter(Boolean).join('\n'),
    set: (value: string) => {
        form.value.service_highlights = value
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean);
    },
});

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await api<{ data: PortalPayload }>('/portal/settings');
        form.value = {
            ...res.data,
            service_highlights:
                res.data.service_highlights?.length > 0
                    ? [...res.data.service_highlights]
                    : ['', '', ''],
        };
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function save() {
    message.value = null;
    error.value = null;
    saving.value = true;
    try {
        await api('/portal/settings', {
            method: 'PUT',
            body: JSON.stringify({
                ...form.value,
                service_highlights: form.value.service_highlights.filter(Boolean),
            }),
        });
        message.value = 'Contenido del portal de login actualizado.';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-3xl">
        <RouterLink to="/app/dashboard" class="text-sm text-primary-700 underline">← Panel</RouterLink>
        <PageHeader
            title="Portal de login"
            subtitle="Textos de ayuda, contacto, servicio e imagen del panel derecho en la pantalla de acceso (visible sin autenticación)."
        />
        <AlertBanner variant="info" class="mb-4">
            Los cambios se reflejan de inmediato en la pantalla de inicio de sesión. Usa una URL de imagen HTTPS (p. ej. almacenamiento propio o CDN).
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">Cargando…</GlassCard>
        <GlassCard v-else padding="lg" class="space-y-6">
            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-slate-800">Imagen industrial (panel derecho)</legend>
                <label class="block text-sm">
                    URL de imagen
                    <input v-model="form.hero_image_url" type="url" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Texto alternativo
                    <input v-model="form.hero_image_alt" type="text" class="field-input mt-1 w-full" />
                </label>
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-slate-800">Servicio</legend>
                <label class="block text-sm">
                    Título
                    <input v-model="form.service_title" type="text" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Descripción
                    <textarea v-model="form.service_description" rows="4" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Destacados (uno por línea)
                    <textarea v-model="highlightsText" rows="4" class="field-input mt-1 w-full font-mono text-xs" />
                </label>
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-semibold text-slate-800">Ayuda y contacto (columna login)</legend>
                <label class="block text-sm">
                    Título de ayuda
                    <input v-model="form.help_title" type="text" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Texto de ayuda
                    <textarea v-model="form.help_text" rows="3" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Correo
                    <input v-model="form.contact_email" type="email" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Teléfono
                    <input v-model="form.contact_phone" type="text" class="field-input mt-1 w-full" />
                </label>
                <label class="block text-sm">
                    Horario
                    <input v-model="form.contact_hours" type="text" class="field-input mt-1 w-full" />
                </label>
            </fieldset>

            <AppButton :disabled="saving" @click="save">
                {{ saving ? 'Guardando…' : 'Guardar portal' }}
            </AppButton>
            <p v-if="message" class="text-sm text-emerald-800">{{ message }}</p>
            <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        </GlassCard>
    </div>
</template>
