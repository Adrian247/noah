<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, getToken, getCompanyId } from '@/api/client';
import { useToast } from '@/composables/useToast';

type InvoiceRow = {
    id: number;
    number?: string | null;
    total: string;
    currency: string;
    issued_at?: string | null;
};

const toast = useToast();
const items = ref<InvoiceRow[]>([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const res = await api<{ data: InvoiceRow[] }>('/portal/invoices');
        items.value = res.data;
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        loading.value = false;
    }
}

async function download(id: number) {
    const res = await fetch(`/api/v1/portal/invoices/${id}/download`, {
        headers: {
            Authorization: `Bearer ${getToken()}`,
            'X-Company-Id': getCompanyId() ?? '',
            Accept: 'application/pdf',
        },
    });
    if (!res.ok) {
        toast.error('No se pudo descargar la factura.');
        return;
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `factura-${id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Mis facturas</h2>
        <p class="text-sm text-slate-600">Solo ves facturas emitidas y habilitadas para tu cuenta.</p>
        <p v-if="loading" class="text-slate-500">Cargando…</p>
        <ul v-else class="divide-y rounded-xl border border-slate-200 bg-white">
            <li
                v-for="inv in items"
                :key="inv.id"
                class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">{{ inv.number ?? `Borrador #${inv.id}` }}</p>
                    <p class="text-slate-500">{{ inv.issued_at }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-semibold">{{ inv.total }} {{ inv.currency }}</span>
                    <button
                        type="button"
                        class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-stone-950"
                        @click="download(inv.id)"
                    >
                        Descargar PDF
                    </button>
                </div>
            </li>
            <li v-if="!items.length" class="px-4 py-6 text-center text-slate-500">No hay facturas disponibles.</li>
        </ul>
    </div>
</template>
