<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import AppButton from '@/components/ui/AppButton.vue';
import MaterialSelect from '@/components/ui/MaterialSelect.vue';

type Provider = 'google' | 'openai' | 'local';
type ProviderAvailability = { configured: boolean };
type ModelOption = { id: string; label: string };

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const validating = ref(false);
const provider = ref<Provider>('local');
const googleModel = ref('');
const openaiModel = ref('');
const googleUseDefault = ref(true);
const openaiUseDefault = ref(true);
const defaultGoogleModel = ref('gemini-2.0-flash');
const defaultOpenaiModel = ref('gpt-4o-mini');
const providersAvailable = ref<Record<string, ProviderAvailability>>({});
const models = ref<ModelOption[]>([]);
const validationMessage = ref('');
const validationOk = ref<boolean | null>(null);

const providerOptions = [
    { value: 'local', label: 'Local (sin LLM, tools verificadas)' },
    { value: 'google', label: 'Google Gemini' },
    { value: 'openai', label: 'OpenAI' },
];

const activeUseDefault = computed({
    get: () => (provider.value === 'openai' ? openaiUseDefault.value : googleUseDefault.value),
    set: (value: boolean) => {
        if (provider.value === 'openai') {
            openaiUseDefault.value = value;
        } else {
            googleUseDefault.value = value;
        }
    },
});

const activeModel = computed({
    get: () => (provider.value === 'openai' ? openaiModel.value : googleModel.value),
    set: (value: string) => {
        if (provider.value === 'openai') {
            openaiModel.value = value;
        } else {
            googleModel.value = value;
        }
    },
});

const activeDefaultModel = computed(() =>
    provider.value === 'openai' ? defaultOpenaiModel.value : defaultGoogleModel.value,
);

const modelSelectOptions = computed(() =>
    models.value.map((m) => ({ value: m.id, label: m.label })),
);

const showModelPicker = computed(() => provider.value !== 'local');

