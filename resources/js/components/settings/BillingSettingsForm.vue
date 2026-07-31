<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import MaterialField from '@/components/ui/MaterialField.vue';
import AppButton from '@/components/ui/AppButton.vue';

type Settings = {
    currency: string;
    billing_labor_rate_per_hour: number | string;
    billing_tax_rate: number | string;
    fiscal_enabled: boolean;
    fiscal_provider: string;
    fiscal_settings?: {
        base_url?: string;
        api_key?: string;
    };
};

const toast = useToast();
const settings = ref<Settings | null>(null);
const labor = ref('0');
const tax = ref('0.16');
const fiscalEnabled = ref(false);
const fiscalProvider = ref('sandbox');
const pacBaseUrl = ref('');
const pacApiKey = ref('');
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Settings }>('/billing/settings');
        settings.value = res.data;
        labor.value = String(res.data.billing_labor_rate_per_hour);
        tax.value = String(res.data.billing_tax_rate);
        fiscalEnabled.value = Boolean(res.data.fiscal_enabled);
        fiscalProvider.value = res.data.fiscal_provider || 'sandbox';
        pacBaseUrl.value = res.data.fiscal_settings?.base_url ?? '';
        pacApiKey.value = res.data.fiscal_settings?.api_key ?? '';
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function save() {
    try {
        await api('/billing/settings', {
            method: 'PUT',
            body: JSON.stringify({
                billing_labor_rate_per_hour: Number(labor.value),
                billing_tax_rate: Number(tax.value),
                fiscal_enabled: fiscalEnabled.value,
                fiscal_provider: fiscalProvider.value,
                fiscal_settings: {
                    base_url: pacBaseUrl.value || null,
                    api_key: pacApiKey.value || null,
                },
            }),
        });
        toast.success('Configuración de facturación guardada.');
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);

defineExpose({ load });
</script>

<template>
    <div v-if="loading" class="text-portal-muted text-sm">Cargando facturación…</div>
    <div v-else class="space-y-5">
        <MaterialField
            v-model="labor"
            :label="`Tarifa sugerida mano de obra (${settings?.currency ?? 'MXN'} / hora)`"
            type="number"
        />
        <p class="text-portal-muted text-xs">
            Se usa al crear la prefactura; puedes ajustar horas, personas y precios en cada borrador.
        </p>
        <MaterialField v-model="tax" label="Tasa de IVA (ej. 0.16)" type="number" />

        <hr class="border-portal-border" />

        <div class="space-y-3">
            <h3 class="text-portal-heading text-sm font-semibold">Timbrado fiscal (PAC)</h3>
            <p class="text-portal-muted text-xs">
                Al emitir una prefactura, Phoenix puede timbrar automáticamente. Usa
                <strong>Sandbox</strong> para pruebas locales (genera CFDI XML de ejemplo).
            </p>
            <label class="text-portal-muted flex items-center gap-2 text-sm">
                <input v-model="fiscalEnabled" type="checkbox" />
                Habilitar timbrado fiscal al emitir
            </label>
            <label class="text-portal-heading block text-sm">
                Proveedor fiscal
                <select v-model="fiscalProvider" class="field-input mt-1 w-full">
                    <option value="sandbox">Sandbox (CFDI de prueba)</option>
                    <option value="mexico_pac">PAC México (HTTP)</option>
                </select>
            </label>
            <template v-if="fiscalProvider === 'mexico_pac'">
                <MaterialField v-model="pacBaseUrl" label="URL base PAC" placeholder="https://pac.ejemplo.com/api" />
                <MaterialField v-model="pacApiKey" label="API key PAC" type="password" />
            </template>
        </div>

        <AppButton @click="save">Guardar facturación</AppButton>
    </div>
</template>
