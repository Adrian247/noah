<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api/client';
import GlassCard from '@/components/ui/GlassCard.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';

type Settings = {
    currency: string;
    billing_labor_rate_per_hour: number | string;
    billing_tax_rate: number | string;
};

const settings = ref<Settings | null>(null);
const labor = ref('0');
const tax = ref('0.16');
const loading = ref(true);
const message = ref<string | null>(null);
const error = ref<string | null>(null);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Settings }>('/billing/settings');
        settings.value = res.data;
        labor.value = String(res.data.billing_labor_rate_per_hour);
        tax.value = String(res.data.billing_tax_rate);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
    }
}

async function save() {
    message.value = null;
    error.value = null;
    try {
        await api('/billing/settings', {
            method: 'PUT',
            body: JSON.stringify({
                billing_labor_rate_per_hour: Number(labor.value),
                billing_tax_rate: Number(tax.value),
            }),
        });
        message.value = 'Configuración guardada. Los próximos borradores usarán estos valores como sugerencia.';
        await load();
    } catch (e) {
        error.value = (e as Error).message;
    }
}

onMounted(load);
</script>

<template>
    <div class="max-w-xl">
        <RouterLink to="/app/billing" class="text-sm text-primary-700 underline">← Facturas</RouterLink>
        <PageHeader
            title="Configuración de facturación"
            subtitle="Define cómo se calculan los borradores al validar rutinas (insumos + mano de obra opcional + IVA)."
        />
        <AlertBanner variant="info" class="mb-4">
            <strong>Mano de obra en 0</strong> = solo se facturan insumos (+ IVA). Útil en demo y servicios a precio fijo.
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">Cargando…</GlassCard>
        <GlassCard v-else padding="lg" class="space-y-4">
            <label class="block text-sm">
                Tarifa sugerida mano de obra ({{ settings?.currency ?? 'MXN' }} / hora)
                <input
                    v-model="labor"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"
                />
            </label>
            <p class="text-xs text-slate-500">
                Se usa al crear la prefactura; puedes ajustar horas, personas y precios en cada borrador.
            </p>
            <label class="block text-sm">
                <input
                    v-model="tax"
                    type="number"
                    min="0"
                    max="1"
                    step="0.0001"
                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"
                />
            </label>
            <AppButton @click="save">Guardar</AppButton>
            <p v-if="message" class="text-sm text-emerald-800">{{ message }}</p>
            <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        </GlassCard>
    </div>
</template>
