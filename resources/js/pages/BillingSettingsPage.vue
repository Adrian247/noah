<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import MaterialField from '@/components/ui/MaterialField.vue';

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
        toast.success(
            'Configuración guardada. Los próximos borradores y emisiones usarán estos valores.',
        );
        await load();
    } catch (e) {
        toast.error((e as Error).message);
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-xl">
        <RouterLink to="/app/billing" class="text-portal-link text-sm underline">← Facturas</RouterLink>
        <PageHeader
            title="Configuración de facturación"
            subtitle="Borradores, IVA y timbrado fiscal (PAC México)."
        />
        <AlertBanner variant="info" class="mb-4">
            <strong>Mano de obra en 0</strong> = solo insumos (+ IVA). <strong>Sandbox fiscal</strong> genera CFDI de prueba al emitir.
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">Cargando…</GlassCard>
        <GlassCard v-else padding="lg" class="space-y-5">
            <MaterialField
                v-model="labor"
                :label="`Tarifa sugerida mano de obra (${settings?.currency ?? 'MXN'} / hora)`"
                type="number"
            />
            <MaterialField v-model="tax" label="Tasa de IVA (ej. 0.16)" type="number" />

            <hr class="border-portal-border" />

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

            <AppButton @click="save">Guardar</AppButton>
        </GlassCard>
    </div>
</template>
