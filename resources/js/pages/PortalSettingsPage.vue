<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

type PortalPayload = {
    service_title: string | null;
    service_description: string | null;
    service_highlights: string[];
    help_title: string | null;
    help_text: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_hours: string | null;
};

const toast = useToast();
const form = ref<PortalPayload>({
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
    try {
        const res = await api<{ data: PortalPayload }>('/portal/settings');
        form.value = {
            service_title: res.data.service_title ?? '',
            service_description: res.data.service_description ?? '',
            service_highlights:
                res.data.service_highlights?.length > 0
                    ? [...res.data.service_highlights]
                    : ['', '', ''],
            help_title: res.data.help_title ?? '',
            help_text: res.data.help_text ?? '',
            contact_email: res.data.contact_email ?? '',
            contact_phone: res.data.contact_phone ?? '',
            contact_hours: res.data.contact_hours ?? '',
        };
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        await api('/portal/settings', {
            method: 'PUT',
            body: JSON.stringify({
                ...form.value,
                service_highlights: form.value.service_highlights.filter(Boolean),
            }),
        });
        toast.success('Contenido del portal de login actualizado.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-3xl">
        <RouterLink to="/app/settings#portal-login" class="text-portal-link text-sm underline">← Ajustes</RouterLink>
        <PageHeader
            title="Portal de login"
            subtitle="Textos de ayuda, contacto y servicio en la pantalla de acceso (visible sin autenticación)."
        />
        <AlertBanner variant="info" class="mb-4">
            Los cambios se reflejan de inmediato en la pantalla de inicio de sesión. El fondo visual del login es
            propio de Phoenix (sin URL externa de imagen).
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">Cargando…</GlassCard>
        <GlassCard v-else padding="lg" class="space-y-8">
            <fieldset class="space-y-4">
                <legend class="text-portal-heading text-sm font-semibold">Servicio</legend>
                <MaterialField v-model="form.service_title" label="Título" />
                <MaterialField v-model="form.service_description" label="Descripción" multiline :rows="4" />
                <MaterialField v-model="highlightsText" label="Destacados (uno por línea)" multiline :rows="4" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-portal-heading text-sm font-semibold">Ayuda y contacto (columna login)</legend>
                <MaterialField v-model="form.help_title" label="Título de ayuda" />
                <MaterialField v-model="form.help_text" label="Texto de ayuda" multiline :rows="3" />
                <MaterialField v-model="form.contact_email" label="Correo" type="email" />
                <MaterialField v-model="form.contact_phone" label="Teléfono" />
                <MaterialField v-model="form.contact_hours" label="Horario" />
            </fieldset>

            <AppButton :disabled="saving" @click="save">
                {{ saving ? 'Guardando…' : 'Guardar portal' }}
            </AppButton>
        </GlassCard>
    </div>
</template>
