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
};

const toast = useToast();
const settings = ref<Settings | null>(null);
const labor = ref('0');
const tax = ref('0.16');
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: Settings }>('/billing/settings');
        settings.value = res.data;
        labor.value = String(res.data.billing_labor_rate_per_hour);
        tax.value = String(res.data.billing_tax_rate);
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
            }),
        });
        toast.success(
            'Configuración guardada. Los próximos borradores usarán estos valores como sugerencia.',
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
            subtitle="Define cómo se calculan los borradores al validar rutinas (insumos + mano de obra opcional + IVA)."
        />
        <AlertBanner variant="info" class="mb-4">
            <strong>Mano de obra en 0</strong> = solo se facturan insumos (+ IVA). Útil en demo y servicios a precio fijo.
        </AlertBanner>
        <GlassCard v-if="loading" padding="md">Cargando…</GlassCard>
        <GlassCard v-else padding="lg" class="space-y-5">
            <MaterialField
                v-model="labor"
                :label="`Tarifa sugerida mano de obra (${settings?.currency ?? 'MXN'} / hora)`"
                type="number"
            />
            <p class="text-portal-muted text-xs">
                Se usa al crear la prefactura; puedes ajustar horas, personas y precios en cada borrador.
            </p>
            <MaterialField v-model="tax" label="Tasa de IVA (ej. 0.16)" type="number" />
            <AppButton @click="save">Guardar</AppButton>
        </GlassCard>
    </div>
</template>