async function loadSettings() {
    loading.value = true;
    try {
        const res = await api<{
            data: {
                provider: Provider;
                google_model: string;
                openai_model: string;
                google_use_default: boolean;
                openai_use_default: boolean;
                default_google_model: string;
                default_openai_model: string;
                providers_available: Record<string, ProviderAvailability>;
            };
        }>('/platform/ai/settings');
        provider.value = res.data.provider;
        googleModel.value = res.data.google_model;
        openaiModel.value = res.data.openai_model;
        googleUseDefault.value = res.data.google_use_default;
        openaiUseDefault.value = res.data.openai_use_default;
        defaultGoogleModel.value = res.data.default_google_model;
        defaultOpenaiModel.value = res.data.default_openai_model;
        providersAvailable.value = res.data.providers_available;
        await validateAndLoadModels(false);
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function validateAndLoadModels(showToastOnError = true) {
    validating.value = true;
    validationOk.value = null;
    validationMessage.value = '';
    models.value = [];

    try {
        const res = await api<{
            data: {
                ok: boolean;
                configured: boolean;
                message: string;
                default_model: string;
                models: ModelOption[];
            };
        }>(`/platform/ai/models?provider=${encodeURIComponent(provider.value)}`);

        validationOk.value = res.data.ok;
        validationMessage.value = res.data.message;
        models.value = res.data.models ?? [];

        if (provider.value === 'google') {
            defaultGoogleModel.value = res.data.default_model || defaultGoogleModel.value;
            if (!googleUseDefault.value) {
                const exists = models.value.some((m) => m.id === googleModel.value);
                if (!exists && models.value[0]) {
                    googleModel.value = models.value[0].id;
                }
            }
        }
        if (provider.value === 'openai') {
            defaultOpenaiModel.value = res.data.default_model || defaultOpenaiModel.value;
            if (!openaiUseDefault.value) {
                const exists = models.value.some((m) => m.id === openaiModel.value);
                if (!exists && models.value[0]) {
                    openaiModel.value = models.value[0].id;
                }
            }
        }
    } catch (e) {
        validationOk.value = false;
        validationMessage.value = (e as Error).message;
        if (showToastOnError) {
            toast.error(validationMessage.value);
        }
    } finally {
        validating.value = false;
    }
}

async function save() {
    if (showModelPicker.value && validationOk.value === false) {
        toast.error('Valida el proveedor antes de guardar.');
        return;
    }

    saving.value = true;
    try {
        const res = await api<{
            data: {
                provider: Provider;
                google_model: string;
                openai_model: string;
                google_use_default: boolean;
                openai_use_default: boolean;
                providers_available: Record<string, ProviderAvailability>;
            };
        }>('/platform/ai/settings', {
            method: 'PUT',
            body: JSON.stringify({
                provider: provider.value,
                google_model: googleModel.value,
                openai_model: openaiModel.value,
                google_use_default: googleUseDefault.value,
                openai_use_default: openaiUseDefault.value,
            }),
        });
        provider.value = res.data.provider;
        googleModel.value = res.data.google_model;
        openaiModel.value = res.data.openai_model;
        googleUseDefault.value = res.data.google_use_default;
        openaiUseDefault.value = res.data.openai_use_default;
        providersAvailable.value = res.data.providers_available;
        toast.success('Configuración de IA guardada.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        saving.value = false;
    }
}

watch(provider, () => {
    void validateAndLoadModels();
});

onMounted(loadSettings);
</script>

<template>
    <div class="space-y-4">
        <p class="text-portal-muted text-sm">
            Solo administrador de plataforma. Las API keys viven en el servidor
            (<code>GEMINI_API_KEY</code> / <code>GOOGLE_API_KEY</code>, <code>OPENAI_API_KEY</code>).
            Al elegir proveedor se valida la conexión y se cargan los modelos disponibles.
        </p>

        <div v-if="loading" class="text-portal-muted text-sm">Cargando…</div>
        <div v-else class="space-y-4">
            <MaterialSelect v-model="provider" label="Proveedor del agente" :options="providerOptions" />

            <p class="text-portal-muted text-xs">
                Google:
                {{ providersAvailable.google?.configured ? 'API key presente' : 'sin API key' }}
                · OpenAI:
                {{ providersAvailable.openai?.configured ? 'API key presente' : 'sin API key' }}
            </p>

            <div
                class="phoenix-ai-validation rounded-xl border px-3 py-2 text-xs"
                :class="{
                    'phoenix-ai-validation--ok': validationOk === true,
                    'phoenix-ai-validation--err': validationOk === false,
                }"
            >
                <span v-if="validating">Validando proveedor y cargando modelos…</span>
                <span v-else>{{ validationMessage || 'Selecciona un proveedor para validar.' }}</span>
            </div>

            <template v-if="showModelPicker">
                <label class="flex items-center gap-2 text-sm text-portal-heading">
                    <input
                        v-model="activeUseDefault"
                        type="checkbox"
                        class="phoenix-checkbox rounded"
                        :disabled="validating || saving"
                    />
                    Usar modelo por defecto
                    <span class="text-portal-muted text-xs">({{ activeDefaultModel }})</span>
                </label>

                <MaterialSelect
                    v-if="!activeUseDefault"
                    v-model="activeModel"
                    label="Modelo"
                    :options="modelSelectOptions"
                    :disabled="validating || saving || modelSelectOptions.length === 0"
                />

                <AppButton
                    type="button"
                    variant="ghost"
                    :disabled="validating || saving"
                    @click="validateAndLoadModels()"
                >
                    {{ validating ? 'Validando…' : 'Revalidar proveedor' }}
                </AppButton>
            </template>

            <AppButton type="button" :disabled="saving || validating" @click="save">
                {{ saving ? 'Guardando…' : 'Guardar configuración IA' }}
            </AppButton>
        </div>
    </div>
</template>

<style scoped>
.phoenix-ai-validation {
    border-color: var(--portal-edge-label-border);
    color: var(--portal-muted);
    background: var(--portal-canvas-bg);
}

.phoenix-ai-validation--ok {
    border-color: rgb(16 185 129 / 0.45);
    color: var(--portal-heading);
    background: rgb(16 185 129 / 0.08);
}

.phoenix-ai-validation--err {
    border-color: rgb(244 63 94 / 0.4);
    color: var(--portal-heading);
    background: rgb(244 63 94 / 0.08);
}
</style>
