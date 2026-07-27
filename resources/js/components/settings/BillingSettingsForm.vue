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
    <div v-else class="space-y-4">
        <MaterialField
            v-model="labor"
            :label="`Tarifa sugerida mano de obra (${settings?.currency ?? 'MXN'} / hora)`"
            type="number"
        />
        <p class="text-portal-muted text-xs">
            Se usa al crear la prefactura; puedes ajustar horas, personas y precios en cada borrador.
        </p>
        <MaterialField v-model="tax" label="Tasa de IVA (ej. 0.16)" type="number" />
        <AppButton @click="save">Guardar facturación</AppButton>
    </div>
</template>
